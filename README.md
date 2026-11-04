# IPSBahn

IP-Symcon-Modul **Bahntafel**: Abfahrts- und Ankunftstafel eines festen Heimatbahnhofs aus der DB Timetables API, mit Verspätungen, Ausfällen, Gleiswechseln und Hinweisen. Optional lässt sich die Tafel auf eine feste Strecke einschränken (z. B. WohnortBahnhof ↔ Großstadt Hbf) und zeigt dann, ob man einen Zug von zu Hause noch erreicht.

Gedacht für das Tablet zu Hause: „Wann muss ich los?“ und „Wann kommt mein Besuch an?“. Eine Fahrplanauskunft mit Umstiegen ist es nicht.

## Voraussetzungen

- IP-Symcon ab 9.0
- Kostenloses Konto im [DB API Marketplace](https://developers.deutschebahn.com/) mit einer Anwendung, die die API **Timetables** abonniert hat. Von dort kommen Client-ID und API-Key.

## Installation

In der Konsole unter *Module Control* die URL `https://github.com/captmajac/IPSBahn` hinzufügen. Danach eine Instanz *Bahntafel* anlegen.

## Einrichtung

1. Client-ID und API-Key eintragen.
2. Unten bei „Bahnhof suchen“ den Namen eingeben (z. B. `WohnortBahnhof`) und „Suchen“ klicken. Danach oben unter „Bahnhof“ den richtigen Eintrag wählen und übernehmen.
3. Optional die Strecke festlegen: zuerst „Bahnhöfe laden“, dann unter „Bahnhof der Strecke“ z. B. `Großstadt Hbf` wählen. Bei „Andere Züge“ entscheidest du, ob Züge, die nicht über diesen Bahnhof fahren, ausgeblendet oder grau dargestellt werden.
   - Abfahrt: Züge, die nach oder über den Bahnhof fahren.
   - Ankunft: Züge, die von dort kommen oder über ihn kommen.
4. Optional die Wegezeit von zu Hause zum Bahnhof eintragen. Die Abfahrtstafel zeigt dann *erreichbar*, *knapp* oder *nicht erreichbar*.
5. „Verbindung testen“ prüft die Zugangsdaten.

Unter der Instanz liegen danach:

- **Anzeige:** Umschalter Abfahrt/Ankunft. Er gilt für alle Clients.
- **Tafel:** HTML-Box für das alte WebFront.
- **Kachel:** In der Tile-Visualisierung ist die Instanz selbst die Kachel. Eine große Kachel zeigt die Liste, eine kleine nur den nächsten erreichbaren Zug.

## Aktualisierung

Die Tafel wird im eingestellten Intervall abgerufen (Standard 60 Minuten). Jeder Abruf besteht aus 4 API-Aufrufen: Fahrplan der vorherigen, aktuellen und nächsten Stunde (die vorherige für verspätete Züge) sowie die Änderungen. Reichen die geladenen Stunden weniger als eine Stunde voraus, lädt das Modul sofort nach, auch bei längerem Intervall. Beim Umschalten der Anzeige wird zusätzlich abgerufen, höchstens alle 5 Minuten. Restzeit und Erreichbarkeit berechnet das Modul jede Minute neu, dafür ruft es die API nicht ab.

## Grenzen

- Nur Direktverbindungen: Ob ein Zug zur Strecke gehört, ergibt sich aus seinem Laufweg.
- Vorschau 1 bis 2 Stunden.
- Verspätungen sind höchstens ein Intervall alt.

## Entwicklung

```bash
../tools/php/php tests/run.php
```

Die Tests laufen ohne Symcon-Server. Die Kachel lässt sich lokal ansehen:

```bash
../tools/php/php -S 127.0.0.1:8765
```

Danach `http://127.0.0.1:8765/tests/tile-preview.php` öffnen. Echte API-Antworten für die Fixtures holt `tools/capture-fixtures.php` (siehe `tests/fixtures/README.md`).
