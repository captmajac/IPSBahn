<?php

// TimetablesClient: parsing of plan, fchg and station responses, requests

function ts(string $yymmddhhii): int
{
    return DateTime::createFromFormat('ymdHi', $yymmddhhii, new DateTimeZone('Europe/Berlin'))->getTimestamp();
}

function tripById(array $trips, string $id): array
{
    foreach ($trips as $trip) {
        if ($trip['id'] === $id) {
            return $trip;
        }
    }
    throw new CheckFailed("trip $id missing");
}

$tests['Client: plan yields one trip per stop with line, time, platform and path'] = function () {
    $trips = TimetablesClient::parsePlan(fixture('plan_wohnort_18.xml'));
    check(count($trips) === 11, 'count ' . count($trips));
    $re6 = tripById($trips, '-7008-2611041854-3');
    check($re6['line'] === 'RE6' && $re6['category'] === 'NVB' && $re6['number'] === '95620', 'line');
    check($re6['dep']['planned'] === ts('2611041854') && $re6['dep']['platform'] === '1', 'dep time/platform');
    check(end($re6['dep']['path']) === 'Grenzstadt' && in_array('Großstadt Hbf', $re6['dep']['path'], true), 'dep path');
    check($re6['arr']['path'][0] === 'Löwenstadt Hbf', 'arr path');
    check($re6['dep']['changed'] === null && $re6['dep']['cancelled'] === false && $re6['messages'] === [], 'no changes');
};

$tests['Client: stop with only departure or only arrival'] = function () {
    $trips = TimetablesClient::parsePlan(fixture('plan_wohnort_19.xml'));
    check(tripById($trips, '-7103-2611041920-2')['dep'] === null, 'RE7 ends here');
    check(tripById($trips, '-7104-2611041940-3')['arr'] === null, 'RB10 starts here');
};

$tests['Client: line without l attribute is category and number'] = function () {
    $ic = tripById(TimetablesClient::parsePlan(fixture('plan_wohnort_19.xml')), '-7102-2611041912-1');
    check($ic['line'] === 'IC 2000', $ic['line']);
};

$tests['Client: changes give changed time, platform, cancellation and messages'] = function () {
    $trips = TimetablesClient::merge(TimetablesClient::parsePlan(fixture('plan_wohnort_18.xml')), TimetablesClient::parseChanges(fixture('fchg_wohnort.xml')));
    check(count($trips) === 11, 'change without plan adds no trip');
    $re3 = tripById($trips, '-7004-2611041819-4');
    check($re3['dep']['changed'] === ts('2611041831') && $re3['dep']['changedPlatform'] === '3', 'RE3 dep');
    check($re3['arr']['changed'] === ts('2611041830'), 'RE3 arr');
    check($re3['messages'] === ['Verspätung eines vorausfahrenden Zuges'], json_encode($re3['messages']));
    $re7 = tripById($trips, '-7002-2611041806-1');
    check($re7['dep']['cancelled'] === true, 'RE7 cancelled');
    check($re7['messages'] === ['Bauarbeiten zwischen WohnortBahnhof und Löwenstadt'], json_encode($re7['messages']));
    check(tripById($trips, '-7008-2611041854-3')['messages'] === [], 'quality message not shown');
    check(tripById($trips, '-7001-2611041801-4')['dep']['changed'] === null, 'unchanged trip');
};

$tests['Client: fetchTrips requests previous, current and next hour across midnight and the changes'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp([
        '/plan/8099001/261104/22' => [200, '<timetable/>'],
        '/plan/8099001/261104/23' => [200, fixture('plan_wohnort_23.xml')],
        '/plan/8099001/261105/00' => [200, fixture('plan_wohnort_00.xml')],
        '/fchg/8099001' => [200, '<timetable/>'],
    ]));
    $result = $client->fetchTrips('8099001', ts('2611042330'));
    $urls = array_map(fn ($c) => substr($c[0], strlen('https://apis.deutschebahn.com/db-api-marketplace/apis/timetables/v1/')), TestRegistry::$calls);
    check($urls === ['plan/8099001/261104/22', 'plan/8099001/261104/23', 'plan/8099001/261105/00', 'fchg/8099001'], json_encode($urls));
    check(in_array('DB-Client-Id: id', TestRegistry::$calls[0][1], true) && in_array('DB-Api-Key: key', TestRegistry::$calls[0][1], true), 'headers');
    check(count($result['trips']) === 2 && $result['realtime'] === true, 'trips');
};

$tests['Client: 401 is an authentication error'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp(['/18' => [401, ''], '/19' => [401, ''], '/fchg/8099001' => [401, '']]));
    try {
        $client->fetchTrips('8099001', TestRegistry::$now);
        check(false, 'no exception');
    } catch (TimetablesAuthException $e) {
    }
};

$tests['Client: failing plan is an error, failing changes only lose realtime'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp(['/18' => [500, ''], '/19' => [200, fixture('plan_wohnort_19.xml')], '/fchg/8099001' => [200, '']]));
    try {
        $client->fetchTrips('8099001', TestRegistry::$now);
        check(false, 'no exception');
    } catch (TimetablesAuthException $e) {
        check(false, 'not an auth error');
    } catch (TimetablesException $e) {
    }
    $client = new TimetablesClient('id', 'key', fakeHttp(['/18' => [200, fixture('plan_wohnort_18.xml')], '/19' => [200, fixture('plan_wohnort_19.xml')], '/fchg/8099001' => [500, '']]));
    $result = $client->fetchTrips('8099001', TestRegistry::$now);
    check($result['realtime'] === false && count($result['trips']) === 15, 'plan only');
};

$tests['Client: broken XML is an error'] = function () {
    try {
        TimetablesClient::parsePlan('<timetable><s');
        check(false, 'no exception');
    } catch (TimetablesException $e) {
    }
};

$tests['Client: station search'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp(['/station/WohnortBahnhof' => [200, fixture('station_wohnort.xml')]]));
    $stations = $client->searchStations('WohnortBahnhof');
    check($stations[0] === ['eva' => '8099001', 'name' => 'WohnortBahnhof'] && count($stations) === 2, json_encode($stations));
};

$tests['Client: station search escapes the pattern'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp([]));
    $client->searchStations('Großstadt Hbf');
    check(str_ends_with(TestRegistry::$calls[0][0], '/station/Gro%C3%9Fstadt%20Hbf'), TestRegistry::$calls[0][0]);
};

$tests['Client: plan 404 for one hour gives no trips for that hour'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp(['/18' => [200, fixture('plan_wohnort_18.xml')], '/fchg/8099001' => [200, '<timetable/>']]));
    check(count($client->fetchTrips('8099001', TestRegistry::$now)['trips']) === 11, 'trips of 18 only');
};

$tests['Client: no plan for both hours is an error, not an empty board'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp([]));
    try {
        $client->fetchTrips('8099001', TestRegistry::$now);
        check(false, 'no exception');
    } catch (TimetablesException $e) {
    }
};

$tests['Client: delayed train of the previous hour is included'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp([
        '/plan/8099001/261104/17' => [200, '<timetable><s id="late"><tl c="NVA" n="1"/><dp pt="2611041758" pp="1" l="RE3" ppth="Großstadt Hbf"/></s></timetable>'],
        '/plan/8099001/261104/18' => [200, fixture('plan_wohnort_18.xml')],
        '/fchg/8099001' => [200, '<timetable><s id="late"><dp ct="2611041810"/></s></timetable>'],
    ]));
    $trips = $client->fetchTrips('8099001', TestRegistry::$now + 5 * 60)['trips'];
    check(tripById($trips, 'late')['dep']['changed'] === ts('2611041810'), 'late train');
};

$tests['Client: DST change requests every hour once'] = function () {
    $client = new TimetablesClient('id', 'key', fakeHttp(['/fchg/8099001' => [200, '<timetable/>'], '/02' => [200, '<timetable/>']]));
    $client->fetchTrips('8099001', ts('2610250230') + 3600);  // 02:30 CET, after the change
    $urls = array_map(fn ($c) => basename(dirname($c[0])) . '/' . basename($c[0]), TestRegistry::$calls);
    check(count($urls) === count(array_unique($urls)), json_encode($urls));
};

