<?php

// BoardBuilder: effective time, filters, route, rows, reachability

// trip leaving/arriving $minutes after the test clock
function trip(string $id, int $minutes, array $over = []): array
{
    $stop = [
        'planned' => TestRegistry::$now + $minutes * 60, 'changed' => null, 'platform' => '1',
        'changedPlatform' => null, 'path' => ['Großstadt Hbf'], 'cancelled' => false,
    ];
    $trip = ['id' => $id, 'line' => 'RE3', 'category' => 'NVA', 'number' => '1', 'dep' => $stop, 'arr' => $stop, 'messages' => []];
    foreach ($over as $key => $value) {
        if (in_array($key, ['dep', 'arr'], true) && is_array($value)) {
            $trip[$key] = array_merge($stop, $value);
        } else {
            $trip[$key] = $value;
        }
    }
    return $trip;
}

function settings(array $over = []): array
{
    return array_merge([
        'board' => 'dep', 'groups' => [TrainBadge::LONG, TrainBadge::REGIONAL, TrainBadge::SBAHN, TrainBadge::TRAM, TrainBadge::OTHER],
        'route' => '', 'offRoute' => 'hide', 'rows' => 10, 'walk' => 0, 'tolerance' => 4,
    ], $over);
}

function ids(array $rows): array
{
    return array_column($rows, 'id');
}

$tests['Board: changed time wins, delayed only when later, sorted by effective time'] = function () {
    $now = TestRegistry::$now;
    $rows = BoardBuilder::build([
        trip('a', 10, ['dep' => ['changed' => $now + 30 * 60]]),
        trip('b', 20),
        trip('c', 25, ['dep' => ['changed' => $now + 25 * 60]]),
    ], $now, settings());
    check(ids($rows) === ['b', 'c', 'a'], json_encode(ids($rows)));
    check($rows[2]['delayed'] === true && $rows[2]['time'] === $now + 30 * 60 && $rows[2]['minutesLeft'] === 30, 'a delayed');
    check($rows[1]['delayed'] === false, 'c on time');
};

$tests['Board: sorted across midnight'] = function () {
    $trips = TimetablesClient::parsePlan(fixture('plan_wohnort_00.xml'));
    $trips = array_merge($trips, TimetablesClient::parsePlan(fixture('plan_wohnort_23.xml')));
    $now = DateTime::createFromFormat('ymdHi', '2611042330', new DateTimeZone('Europe/Berlin'))->getTimestamp();
    $rows = BoardBuilder::build($trips, $now, settings());
    check(ids($rows) === ['-7301-2611042358-4', '-7401-2611050003-6'], json_encode(ids($rows)));
};

$tests['Board: past trips and trips without the board part are left out'] = function () {
    $rows = BoardBuilder::build([trip('past', -1), trip('now', 0), trip('ends', 5, ['dep' => null])], TestRegistry::$now, settings());
    check(ids($rows) === ['now'], json_encode(ids($rows)));
    $rows = BoardBuilder::build([trip('starts', 5, ['arr' => null]), trip('x', 6)], TestRegistry::$now, settings(['board' => 'arr']));
    check(ids($rows) === ['x'], json_encode(ids($rows)));
};

$tests['Board: groups filter'] = function () {
    $rows = BoardBuilder::build([trip('bus', 5, ['line' => 'Bus 100', 'category' => 'Bus']), trip('re', 6)], TestRegistry::$now, settings());
    check(ids($rows) === ['re'], json_encode(ids($rows)));
};

$tests['Board: route by departure path, hidden or grey'] = function () {
    $trips = [trip('to', 5, ['dep' => ['path' => ['Großstadt Hbf', 'Grenzstadt']]]), trip('other', 6, ['dep' => ['path' => ['Nordstadt']]])];
    $rows = BoardBuilder::build($trips, TestRegistry::$now, settings(['route' => 'Großstadt Hbf']));
    check(ids($rows) === ['to'], 'hide');
    $rows = BoardBuilder::build($trips, TestRegistry::$now, settings(['route' => 'Großstadt Hbf', 'offRoute' => 'grey', 'walk' => 2]));
    check(ids($rows) === ['to', 'other'] && $rows[0]['offRoute'] === false && $rows[1]['offRoute'] === true, 'grey');
    check($rows[1]['reach'] === null && $rows[0]['reach'] !== null, 'no reach on grey rows');
    $rows = BoardBuilder::build($trips, TestRegistry::$now, settings());
    check(array_column($rows, 'offRoute') === [false, false], 'no route');
};

$tests['Board: route by arrival path'] = function () {
    $trips = [trip('from', 5, ['arr' => ['path' => ['Großstadt Hbf']]]), trip('other', 6, ['arr' => ['path' => ['Nordstadt']], 'dep' => ['path' => ['Großstadt Hbf']]])];
    $rows = BoardBuilder::build($trips, TestRegistry::$now, settings(['board' => 'arr', 'route' => 'Großstadt Hbf']));
    check(ids($rows) === ['from'], json_encode(ids($rows)));
};

$tests['Board: rows limit after filtering, grey rows count'] = function () {
    $trips = [trip('a', 5, ['dep' => ['path' => ['Nordstadt']]]), trip('b', 6), trip('c', 7)];
    $rows = BoardBuilder::build($trips, TestRegistry::$now, settings(['rows' => 2, 'route' => 'Großstadt Hbf', 'offRoute' => 'grey']));
    check(ids($rows) === ['a', 'b'], json_encode(ids($rows)));
    $rows = BoardBuilder::build($trips, TestRegistry::$now, settings(['rows' => 2, 'route' => 'Großstadt Hbf']));
    check(ids($rows) === ['b', 'c'], json_encode(ids($rows)));
};

$tests['Board: reachability limits'] = function () {
    $trips = [trip('19', 19), trip('20', 20), trip('23', 23), trip('24', 24), trip('x', 30, ['dep' => ['cancelled' => true]])];
    $rows = BoardBuilder::build($trips, TestRegistry::$now, settings(['walk' => 20, 'tolerance' => 4]));
    check(array_column($rows, 'reach') === ['missed', 'tight', 'tight', 'ok', null], json_encode(array_column($rows, 'reach')));
    check(array_column(BoardBuilder::build($trips, TestRegistry::$now, settings()), 'reach') === [null, null, null, null, null], 'walk 0');
    check(array_unique(array_column(BoardBuilder::build($trips, TestRegistry::$now, settings(['walk' => 20, 'board' => 'arr'])), 'reach')) === [null], 'arrival');
};

$tests['Board: direction, platform, operator and badge'] = function () {
    $trips = [trip('a', 5, [
        'line' => 'RE6', 'category' => 'NVB',
        'dep' => ['path' => ['Großstadt Hbf', 'Grenzstadt'], 'changedPlatform' => '3'],
        'arr' => ['path' => ['Löwenstadt Hbf', 'Ostheim']],
    ])];
    $dep = BoardBuilder::build($trips, TestRegistry::$now, settings())[0];
    check($dep['direction'] === 'Grenzstadt' && $dep['platform'] === '3' && $dep['platformChanged'] === true, 'dep');
    check($dep['operator'] === 'NVB' && $dep['badge'] === 'RE' && $dep['line'] === 'RE6', 'operator/badge');
    $arr = BoardBuilder::build($trips, TestRegistry::$now, settings(['board' => 'arr']))[0];
    check($arr['direction'] === 'Löwenstadt Hbf' && $arr['platform'] === '1' && $arr['platformChanged'] === false, 'arr');
    check($arr['continuesTo'] === 'Grenzstadt' && $dep['continuesTo'] === '', 'onward destination only on arrivals');
    $ends = BoardBuilder::build([trip('end', 5, ['dep' => null])], TestRegistry::$now, settings(['board' => 'arr']))[0];
    check($ends['continuesTo'] === '', 'train ends here');
    $ic = BoardBuilder::build([trip('ic', 5, ['line' => 'IC 2000', 'category' => 'IC', 'dep' => ['path' => []]])], TestRegistry::$now, settings())[0];
    check($ic['operator'] === '' && $ic['direction'] === '', 'IC');
};

$tests['Board: route stations and next train'] = function () {
    $trips = [trip('a', 5, ['dep' => ['path' => ['Nordstadt', 'Moordorf']]]), trip('b', 6, ['dep' => ['path' => ['Moordorf']]])];
    check(BoardBuilder::routeStations($trips, 'dep') === ['Moordorf', 'Nordstadt'], json_encode(BoardBuilder::routeStations($trips, 'dep')));
    $rows = BoardBuilder::build([
        trip('cancelled', 30, ['dep' => ['cancelled' => true]]),
        trip('missed', 10),
        trip('grey', 31, ['dep' => ['path' => ['Nordstadt']]]),
        trip('next', 32),
    ], TestRegistry::$now, settings(['walk' => 20, 'route' => 'Großstadt Hbf', 'offRoute' => 'grey']));
    check(BoardBuilder::nextTrain($rows)['id'] === 'next', 'next');
    check(BoardBuilder::nextTrain([]) === null, 'none');
};
