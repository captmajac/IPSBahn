<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/MessageCodes.php';
require_once __DIR__ . '/../libs/TimetablesClient.php';
require_once __DIR__ . '/../libs/TrainBadge.php';
require_once __DIR__ . '/../libs/BoardBuilder.php';
require_once __DIR__ . '/../libs/BoardHtml.php';

// Departure and arrival board of one station from the DB Timetables API, for the WebFront and the tile visualization
class Bahntafel extends IPSModule
{
    const STATUS_NOT_CONFIGURED = 201;
    const STATUS_AUTH = 202;
    const THROTTLE_S = 300;    // switching the board fetches at most this often
    const RECALC_MS = 60000;   // minutes left and reachability without API calls
    const MIN_AHEAD_S = 3600;  // fetch again when the loaded hours reach less far ahead
    const MAX_AHEAD_H = 4;     // hours after the current one loaded at most to fill the rows
    const POS_BOARD = 1;
    const POS_HTML = 2;

    // property => TrainBadge group
    const GROUPS = [
        'ShowLong'     => TrainBadge::LONG,
        'ShowRegional' => TrainBadge::REGIONAL,
        'ShowSBahn'    => TrainBadge::SBAHN,
        'ShowBus'      => TrainBadge::BUS,
        'ShowTram'     => TrainBadge::TRAM,
        'ShowOther'    => TrainBadge::OTHER,
    ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('ClientId', '');
        $this->RegisterPropertyString('ApiKey', '');
        // {"eva": "8099001", "name": "WohnortBahnhof"} or ''
        $this->RegisterPropertyString('Station', '');
        $this->RegisterPropertyString('RouteStation', '');
        $this->RegisterPropertyInteger('OffRoute', 0);  // 0 hide, 1 grey
        foreach (self::GROUPS as $property => $group) {
            $this->RegisterPropertyBoolean($property, $group !== TrainBadge::BUS);
        }
        $this->RegisterPropertyInteger('Rows', 10);
        $this->RegisterPropertyInteger('WalkMinutes', 0);
        $this->RegisterPropertyInteger('ToleranceMinutes', 4);
        $this->RegisterPropertyInteger('IntervalMinutes', 60);

        // trips of TimetablesClient::fetchTrips, of the station in TripsEva
        $this->RegisterAttributeString('Trips', '[]');
        $this->RegisterAttributeString('TripsEva', '');
        $this->RegisterAttributeInteger('FetchedAt', 0);
        // end of the last loaded hour
        $this->RegisterAttributeInteger('LoadedUntil', 0);
        // '' | 'failed' | 'noRealtime' of the last update
        $this->RegisterAttributeString('Notice', '');
        $this->RegisterAttributeInteger('LastFetch', 0);
        // hours after the current one the last update needed to fill the rows
        $this->RegisterAttributeInteger('AheadHours', 1);

        foreach (['Update', 'Recalc'] as $timer) {
            $this->RegisterTimer($timer, 0, 'IPS_RequestAction($_IPS[\'TARGET\'], "' . $timer . '", "");');
        }

        $this->SetVisualizationType(1);
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->MaintainVariable('Board', $this->Translate("Board"), 1, $this->boardPresentation(), self::POS_BOARD, true);
        $this->EnableAction('Board');
        // name and icon are set once on creation, afterwards they belong to the user
        $created = @$this->GetIDForIdent('BoardHtml') === false;
        $this->RegisterVariableString('BoardHtml', $this->Translate("Timetable"), '~HTMLBox', self::POS_HTML);
        if ($created) {
            IPS_SetIcon($this->GetIDForIdent('BoardHtml'), 'clock');
        }

        $eva = $this->station()['eva'] ?? '';
        if ($this->ReadAttributeString('TripsEva') !== $eva) {
            $this->WriteAttributeString('Trips', '[]');
            $this->WriteAttributeString('TripsEva', $eva);
            $this->WriteAttributeInteger('FetchedAt', 0);
            $this->WriteAttributeInteger('LoadedUntil', 0);
            $this->WriteAttributeString('Notice', '');
        }

        if (!$this->configured()) {
            $this->SetStatus(self::STATUS_NOT_CONFIGURED);
            $this->SetTimerInterval('Update', 0);
            $this->SetTimerInterval('Recalc', 0);
            $this->render();
            return;
        }
        $this->SetStatus(102);
        $this->SetTimerInterval('Update', $this->ReadPropertyInteger('IntervalMinutes') * 60000);
        $this->SetTimerInterval('Recalc', self::RECALC_MS);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->update();
        } else {
            $this->render();
        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $route = $this->ReadPropertyString('RouteStation');
        $options = [
            'Station'      => [$this->currentStationOption()],
            'RouteStation' => array_merge([self::option($this->Translate("(none)"), '')], $route === '' ? [] : [self::option($route, $route)]),
        ];
        $form['elements'] = self::withOptions($form['elements'], $options);
        return json_encode($form);
    }

    public function GetVisualizationTile()
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP;
        $initial = json_encode(json_encode($this->payload(), $flags), $flags);
        return file_get_contents(__DIR__ . '/module.html') . "\n<script>handleMessage($initial);</script>";
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // ApplyChanges before the kernel was ready did not fetch
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Board':
                $this->SetValue('Board', (int) $Value);
                if ($this->now() - $this->ReadAttributeInteger('LastFetch') >= self::THROTTLE_S) {
                    $this->update();
                } else {
                    $this->render();
                }
                break;
            case 'Update':
                $this->update();
                break;
            case 'Recalc':
                // a longer interval than the loaded hours must not empty the board
                $loadedUntil = $this->ReadAttributeInteger('LoadedUntil');
                if ($loadedUntil > 0 && $loadedUntil - $this->now() < self::MIN_AHEAD_S
                    && $this->now() - $this->ReadAttributeInteger('LastFetch') >= self::THROTTLE_S) {
                    $this->update();
                } else {
                    $this->render();
                }
                break;
            case 'SearchStation':
                $this->searchStation(trim((string) $Value));
                break;
            case 'LoadStations':
                $this->loadStations();
                break;
            case 'TestConnection':
                $this->testConnection();
                break;
            case 'NameInstance':
                $this->nameInstance();
                break;
            default:
                throw new Exception("Invalid Ident $Ident");
        }
    }

    protected function now(): int
    {
        return time();
    }

    protected function client(): TimetablesClient
    {
        return new TimetablesClient($this->ReadPropertyString('ClientId'), $this->ReadPropertyString('ApiKey'));
    }

    // data of the tile: rows of the chosen board, next train for the small tile, badge SVGs by label
    protected function payload(): array
    {
        $rows = $this->rows();
        $badges = [];
        foreach ($rows as $row) {
            $badges[$row['badge']] ??= TrainBadge::svg($row['badge']);
        }
        return [
            'board'     => $this->board(),
            'station'   => $this->station()['name'] ?? '',
            'fetchedAt' => $this->ReadAttributeInteger('FetchedAt'),
            'notice'    => $this->notice(),
            'rows'      => $rows,
            'next'      => BoardBuilder::nextTrain($rows),
            'badges'    => $badges,
        ];
    }

    private function update(): void
    {
        if (!$this->configured()) {
            return;
        }
        $this->WriteAttributeInteger('LastFetch', $this->now());
        try {
            // hidden trains must not shorten the board: load more hours until the rows are filled
            $ahead = min(self::MAX_AHEAD_H, max(1, $this->ReadAttributeInteger('AheadHours')));
            while (true) {
                $result = $this->client()->fetchTrips($this->station()['eva'], $this->now(), $ahead);
                $rows = $this->rows($result['trips']);
                if ($ahead >= self::MAX_AHEAD_H || count($rows) >= $this->ReadPropertyInteger('Rows')) {
                    break;
                }
                $ahead++;
            }
            $loadedUntil = self::nextHourStart($this->now()) + $ahead * 3600;
            // the next update starts one hour shorter when the last hour was not needed
            $lastTime = $rows === [] ? PHP_INT_MAX : end($rows)['time'];
            $this->WriteAttributeInteger('AheadHours', $ahead > 1 && $lastTime < $loadedUntil - 3600 ? $ahead - 1 : $ahead);
            $this->WriteAttributeString('Trips', json_encode($result['trips']));
            $this->WriteAttributeInteger('FetchedAt', $this->now());
            $this->WriteAttributeInteger('LoadedUntil', $loadedUntil);
            $this->WriteAttributeString('Notice', $result['realtime'] ? '' : 'noRealtime');
            $this->SetStatus(102);
        } catch (TimetablesAuthException $e) {
            $this->WriteAttributeString('Notice', 'failed');
            $this->SetStatus(self::STATUS_AUTH);
            $this->LogMessage($e->getMessage(), KL_ERROR);
        } catch (TimetablesException $e) {
            $this->WriteAttributeString('Notice', 'failed');
            $this->LogMessage($e->getMessage(), KL_ERROR);
        }
        $this->render();
    }

    private function render(): void
    {
        $payload = $this->payload();
        $meta = [
            'board'     => $payload['board'],
            'fetchedAt' => $payload['fetchedAt'],
            'notice'    => $payload['notice'],
            'walk'      => $this->ReadPropertyInteger('WalkMinutes'),
        ];
        $this->SetValue('BoardHtml', BoardHtml::render($payload['rows'], $meta));
        $this->UpdateVisualizationValue(json_encode($payload));
    }

    // rows of the loaded trips, or of the given ones
    private function rows(?array $trips = null): array
    {
        if (!$this->configured()) {
            return [];
        }
        $groups = [];
        foreach (self::GROUPS as $property => $group) {
            if ($this->ReadPropertyBoolean($property)) {
                $groups[] = $group;
            }
        }
        return BoardBuilder::build($trips ?? json_decode($this->ReadAttributeString('Trips'), true), $this->now(), [
            'board'     => $this->board(),
            'groups'    => $groups,
            'route'     => $this->ReadPropertyString('RouteStation'),
            'offRoute'  => $this->ReadPropertyInteger('OffRoute') === 1 ? 'grey' : 'hide',
            'rows'      => $this->ReadPropertyInteger('Rows'),
            'walk'      => $this->ReadPropertyInteger('WalkMinutes'),
            'tolerance' => $this->ReadPropertyInteger('ToleranceMinutes'),
        ]);
    }

    // stale data matters more than the result of the last attempt
    private function notice(): string
    {
        if (!$this->configured()) {
            return 'notConfigured';
        }
        $fetchedAt = $this->ReadAttributeInteger('FetchedAt');
        if ($fetchedAt > 0 && $this->now() - $fetchedAt > 2 * $this->ReadPropertyInteger('IntervalMinutes') * 60) {
            return 'stale';
        }
        return $this->ReadAttributeString('Notice');
    }

    private function board(): string
    {
        return $this->GetValue('Board') === 1 ? 'arr' : 'dep';
    }

    private function searchStation(string $pattern): void
    {
        try {
            $stations = $pattern === '' ? [] : $this->client()->searchStations($pattern);
        } catch (TimetablesException $e) {
            $this->message($this->errorText($e));
            return;
        }
        if ($stations === []) {
            $this->message($this->Translate("No station found"));
            return;
        }
        // the current value stays selectable, otherwise the form cannot be saved
        $options = [$this->currentStationOption()];
        foreach ($stations as $s) {
            $option = self::option($s['name'], json_encode(['eva' => $s['eva'], 'name' => $s['name']]));
            if ($option['value'] !== $options[0]['value']) {
                $options[] = $option;
            }
        }
        $this->UpdateFormField('Station', 'options', json_encode($options));
    }

    private function currentStationOption(): array
    {
        $station = $this->station();
        return self::option($station === null ? $this->Translate("(none)") : $station['name'], $this->ReadPropertyString('Station'));
    }

    // stations on the paths of the loaded trips, departures and arrivals together
    private function loadStations(): void
    {
        $trips = json_decode($this->ReadAttributeString('Trips'), true);
        $names = array_unique(array_merge(BoardBuilder::routeStations($trips, 'dep'), BoardBuilder::routeStations($trips, 'arr')));
        sort($names, SORT_STRING | SORT_FLAG_CASE);
        $options = [self::option($this->Translate("(none)"), '')];
        foreach ($names as $name) {
            $options[] = self::option($name, $name);
        }
        $this->UpdateFormField('RouteStation', 'options', json_encode($options));
    }

    private function testConnection(): void
    {
        try {
            $stations = $this->client()->searchStations($this->station()['eva'] ?? 'Berlin Hbf');
            $this->message(sprintf($this->Translate("OK: %d station(s) found"), count($stations)));
        } catch (TimetablesException $e) {
            $this->message($this->errorText($e));
        }
    }

    // popup in the configuration form; an echo from RequestAction shows up as a PHP warning with file and line
    private function message(string $text): void
    {
        $this->UpdateFormField('MessageText', 'caption', $text);
        $this->UpdateFormField('Message', 'visible', true);
    }

    // only on the user's click: the instance name belongs to the user
    private function nameInstance(): void
    {
        $station = $this->station();
        if ($station === null) {
            $this->message($this->Translate("Please choose a station first"));
            return;
        }
        IPS_SetName($this->InstanceID, $station['name']);
        $this->message(sprintf($this->Translate("Instance renamed: %s"), $station['name']));
    }

    private function errorText(TimetablesException $e): string
    {
        return $e instanceof TimetablesAuthException
            ? $this->Translate("The DB API rejected the credentials")
            : $this->Translate("The DB API could not be reached") . ': ' . $e->getMessage();
    }

    private function configured(): bool
    {
        return $this->station() !== null && $this->ReadPropertyString('ClientId') !== '' && $this->ReadPropertyString('ApiKey') !== '';
    }

    private function station(): ?array
    {
        $station = json_decode($this->ReadPropertyString('Station'), true);
        return is_array($station) && ($station['eva'] ?? '') !== '' ? $station : null;
    }

    // start of the hour after the one of $time, local time like the API
    private static function nextHourStart(int $time): int
    {
        $hour = (new DateTime('@' . $time))->setTimezone(new DateTimeZone(TimetablesClient::TIMEZONE));
        $hour->setTime((int) $hour->format('H'), 0);
        return $hour->getTimestamp() + 3600;
    }

    private static function option(string $caption, string $value): array
    {
        return ['caption' => $caption, 'value' => $value];
    }

    // sets the options of named elements, also inside panels
    private static function withOptions(array $elements, array $options): array
    {
        foreach ($elements as &$element) {
            if (isset($element['name'], $options[$element['name']])) {
                $element['options'] = $options[$element['name']];
            }
            if (isset($element['items'])) {
                $element['items'] = self::withOptions($element['items'], $options);
            }
        }
        return $elements;
    }

    // enumeration: departure, arrival (two buttons as in the old view)
    private function boardPresentation(): array
    {
        $option = fn (int $value, string $caption) => ['Value' => $value, 'Caption' => $caption, 'IconActive' => false, 'IconValue' => '', 'Color' => -1];
        return [
            'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
            'OPTIONS'      => json_encode([$option(0, $this->Translate("Departure")), $option(1, $this->Translate("Arrival"))]),
        ];
    }
}
