<?php

// BoardHtml: WebFront HTML box

function row(array $over = []): array
{
    return array_merge([
        'id' => 'a', 'line' => 'S1', 'operator' => 'SBX', 'badge' => 'S', 'time' => TestRegistry::$now + 26 * 60, 'delayed' => false,
        'platform' => '13', 'platformChanged' => false, 'cancelled' => false, 'direction' => 'Südstadt Hbf',
        'minutesLeft' => 26, 'continuesTo' => '', 'reach' => null, 'offRoute' => false, 'messages' => [],
    ], $over);
}

function meta(array $over = []): array
{
    return array_merge(['board' => 'dep', 'fetchedAt' => TestRegistry::$now, 'notice' => '', 'walk' => 20], $over);
}

// HTML of the single row with the given text (row cells only, without the header)
function rowHtml(string $html, string $needle): string
{
    preg_match_all('/<tr\b.*?<\/tr>/s', $html, $m);
    foreach ($m[0] as $tr) {
        if (str_contains($tr, $needle) && !str_contains($tr, '<th')) {
            return $tr;
        }
    }
    throw new CheckFailed("row with $needle missing");
}

$tests['Html: header, line, operator, time left and footer'] = function () {
    $html = BoardHtml::render([row()], meta());
    foreach (['Typ', 'Zug', 'Zeit', 'Status', 'Richtung', 'Gleis', '>S1<', '>SBX<', '18:26', 'in 26 min', 'Südstadt Hbf', '>13<', 'Stand: 04.11.2026 18:00', '<svg'] as $text) {
        check(str_contains($html, $text), $text);
    }
};

$tests['Html: delay only as red time, platform change only as red platform'] = function () {
    $tr = rowHtml(BoardHtml::render([row(['delayed' => true, 'time' => TestRegistry::$now + 31 * 60, 'minutesLeft' => 31, 'platform' => '3', 'platformChanged' => true])], meta()), '18:31');
    check(preg_match('/#E53935[^>]*>18:31</', $tr) === 1, 'red time');
    check(preg_match('/#E53935[^>]*>3</', $tr) === 1, 'red platform');
    check(!str_contains($tr, '+') && !str_contains($tr, 'statt') && !str_contains($tr, '<s>'), 'no extra delay text');
};

$tests['Html: departure status from reachability'] = function () {
    $html = BoardHtml::render([row(['id' => 'a', 'line' => 'L1', 'reach' => 'ok']), row(['id' => 'b', 'line' => 'L2', 'reach' => 'tight']), row(['id' => 'c', 'line' => 'L3', 'reach' => 'missed']), row(['id' => 'd', 'line' => 'L4', 'cancelled' => true])], meta());
    check(preg_match('/#43A047[^>]*>erreichbar</', rowHtml($html, '>L1<')) === 1, 'ok');
    check(preg_match('/#FB8C00[^>]*>knapp</', rowHtml($html, '>L2<')) === 1, 'tight');
    check(preg_match('/#E53935[^>]*>nicht erreichbar</', rowHtml($html, '>L3<')) === 1, 'missed');
    check(preg_match('/#E53935[^>]*>fällt aus</', rowHtml($html, '>L4<')) === 1, 'cancelled');
};

$tests['Html: arrival status, and departure without walk time like arrival'] = function () {
    foreach ([meta(['board' => 'arr']), meta(['walk' => 0])] as $m) {
        $html = BoardHtml::render([row(['line' => 'L1']), row(['id' => 'b', 'line' => 'L2', 'delayed' => true])], $m);
        check(preg_match('/#43A047[^>]*>pünktlich</', rowHtml($html, '>L1<')) === 1, 'on time');
        check(!str_contains(rowHtml($html, '>L2<'), 'pünktlich') && !str_contains(rowHtml($html, '>L2<'), 'erreichbar'), 'delayed: empty status');
    }
};

$tests['Html: cancelled and off-route rows are greyed'] = function () {
    $html = BoardHtml::render([row(['line' => 'L1', 'cancelled' => true]), row(['id' => 'b', 'line' => 'L2', 'offRoute' => true]), row(['id' => 'c', 'line' => 'L3'])], meta());
    check(str_contains(rowHtml($html, '>L1<'), 'opacity:0.5'), 'cancelled');
    check(str_contains(rowHtml($html, '>L2<'), 'opacity:0.5'), 'off route');
    check(!str_contains(rowHtml($html, '>L3<'), 'opacity:0.5'), 'normal');
};

$tests['Html: messages escaped in their own row'] = function () {
    $html = BoardHtml::render([row(['messages' => ["a < b & 'c'"], 'direction' => 'X<script>'])], meta());
    check(str_contains($html, 'a &lt; b &amp; &#039;c&#039;') && str_contains($html, '#FB8C00'), 'message');
    check(!str_contains($html, '<script>'), 'direction escaped');
};

$tests['Html: notices and empty board'] = function () {
    check(str_contains(BoardHtml::render([row()], meta(['notice' => 'failed'])), 'Aktualisierung fehlgeschlagen'), 'failed');
    check(str_contains(BoardHtml::render([row()], meta(['notice' => 'noRealtime'])), 'ohne Echtzeitdaten'), 'noRealtime');
    check(str_contains(BoardHtml::render([row()], meta(['notice' => 'stale'])), 'Daten veraltet'), 'stale');
    check(str_contains(BoardHtml::render([], meta()), 'Keine passenden Züge in den nächsten 2 Stunden'), 'empty');
    $html = BoardHtml::render([], meta(['notice' => 'notConfigured', 'fetchedAt' => 0]));
    check(str_contains($html, 'Bitte Bahnhof und Zugangsdaten eintragen') && !str_contains($html, 'Keine passenden'), 'not configured');
    check(!str_contains($html, 'Stand: 01.01.1970'), 'no stand without data');
};

$tests['Html: times in German time also on a server in UTC'] = function () {
    date_default_timezone_set('UTC');
    try {
        $html = BoardHtml::render([row()], meta());
    } finally {
        date_default_timezone_set('Europe/Berlin');
    }
    check(str_contains($html, '18:26') && str_contains($html, 'Stand: 04.11.2026 18:00'), 'Berlin time');
};

$tests['Html: missed rows tinted red, tight rows orange, with their messages'] = function () {
    $html = BoardHtml::render([row(['line' => 'L1', 'reach' => 'missed', 'messages' => ['Bauarbeiten']]), row(['line' => 'L2', 'reach' => 'tight']), row(['line' => 'L3', 'reach' => 'ok'])], meta());
    check(str_contains(rowHtml($html, 'L1'), 'background:' . BoardHtml::MISSED_BG) && str_contains(rowHtml($html, 'Bauarbeiten'), 'background:' . BoardHtml::MISSED_BG), 'missed');
    check(str_contains(rowHtml($html, 'L2'), 'background:' . BoardHtml::TIGHT_BG), 'tight');
    check(!str_contains(rowHtml($html, 'L3'), BoardHtml::MISSED_BG) && !str_contains(rowHtml($html, 'L3'), BoardHtml::TIGHT_BG), 'ok untinted');
};

$tests['Html: arrivals show where the train comes from and where it goes on'] = function () {
    $html = BoardHtml::render([row(['direction' => 'Großstadt Hbf', 'continuesTo' => 'Grenzstadt'])], meta(['board' => 'arr']));
    check(str_contains($html, '>Von</th>') && !str_contains($html, '>Richtung</th>'), 'header');
    check(str_contains(rowHtml($html, 'Großstadt Hbf'), 'weiter nach Grenzstadt'), 'onward');
    check(!str_contains(BoardHtml::render([row()], meta(['board' => 'arr'])), 'weiter nach'), 'ends here');
    check(str_contains(BoardHtml::render([row()], meta()), '>Richtung</th>'), 'departure header');
};
