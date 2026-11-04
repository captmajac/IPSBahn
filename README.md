# IPSBahn

IP-Symcon-Modul **Bahntafel**: Abfahrts- und Ankunftstafel eines festen Heimatbahnhofs aus der DB Timetables API, mit Verspätungen, Ausfällen, Gleiswechseln und Hinweisen. Optional lässt sich die Tafel auf eine feste Strecke einschränken (z. B. WohnortBahnhof ↔ Großstadt Hbf) und zeigt dann, ob man einen Zug von zu Hause noch erreicht.

Gedacht für das Tablet zu Hause: „Wann muss ich los?“ und „Wann kommt mein Besuch an?“. Eine Fahrplanauskunft mit Umstiegen ist es nicht.

## Voraussetzungen

- IP-Symcon ab 9.0
- Kostenloses Konto im [DB API Marketplace](https://developers.deutschebahn.com/) mit einer Anwendung, die die API **Timetables** abonniert hat. Von dort kommen Client-ID und API-Key (siehe unten).

## Zugang zur DB API

Die Timetables API ist kostenlos. Man braucht dafür ein Entwicklerkonto, das in ein paar Minuten angelegt ist:

1. Im [DB API Marketplace](https://developers.deutschebahn.com/db-api-marketplace/apis/) auf *Registrieren* gehen und ein Konto anlegen. Die Bestätigungsmail öffnen und sich anmelden.
2. Unter *Anwendungen* (bzw. *Meine Anwendungen*) eine neue Anwendung anlegen, z. B. „Symcon Bahntafel“. Danach zeigt die Seite die **Client-ID** und den **API-Key** (Client Secret). Den API-Key gleich notieren, er wird unter Umständen nur einmal angezeigt; sonst lässt sich ein neuer erzeugen.
3. Im Katalog die API **Timetables** öffnen, *Abonnieren* wählen, den kostenlosen Plan nehmen und die eben angelegte Anwendung auswählen.
4. Client-ID und API-Key in der Instanz eintragen und „Verbindung testen“ klicken. Direkt nach dem Abonnieren kann es ein paar Minuten dauern, bis die Zugangsdaten angenommen werden.

Client-ID und API-Key gehören nur in die Instanz, nicht in Skripte, Screenshots oder öffentliche Repositories.

## Installation

In der Konsole unter *Module Control* die URL `https://github.com/captmajac/IPSBahn` hinzufügen. Danach eine Instanz *Bahntafel* anlegen.

## Einrichtung

1. Client-ID und API-Key eintragen.
2. Unten bei „Bahnhof suchen“ den Namen eingeben (z. B. `WohnortBahnhof`) und „Suchen“ klicken. Danach oben unter „Bahnhof“ den richtigen Eintrag wählen und übernehmen.
3. Optional die Richtung festlegen: zuerst „Bahnhöfe laden“, dann unter „Über Bahnhof“ z. B. `Großstadt Hbf` wählen. Bei „Andere Züge“ entscheidest du, ob Züge, die nicht an diesem Bahnhof halten, ausgeblendet oder grau dargestellt werden. Ausgeblendete Züge verkürzen die Tafel nicht, das Modul lädt dann weitere Stunden.
   - Abfahrt: Züge, die danach an dem Bahnhof halten.
   - Ankunft: Züge, die vorher an dem Bahnhof gehalten haben. Die Tafel zeigt dann, woher der Zug kommt und wohin er weiterfährt.
4. Optional die Wegezeit von zu Hause zum Bahnhof eintragen, und zwar entspannt gegangen, dazu wie viele Minuten man mit zügigem Gehen spart. Die Abfahrtstafel zeigt dann *erreichbar* (entspannt), *knapp* (nur zügig) oder *nicht erreichbar*.
5. „Verbindung testen“ prüft die Zugangsdaten.

Die Instanz heißt danach wie der Bahnhof, sofern du sie nicht selbst umbenannt hast. Unter ihr liegen:

- **Anzeige:** Umschalter Abfahrt/Ankunft. Er gilt für alle Clients.
- **Abfahrt/Ankunft <Bahnhof>:** HTML-Box für das alte WebFront. Der Name folgt der Anzeige, ein eigener Name bleibt.
- **Kachel:** In der Tile-Visualisierung ist die Instanz selbst die Kachel, mit eigenem Umschalter Abfahrt/Ankunft. Eine große Kachel zeigt die Liste, eine kleine nur den nächsten erreichbaren Zug.

## Aktualisierung

Die Tafel wird im eingestellten Intervall abgerufen (Standard 60 Minuten). Jeder Abruf besteht aus 4 API-Aufrufen: Fahrplan der vorherigen, aktuellen und nächsten Stunde (die vorherige für verspätete Züge) sowie die Änderungen. Füllen die Züge nach den Filtern die eingestellten Zeilen nicht, lädt das Modul weitere Stunden nach, höchstens 4 Stunden über die aktuelle hinaus, und beginnt beim nächsten Abruf gleich mit so vielen Stunden. Reichen die geladenen Stunden weniger als eine Stunde voraus, lädt das Modul sofort nach, auch bei längerem Intervall. Beim Umschalten der Anzeige wird zusätzlich abgerufen, höchstens alle 5 Minuten. Restzeit und Erreichbarkeit berechnet das Modul jede Minute neu, dafür ruft es die API nicht ab.

## Grenzen

- Nur Direktverbindungen: Ob ein Zug zur Strecke gehört, ergibt sich aus seinem Laufweg.
- Vorschau 1 bis 2 Stunden, bei ausgeblendeten Zügen bis zu 5 Stunden.
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
