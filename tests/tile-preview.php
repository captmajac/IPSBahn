<?php

// Local preview of Bahntafel/module.html with data from the fixtures, built by the module itself.
// Serve the repo root: ../tools/php/php -S 127.0.0.1:8765 and open /tests/tile-preview.php

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../Bahntafel/module.php';
require_once __DIR__ . '/helpers.php';

class CheckFailed extends Exception {}

function sample(array $props, ?callable $http = null, int $board = 0): array
{
    TestRegistry::reset();
    TestRegistry::$http = $http;
    $m = makeBoard($props);
    if ($board === 1) {
        $m->RequestAction('Board', 1);
    }
    return json_decode(end($m->visu), true);
}

$samples = [
    'Abfahrt, Wegezeit 20, Strecke grau' => sample(['WalkMinutes' => 20, 'RouteStation' => 'Großstadt Hbf', 'OffRoute' => 1]),
    'Abfahrt, Strecke ausgeblendet'      => sample(['WalkMinutes' => 20, 'RouteStation' => 'Großstadt Hbf']),
    'Ankunft'                            => sample([], null, 1),
    'Meldung mit Sonderzeichen'          => sample([], fakeHttp([
        '/18' => [200, fixture('plan_wohnort_18.xml')],
        '/fchg/8099001' => [200, '<timetable><s id="-7001-2611041801-4"><m t="h" cat="&lt;b&gt;fett&lt;/b&gt; &amp; \'quote\'"/></s></timetable>'],
    ])),
    'API ausgefallen'                    => sample([], fakeHttp([])),
    'Nicht eingerichtet'                 => sample(['Station' => '']),
];
$html = file_get_contents(__DIR__ . '/../Bahntafel/module.html');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bahntafel tile preview</title>
<style>
    body { margin: 0; font-family: -apple-system, sans-serif; background: #10161e; }
    #tile { height: 340px; width: 380px; margin: 8px; border: 1px solid #556; border-radius: 10px; }
    #bar { padding: 8px; display: flex; gap: 8px; flex-wrap: wrap; }
</style>
</head>
<body>
<div id="bar">
    <button onclick="theme()">hell/dunkel</button>
    <button onclick="tile.style.height = tile.style.height === '140px' ? '340px' : '140px'">klein/groß</button>
<?php foreach (array_keys($samples) as $i => $name): ?>
    <button onclick="tile.contentWindow.handleMessage(samples[<?= $i ?>])"><?= htmlspecialchars($name) ?></button>
<?php endforeach; ?>
</div>
<!-- the tile runs in its own document like in the Symcon tile visualization; theme variables as Symcon sets them -->
<iframe id="tile" srcdoc="<?= htmlspecialchars('<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;height:100vh;font-family:-apple-system,sans-serif;--accent-color:#00cdab;--content-color:#e8eef5;background:#1b2531;}body.light{--content-color:#000;background:#fff;}</style></head><body>' . $html . '</body></html>') ?>"></iframe>
<script>
    const samples = <?= json_encode(array_values($samples), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const tile = document.getElementById('tile');
    const theme = () => tile.contentDocument.body.classList.toggle('light');
    tile.addEventListener('load', () => tile.contentWindow.handleMessage(samples[0]));
</script>
</body>
</html>
