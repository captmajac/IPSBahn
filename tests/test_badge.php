<?php

// TrainBadge: group and badge from line (operator codes like NVA, NVB, SBX in the category)

$tests['Badge: group from the line before the category'] = function () {
    $cases = [
        ['S1', 'SBX', TrainBadge::SBAHN],
        ['RE3', 'NVA', TrainBadge::REGIONAL],
        ['RB10', 'rbx', TrainBadge::REGIONAL],
        ['', 'ICE', TrainBadge::LONG],
        ['IC 2000', 'IC', TrainBadge::LONG],
        ['', 'EC', TrainBadge::LONG],
        ['Bus 100', 'Bus', TrainBadge::BUS],
        ['STR 1', 'STR', TrainBadge::TRAM],
        ['', 'XYZ', TrainBadge::OTHER],
        ['', 'S', TrainBadge::SBAHN],
        ['', 'RE', TrainBadge::REGIONAL],
    ];
    foreach ($cases as [$line, $category, $group]) {
        check(TrainBadge::group($line, $category) === $group, "$line/$category → " . TrainBadge::group($line, $category));
    }
};

$tests['Badge: label'] = function () {
    check(TrainBadge::label('S1', 'SBX') === 'S', 'S');
    check(TrainBadge::label('RE3', 'NVA') === 'RE', 'RE');
    check(TrainBadge::label('RB10', 'rbx') === 'RB', 'RB');
    check(TrainBadge::label('', 'ICE') === 'ICE', 'ICE');
    check(TrainBadge::label('IC 2000', 'IC') === 'IC', 'IC');
    check(TrainBadge::label('Bus 100', 'Bus') === 'BUS', 'BUS');
    check(TrainBadge::label('STR 1', 'STR') === 'TRAM', 'TRAM');
    check(TrainBadge::label('', 'XYZ') === '?', '?');
};

$tests['Badge: SVG colors and escaping'] = function () {
    $s = TrainBadge::svg('S');
    check(str_starts_with($s, '<svg') && str_contains($s, '#008d4f') && str_contains($s, '>S<'), 'S badge');
    check(str_contains(TrainBadge::svg('ICE'), '#e3000f'), 'ICE red');
    check(str_contains(TrainBadge::svg('RE'), '#5a5a5a'), 'RE gray');
    check(!str_contains(TrainBadge::svg('<x>'), '<x>'), 'escaped');
};
