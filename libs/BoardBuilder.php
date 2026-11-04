<?php

declare(strict_types=1);

// Departure or arrival board from the trips of TimetablesClient, for a given time; no API calls.
// settings: board 'dep'|'arr', groups (TrainBadge groups to show), route (station name or ''),
//           offRoute 'hide'|'grey', rows, walk (minutes, 0 = no reachability), tolerance (minutes)
class BoardBuilder
{
    public static function build(array $trips, int $now, array $settings): array
    {
        $part = $settings['board'];
        $rows = [];
        foreach ($trips as $trip) {
            $stop = $trip[$part];
            if ($stop === null || !in_array(TrainBadge::group($trip['line'], $trip['category']), $settings['groups'], true)) {
                continue;
            }
            $time = $stop['changed'] ?? $stop['planned'];
            if ($time < $now) {
                continue;
            }
            $offRoute = $settings['route'] !== '' && !in_array($settings['route'], $stop['path'], true);
            if ($offRoute && $settings['offRoute'] === 'hide') {
                continue;
            }
            $minutesLeft = intdiv($time - $now, 60);
            // departures: destination; arrivals: origin and, if the train goes on, its destination
            $direction = $part === 'dep' ? end($stop['path']) : reset($stop['path']);
            $continuesTo = $part === 'arr' && $trip['dep'] !== null ? end($trip['dep']['path']) : false;
            $rows[] = [
                'id'              => $trip['id'],
                'line'            => $trip['line'],
                'operator'        => stripos($trip['line'], $trip['category']) === 0 ? '' : $trip['category'],
                'badge'           => TrainBadge::label($trip['line'], $trip['category']),
                'time'            => $time,
                'delayed'         => $stop['changed'] !== null && $stop['changed'] > $stop['planned'],
                'platform'        => $stop['changedPlatform'] ?? $stop['platform'],
                'platformChanged' => $stop['changedPlatform'] !== null && $stop['changedPlatform'] !== $stop['platform'],
                'cancelled'       => $stop['cancelled'],
                'direction'       => $direction === false ? '' : $direction,
                'continuesTo'     => $continuesTo === false ? '' : $continuesTo,
                'minutesLeft'     => $minutesLeft,
                // a delay may shrink, so the walk is planned for the earlier of planned and changed time
                'reach'           => self::reach($part, $stop['cancelled'] || $offRoute, intdiv(min($time, $stop['planned']) - $now, 60), $settings),
                'offRoute'        => $offRoute,
                'messages'        => $trip['messages'],
            ];
        }
        usort($rows, fn (array $a, array $b) => $a['time'] <=> $b['time']);
        return array_slice($rows, 0, max(1, $settings['rows']));
    }

    // all stations of the paths of one board part, for choosing the route station
    public static function routeStations(array $trips, string $part): array
    {
        $stations = [];
        foreach ($trips as $trip) {
            foreach ($trip[$part]['path'] ?? [] as $station) {
                $stations[$station] = true;
            }
        }
        $names = array_keys($stations);
        sort($names, SORT_STRING | SORT_FLAG_CASE);
        return $names;
    }

    // first train worth going for (small tile)
    public static function nextTrain(array $rows): ?array
    {
        foreach ($rows as $row) {
            if (!$row['cancelled'] && !$row['offRoute'] && $row['reach'] !== 'missed') {
                return $row;
            }
        }
        return null;
    }

    private static function reach(string $part, bool $skip, int $minutesLeft, array $settings): ?string
    {
        $walk = $settings['walk'];
        if ($part !== 'dep' || $walk <= 0 || $skip) {
            return null;
        }
        // walk is the relaxed time, tolerance what hurrying saves
        if ($minutesLeft < $walk - $settings['tolerance']) {
            return 'missed';
        }
        return $minutesLeft < $walk ? 'tight' : 'ok';
    }
}
