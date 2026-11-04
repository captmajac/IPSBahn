<?php

// Bahntafel module against the Symcon stub

function lastFormUpdate(IPSModule $m, string $field): array
{
    foreach (array_reverse($m->formUpdates) as [$f, $parameter, $value]) {
        if ($f === $field && $parameter === 'options') {
            return json_decode($value, true);
        }
    }
    throw new CheckFailed("no options for $field");
}

// text of the message popup an action opened, nothing echoed
function popup(IPSModule $m, callable $action): string
{
    $m->formUpdates = [];
    check(echoed($action) === '', 'no echo');
    check(in_array(['Message', 'visible', true], $m->formUpdates, true), 'popup shown');
    foreach (array_reverse($m->formUpdates) as [$f, $parameter, $value]) {
        if ($f === 'MessageText' && $parameter === 'caption') {
            return $value;
        }
    }
    throw new CheckFailed('no popup text');
}

$tests['Module: without station or credentials no requests and a hint'] = function () {
    foreach ([['Station' => ''], ['ApiKey' => ''], ['ClientId' => '']] as $props) {
        TestRegistry::reset();
        $m = makeBoard($props);
        check($m->currentStatus() === 201, 'status');
        check($m->timers['Update']['interval'] === 0 && $m->timers['Recalc']['interval'] === 0, 'timers off');
        check(TestRegistry::$calls === [], 'no requests');
        check(str_contains($m->value('BoardHtml'), 'Bitte Bahnhof und Zugangsdaten eintragen'), 'hint');
        check($m->attributes['Trips'] === '[]', 'no trips');
    }
};

$tests['Module: configured instance fetches and shows the board'] = function () {
    $m = makeBoard();
    check($m->currentStatus() === 102, 'status');
    check(count(TestRegistry::$calls) === 4, 'four requests');
    check(count(json_decode($m->attributes['Trips'], true)) === 15, 'trips');
    check(str_contains($m->value('BoardHtml'), 'RE6') && str_contains($m->value('BoardHtml'), 'Stand: 04.11.2026 18:00'), 'board');
    check($m->timers['Update']['interval'] === 3600000 && $m->timers['Recalc']['interval'] === 60000, 'timers');
    check(in_array('Board', $m->actions, true) && $m->presentations['BoardHtml'] === '~HTMLBox', 'variables');
    check($m->value('Board') === 0 && !str_contains($m->value('BoardHtml'), 'Bus 100'), 'departures, bus hidden');
};

$tests['Module: names and icons are left to the user after creation'] = function () {
    $m = makeBoard();
    $html = $m->variables['BoardHtml'];
    check(IPS_GetName($html) === 'Timetable' && IPS_GetObject($html)['ObjectIcon'] === 'clock', 'board variable at creation');
    check(IPS_GetName(2) === 'Bahntafel' && IPS_GetObject(2)['ObjectIcon'] === '', 'instance untouched');
    IPS_SetIcon($html, '');
    $m->RequestAction('Board', 1);
    $m->ApplyChanges();
    check(IPS_GetName($html) === 'Timetable' && IPS_GetObject($html)['ObjectIcon'] === '', 'no later changes');
};

$tests['Module: button names the instance after the station'] = function () {
    $m = makeBoard();
    check(popup($m, fn () => $m->RequestAction('NameInstance', '')) !== '' && IPS_GetName(2) === 'WohnortBahnhof', 'renamed');
    $m = makeBoard(['Station' => '']);
    popup($m, fn () => $m->RequestAction('NameInstance', ''));
    check(IPS_GetName(2) === 'WohnortBahnhof', 'no station, name unchanged');
};

$tests['Module: no fetch before the kernel is ready'] = function () {
    TestRegistry::$runlevel = 0;
    $m = makeBoard();
    check(TestRegistry::$calls === [] && $m->currentStatus() === 102, 'no requests');
};

$tests['Module: rejected credentials'] = function () {
    TestRegistry::$http = fakeHttp(['/18' => [401, ''], '/19' => [401, ''], '/fchg/8099001' => [401, '']]);
    $m = makeBoard();
    check($m->currentStatus() === 202, 'status ' . $m->currentStatus());
    check(count(TestRegistry::$log) === 1 && str_contains(TestRegistry::$log[0], 'credentials'), json_encode(TestRegistry::$log));
};

$tests['Module: failed update keeps the last board'] = function () {
    $m = makeBoard();
    $trips = $m->attributes['Trips'];
    TestRegistry::$http = fakeHttp(['/18' => [500, ''], '/19' => [500, '']]);
    TestRegistry::$now += 600;
    $m->RequestAction('Update', '');
    check($m->attributes['Trips'] === $trips && $m->attributes['Notice'] === 'failed', 'kept');
    check(str_contains($m->value('BoardHtml'), 'Aktualisierung fehlgeschlagen') && str_contains($m->value('BoardHtml'), 'Stand: 04.11.2026 18:00'), 'hint');
    check($m->currentStatus() === 102 && count(TestRegistry::$log) === 1, 'logged, still active');
};

$tests['Module: never fetched and API down gives a hint, not an empty box'] = function () {
    TestRegistry::$http = fakeHttp(['/18' => [503, ''], '/19' => [503, '']]);
    $m = makeBoard();
    check($m->attributes['Trips'] === '[]', 'no trips');
    $html = $m->value('BoardHtml');
    check(str_contains($html, 'Aktualisierung fehlgeschlagen') && str_contains($html, 'Keine passenden Züge'), $html);
    check(json_decode(end($m->visu), true)['notice'] === 'failed', 'tile notice');
};

$tests['Module: plan without realtime'] = function () {
    TestRegistry::$http = fakeHttp(['/18' => [200, fixture('plan_wohnort_18.xml')], '/19' => [200, fixture('plan_wohnort_19.xml')], '/fchg/8099001' => [500, '']]);
    $m = makeBoard();
    check(str_contains($m->value('BoardHtml'), 'ohne Echtzeitdaten'), 'hint');
};

$tests['Module: stale data when fetching keeps failing'] = function () {
    $m = makeBoard();
    TestRegistry::$http = fakeHttp([]);
    TestRegistry::$now += 2 * 3600 + 60;
    $m->RequestAction('Recalc', '');
    check(str_contains($m->value('BoardHtml'), 'Daten veraltet'), 'stale');
};

$tests['Module: switching the board refetches at most every 5 minutes'] = function () {
    $m = makeBoard();
    TestRegistry::$now += 300;
    $m->RequestAction('Board', 1);
    check($m->value('Board') === 1 && count(TestRegistry::$calls) === 8, 'refetched');
    check(str_contains($m->value('BoardHtml'), 'Löwenstadt Hbf') && json_decode(end($m->visu), true)['board'] === 'arr', 'arrivals');
    TestRegistry::$now += 60;
    $m->RequestAction('Board', 0);
    check($m->value('Board') === 0 && count(TestRegistry::$calls) === 8, 'throttled');
    check(json_decode(end($m->visu), true)['board'] === 'dep', 'switched anyway');
};

$tests['Module: recalc drops departed trains without requests'] = function () {
    $m = makeBoard();
    TestRegistry::$now += 30 * 60;
    $m->RequestAction('Recalc', '');
    check(count(TestRegistry::$calls) === 4, 'no requests');
    check(!str_contains($m->value('BoardHtml'), '18:26') && str_contains($m->value('BoardHtml'), '18:51'), 'departed gone');
};

$tests['Module: walk time and route from the properties'] = function () {
    $m = makeBoard(['WalkMinutes' => 20, 'RouteStation' => 'Großstadt Hbf', 'OffRoute' => 1]);
    $rows = $m->testPayload()['rows'];
    $byLine = array_column($rows, null, 'id');
    check($byLine['-7005-2611041826-5']['offRoute'] === true, 'S2 to Nordstadt grey');
    check($byLine['-7008-2611041854-3']['reach'] === 'ok', 'RE6 via Großstadt reachable');
    check($byLine['-7001-2611041801-4']['reach'] === 'missed', 'RE3 18:01 missed');
    $m = makeBoard(['RouteStation' => 'Großstadt Hbf', 'OffRoute' => 0]);
    check(!in_array('-7005-2611041826-5', array_column($m->testPayload()['rows'], 'id'), true), 'hidden');
};

$tests['Module: station search fills the station list'] = function () {
    $m = makeBoard();
    $m->RequestAction('SearchStation', ' WohnortBahnhof ');
    $options = lastFormUpdate($m, 'Station');
    check($options[0] === ['caption' => 'WohnortBahnhof', 'value' => WOHNORT], json_encode($options));
};

$tests['Module: station search keeps the current value in the list'] = function () {
    $m = makeBoard(['Station' => '']);
    $m->RequestAction('SearchStation', 'WohnortBahnhof');
    check(lastFormUpdate($m, 'Station')[0] === ['caption' => '(none)', 'value' => ''], 'unset');
    $m = makeBoard(['Station' => '{"eva":"8099003","name":"Großstadt Hbf"}']);
    $m->RequestAction('SearchStation', 'WohnortBahnhof');
    $values = array_column(lastFormUpdate($m, 'Station'), 'value');
    check($values[0] === '{"eva":"8099003","name":"Großstadt Hbf"}' && in_array(WOHNORT, $values, true), json_encode($values));
    $m = makeBoard();
    $m->RequestAction('SearchStation', 'WohnortBahnhof');
    check(count(array_keys(array_column(lastFormUpdate($m, 'Station'), 'value'), WOHNORT)) === 1, 'no duplicate');
};

$tests['Module: station search without result or with an error says so'] = function () {
    $m = makeBoard();
    check(str_contains(popup($m, fn () => $m->RequestAction('SearchStation', 'Xyz')), 'No station found'), 'none');
    TestRegistry::$http = fakeHttp(['/station/WohnortBahnhof' => [401, '']]);
    check(str_contains(popup($m, fn () => $m->RequestAction('SearchStation', 'WohnortBahnhof')), 'credentials'), 'auth');
};

$tests['Module: route stations from both boards'] = function () {
    $m = makeBoard();
    $m->RequestAction('LoadStations', '');
    $options = lastFormUpdate($m, 'RouteStation');
    check($options[0] === ['caption' => '(none)', 'value' => ''], 'none first');
    $names = array_column(array_slice($options, 1), 'value');
    check(in_array('Großstadt Hbf', $names, true) && in_array('Südstadt Hbf', $names, true) && in_array('Messestadt Hbf', $names, true), 'dep and arr');
    $sorted = $names;
    sort($sorted, SORT_STRING | SORT_FLAG_CASE);
    check($names === $sorted && count($names) === count(array_unique($names)), 'sorted, unique');
};

$tests['Module: test connection'] = function () {
    $m = makeBoard();
    check(str_contains(popup($m, fn () => $m->RequestAction('TestConnection', '')), 'OK'), 'ok');
    TestRegistry::$http = fakeHttp(['/station/8099001' => [401, '']]);
    check(str_contains(popup($m, fn () => $m->RequestAction('TestConnection', '')), 'credentials'), 'auth');
};

$tests['Module: configuration form keeps the current station and route in the lists'] = function () {
    $m = makeBoard(['RouteStation' => 'Großstadt Hbf']);
    $form = json_decode($m->GetConfigurationForm(), true);
    $find = function (array $node, string $name) use (&$find) {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if (($value['name'] ?? null) === $name) {
                    return $value;
                }
                if (($found = $find($value, $name)) !== null) {
                    return $found;
                }
            }
        }
        return null;
    };
    check(in_array(['caption' => 'WohnortBahnhof', 'value' => WOHNORT], $find($form, 'Station')['options'], true), 'station');
    check(in_array(['caption' => 'Großstadt Hbf', 'value' => 'Großstadt Hbf'], $find($form, 'RouteStation')['options'], true), 'route');
};

$tests['Module: configuration form offers the current value of every list'] = function () {
    foreach ([[], ['Station' => '', 'RouteStation' => ''], ['ClientId' => '', 'ApiKey' => '', 'Station' => '']] as $props) {
        $m = makeBoard($props);
        foreach (json_decode($m->GetConfigurationForm(), true)['elements'] as $element) {
            if ($element['type'] === 'Select') {
                $value = $m->properties[$element['name']];
                check(in_array($value, array_column($element['options'], 'value'), true), $element['name'] . ' = ' . json_encode($value));
            }
        }
    }
};

$tests['Module: changing the station drops the trips of the old one'] = function () {
    $m = makeBoard();
    TestRegistry::$http = fakeHttp([]);
    $m->properties['Station'] = '{"eva":"8099003","name":"Großstadt Hbf"}';
    $m->ApplyChanges();
    check($m->attributes['Trips'] === '[]', 'old trips gone');
};

$tests['Module: recalc fetches when the loaded window runs out'] = function () {
    $m = makeBoard(['Rows' => 2]);  // 18:00, plan up to 20:00, enough trains
    TestRegistry::$now += 30 * 60;
    $m->RequestAction('Recalc', '');
    check(count(TestRegistry::$calls) === 4, 'enough left at 18:30');
    TestRegistry::$now += 31 * 60;
    $m->RequestAction('Recalc', '');
    check(count(TestRegistry::$calls) === 8, 'refetched at 19:01');
    $m->RequestAction('Recalc', '');
    check(count(TestRegistry::$calls) === 8, 'once');
};

$tests['Module: fetch after Symcon has started'] = function () {
    TestRegistry::$runlevel = 0;
    $m = makeBoard();
    check(TestRegistry::$calls === [] && in_array([0, IPS_KERNELSTARTED], $m->messages, true), 'waits for the kernel');
    TestRegistry::$runlevel = KR_READY;
    $m->MessageSink(TestRegistry::$now, 0, IPS_KERNELSTARTED, []);
    check(count(TestRegistry::$calls) === 4, 'fetched');
};
