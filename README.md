# IPSBahntafel

Bibliothek für IP-Symcon mit Bahn-Anzeigen aus der DB Timetables API.

Gedacht für das Tablet zu Hause: „Wann muss ich los?“ und „Wann kommt mein Besuch an?“. Eine Fahrplanauskunft mit Umstiegen ist es nicht.

## Module

| Modul | Beschreibung |
|---|---|
| [Bahntafel](Bahntafel/README.md) | Abfahrts- und Ankunftstafel eines Bahnhofs mit Echtzeitdaten, Richtungsfilter und Erreichbarkeit, als HTML-Box und Kachel |

## Voraussetzungen

- IP-Symcon ab 9.0
- Kostenloser Zugang zur DB Timetables API, siehe [Bahntafel: Zugang zur DB API](Bahntafel/README.md#zugang-zur-db-api)

## Installation

Über den Module Store oder in der Konsole unter *Module Control* die URL `https://github.com/captmajac/IPSBahntafel` hinzufügen.

## Entwicklung

```bash
../tools/php/php tests/run.php
```

Die Tests laufen ohne Symcon-Server. Die Kachel lässt sich lokal ansehen:

```bash
../tools/php/php -S 127.0.0.1:8765
```

Danach `http://127.0.0.1:8765/tests/tile-preview.php` öffnen. Echte API-Antworten für die Fixtures holt `tests/capture-fixtures.php` (siehe `tests/fixtures/README.md`).
