<?php

// Tile visualization: initial data, updates and badges

$tests['Tile: visualization type and initial data'] = function () {
    $m = makeBoard();
    check($m->visualizationType === 1, 'type');
    $tile = $m->GetVisualizationTile();
    check(str_contains($tile, 'function handleMessage') && substr_count($tile, 'handleMessage(') >= 2, 'handler and call');
    check(preg_match('/<script>handleMessage\((".*")\);<\/script>\s*$/s', $tile, $match) === 1, 'initial call at the end');
    $payload = json_decode(json_decode($match[1]), true);
    check(count($payload['rows']) === 10 && $payload['board'] === 'dep' && $payload['station'] === 'WohnortBahnhof', 'payload');
};

$tests['Tile: script end tag in API texts does not break the tile'] = function () {
    TestRegistry::$http = fakeHttp([
        '/18' => [200, fixture('plan_wohnort_18.xml')],
        '/19' => [200, fixture('plan_wohnort_19.xml')],
        '/fchg/8099001' => [200, '<timetable><s id="-7001-2611041801-4"><m t="h" cat="&lt;/script&gt;&lt;b&gt;x"/></s></timetable>'],
    ]);
    $m = makeBoard();
    check($m->testPayload()['rows'][0]['messages'] === ['</script><b>x'], 'message in the payload');
    $tile = $m->GetVisualizationTile();
    $initial = substr($tile, strrpos($tile, '<script>handleMessage('));
    check(!str_contains(substr($initial, 8, -9), '</script>') && !str_contains($initial, '<b>'), 'escaped');
};

$tests['Tile: every recalculation is sent to the tile like the HTML box'] = function () {
    $m = makeBoard();
    $count = count($m->visu);
    TestRegistry::$now += 60;
    $m->RequestAction('Recalc', '');
    check(count($m->visu) === $count + 1, 'sent');
    $last = json_decode(end($m->visu), true);
    check($last['board'] === 'dep' && count($last['rows']) === substr_count($m->value('BoardHtml'), '<td style=\'padding:10px;vertical-align:middle;border-bottom:1px solid #444;\'><svg'), 'same rows');
};

$tests['Tile: one SVG per badge of the rows'] = function () {
    $payload = makeBoard(['ShowBus' => true])->testPayload();
    $labels = array_unique(array_column($payload['rows'], 'badge'));
    sort($labels);
    $keys = array_keys($payload['badges']);
    sort($keys);
    check($labels === $keys, json_encode($keys));
    check(str_starts_with($payload['badges']['S'], '<svg'), 'svg');
    check($payload['next']['id'] === $payload['rows'][0]['id'], 'next without walk time is the first train');
};
