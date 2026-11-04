<?php

// Saves real DB Timetables API responses as test fixtures.
// Usage: DB_CLIENT_ID=... DB_API_KEY=... ../tools/php/php tools/capture-fixtures.php <eva>
// The credentials are only read from the environment and never written to a file.

date_default_timezone_set('Europe/Berlin');

$eva = $argv[1] ?? '';
$clientId = getenv('DB_CLIENT_ID') ?: '';
$apiKey = getenv('DB_API_KEY') ?: '';
if (!preg_match('/^\d{7}$/', $eva) || $clientId === '' || $apiKey === '') {
    fwrite(STDERR, "Usage: DB_CLIENT_ID=... DB_API_KEY=... php tools/capture-fixtures.php <eva>\n");
    exit(1);
}

$base = 'https://apis.deutschebahn.com/db-api-marketplace/apis/timetables/v1/';
$now = time();
$next = $now + 3600;
$paths = [
    'plan_' . date('H', $now) => 'plan/' . $eva . '/' . date('ymd/H', $now),
    'plan_' . date('H', $next) => 'plan/' . $eva . '/' . date('ymd/H', $next),
    'fchg' => 'fchg/' . $eva,
    'station' => 'station/' . $eva,
];

$dir = __DIR__ . '/../tests/fixtures/raw';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$stamp = date('Ymd-Hi', $now);
foreach ($paths as $name => $path) {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ["DB-Client-Id: $clientId", "DB-Api-Key: $apiKey", 'Accept: application/xml'],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $file = "$dir/{$name}_{$eva}_$stamp.xml";
    file_put_contents($file, (string) $body);
    echo "$status  $path  →  " . basename($file) . "\n";
}
