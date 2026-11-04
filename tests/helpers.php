<?php

// Shared test helpers

function fixture(string $name): string
{
    return file_get_contents(__DIR__ . "/fixtures/$name");
}

// fake HTTP: path suffix => [status, body]; records the calls
function fakeHttp(array $responses): callable
{
    return function (string $url, array $headers) use ($responses): array {
        TestRegistry::$calls[] = [$url, $headers];
        foreach ($responses as $suffix => $response) {
            if (str_ends_with($url, $suffix)) {
                return ['status' => $response[0], 'body' => $response[1]];
            }
        }
        return ['status' => 404, 'body' => ''];
    };
}

// WohnortBahnhof responses of the fixtures (18 and 19 o'clock, changes, station search)
function wohnortHttp(): callable
{
    return fakeHttp([
        '/plan/8099001/261104/18' => [200, fixture('plan_wohnort_18.xml')],
        '/plan/8099001/261104/19' => [200, fixture('plan_wohnort_19.xml')],
        '/fchg/8099001'           => [200, fixture('fchg_wohnort.xml')],
        '/station/WohnortBahnhof'         => [200, fixture('station_wohnort.xml')],
        '/station/8099001'        => [200, fixture('station_wohnort.xml')],
    ]);
}

// Module with test clock and fake HTTP (TestRegistry::$http)
class TestBahntafel extends Bahntafel
{
    protected function now(): int { return TestRegistry::$now; }

    protected function client(): TimetablesClient
    {
        return new TimetablesClient($this->ReadPropertyString('ClientId'), $this->ReadPropertyString('ApiKey'), TestRegistry::$http);
    }

    public function testPayload(): array { return $this->payload(); }
}

const WOHNORT = '{"eva":"8099001","name":"WohnortBahnhof"}';

// Instance 2, configured for WohnortBahnhof unless $props say otherwise
function makeBoard(array $props = []): TestBahntafel
{
    TestRegistry::$http ??= wohnortHttp();
    $m = new TestBahntafel(2);
    $m->Create();
    $m->properties = array_merge($m->properties, ['ClientId' => 'id', 'ApiKey' => 'key', 'Station' => WOHNORT], $props);
    $m->ApplyChanges();
    return $m;
}

// echo output of an action (popup in the configuration form)
function echoed(callable $action): string
{
    ob_start();
    $action();
    return (string) ob_get_clean();
}
