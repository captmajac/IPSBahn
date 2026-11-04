<?php

declare(strict_types=1);

class TimetablesException extends Exception {}

class TimetablesAuthException extends TimetablesException {}

// DB Timetables API: station search, planned timetable (plan) and changes (fchg) of one station.
// Trip: ['id', 'line', 'category', 'number', 'dep' => ?Stop, 'arr' => ?Stop, 'messages' => string[]]
// Stop: ['planned' => int, 'changed' => ?int, 'platform' => string, 'changedPlatform' => ?string,
//        'path' => string[] (dep: following stations, arr: previous stations), 'cancelled' => bool]
class TimetablesClient
{
    const BASE_URL = 'https://apis.deutschebahn.com/db-api-marketplace/apis/timetables/v1/';
    const TIMEZONE = 'Europe/Berlin';

    private $http;

    public function __construct(private string $clientId, private string $apiKey, ?callable $http = null)
    {
        $this->http = $http ?? [self::class, 'curl'];
    }

    // [['eva' => string, 'name' => string], ...]; unknown pattern gives []
    public function searchStations(string $pattern): array
    {
        $xml = $this->get('station/' . rawurlencode($pattern), true);
        if ($xml === null) {
            return [];
        }
        $stations = [];
        foreach (self::load($xml)->getElementsByTagName('station') as $station) {
            $stations[] = ['eva' => $station->getAttribute('eva'), 'name' => $station->getAttribute('name')];
        }
        return $stations;
    }

    // planned trips of the previous, current and next hour with the known changes; realtime false if fchg failed
    public function fetchTrips(string $eva, int $now): array
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        // previous hour too: a train planned at 09:58 and delayed to 10:15 is still to come at 10:05;
        // unique: on the DST change two timestamps an hour apart can be the same local hour
        $hours = [];
        foreach ([$now - 3600, $now, $now + 3600] as $time) {
            $hours[] = (new DateTime('@' . $time))->setTimezone($tz)->format('ymd/H');
        }
        $trips = [];
        $found = false;
        foreach (array_unique($hours) as $hour) {
            $xml = $this->get("plan/$eva/$hour", true);
            $found = $found || $xml !== null;
            $trips = array_merge($trips, $xml === null ? [] : self::parsePlan($xml));
        }
        // a single empty hour is normal, none at all means a wrong station or an API problem
        if (!$found) {
            throw new TimetablesException("DB API has no timetable for station $eva");
        }
        try {
            $changes = self::parseChanges((string) $this->get("fchg/$eva"));
        } catch (TimetablesAuthException $e) {
            throw $e;
        } catch (TimetablesException $e) {
            return ['trips' => $trips, 'realtime' => false];
        }
        return ['trips' => self::merge($trips, $changes), 'realtime' => true];
    }

    public static function parsePlan(string $xml): array
    {
        $trips = [];
        foreach (self::load($xml)->getElementsByTagName('s') as $s) {
            $tl = $s->getElementsByTagName('tl')->item(0);
            $category = $tl ? $tl->getAttribute('c') : '';
            $number = $tl ? $tl->getAttribute('n') : '';
            $dp = $s->getElementsByTagName('dp')->item(0);
            $ar = $s->getElementsByTagName('ar')->item(0);
            $line = ($dp ?? $ar)?->getAttribute('l') ?? '';
            $trips[] = [
                'id'       => $s->getAttribute('id'),
                'line'     => self::lineName($line, $category, $number),
                'category' => $category,
                'number'   => $number,
                'dep'      => $dp ? self::stop($dp) : null,
                'arr'      => $ar ? self::stop($ar) : null,
                'messages' => [],
            ];
        }
        return $trips;
    }

    // id => ['dep' => ?changes, 'arr' => ?changes, 'messages' => string[]]
    public static function parseChanges(string $xml): array
    {
        $changes = [];
        foreach (self::load($xml)->getElementsByTagName('s') as $s) {
            $messages = [];
            foreach ($s->getElementsByTagName('m') as $m) {
                $text = match ($m->getAttribute('t')) {
                    'h'     => trim($m->getAttribute('cat')),
                    'd'     => MessageCodes::text((int) $m->getAttribute('c')),
                    default => null,
                };
                if ($text !== null && $text !== '') {
                    $messages[] = $text;
                }
            }
            $dp = $s->getElementsByTagName('dp')->item(0);
            $ar = $s->getElementsByTagName('ar')->item(0);
            $changes[$s->getAttribute('id')] = [
                'dep'      => $dp ? self::change($dp) : null,
                'arr'      => $ar ? self::change($ar) : null,
                'messages' => array_values(array_unique($messages)),
            ];
        }
        return $changes;
    }

    // changes for trips that are not in the loaded hours are ignored
    public static function merge(array $trips, array $changes): array
    {
        foreach ($trips as &$trip) {
            $change = $changes[$trip['id']] ?? null;
            if ($change === null) {
                continue;
            }
            foreach (['dep', 'arr'] as $part) {
                if ($trip[$part] !== null && $change[$part] !== null) {
                    $trip[$part] = array_merge($trip[$part], $change[$part]);
                }
            }
            $trip['messages'] = $change['messages'];
        }
        return $trips;
    }

    private static function stop(DOMElement $e): array
    {
        $path = $e->getAttribute('ppth');
        return [
            'planned'         => self::time($e->getAttribute('pt')),
            'changed'         => null,
            'platform'        => $e->getAttribute('pp'),
            'changedPlatform' => null,
            'path'            => $path === '' ? [] : explode('|', $path),
            'cancelled'       => false,
        ];
    }

    // only the attributes that are set
    private static function change(DOMElement $e): array
    {
        $change = [];
        if ($e->hasAttribute('ct')) {
            $change['changed'] = self::time($e->getAttribute('ct'));
        }
        if ($e->hasAttribute('cp')) {
            $change['changedPlatform'] = $e->getAttribute('cp');
        }
        if ($e->hasAttribute('cs')) {
            $change['cancelled'] = $e->getAttribute('cs') === 'c';
        }
        return $change;
    }

    // RE3, S1 or Bus 100 from l; a bare number (l="3") gets the category; no l: category and train number (IC 2000)
    private static function lineName(string $line, string $category, string $number): string
    {
        if ($line === '') {
            return trim("$category $number");
        }
        return ctype_digit($line) ? $category . $line : $line;
    }

    private static function time(string $value): ?int
    {
        $time = DateTime::createFromFormat('!ymdHi', $value, new DateTimeZone(self::TIMEZONE));
        return $time ? $time->getTimestamp() : null;
    }

    private static function load(string $xml): DOMDocument
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $xml !== '' && $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new TimetablesException('Invalid XML from the DB API');
        }
        return $doc;
    }

    // body; null for 404 if allowed
    private function get(string $path, bool $allowNotFound = false): ?string
    {
        $headers = ["DB-Client-Id: {$this->clientId}", "DB-Api-Key: {$this->apiKey}", 'Accept: application/xml'];
        $response = ($this->http)(self::BASE_URL . $path, $headers);
        $status = (int) $response['status'];
        if ($status === 401 || $status === 403) {
            throw new TimetablesAuthException("DB API rejected the credentials (HTTP $status)");
        }
        if ($status === 404 && $allowNotFound) {
            return null;
        }
        if ($status < 200 || $status >= 300) {
            throw new TimetablesException("DB API request failed (HTTP $status): $path");
        }
        return (string) $response['body'];
    }

    private static function curl(string $url, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $headers]);
        $body = curl_exec($ch);
        $status = $body === false ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => (string) $body];
    }
}
