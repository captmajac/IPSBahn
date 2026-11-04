# IPSBahn – Bahntafel als IP-Symcon-Modul

Stand: 2026-11-04 · Autor: captmajac · Status: Entwurf zur Durchsicht

## Ziel

Die bisherige Skriptlösung (Dummy-Modul „Bahnverbindung“ mit `class.bahn.php`, `Abfrage`, `AnzeigeBahnTafel` und zwei HTML-String-Variablen) wird durch ein IP-Symcon-Modul ersetzt. Es zeigt für **einen festen Heimatbahnhof** die Abfahrts- bzw. Ankunftstafel, wahlweise eingeschränkt auf eine **feste Strecke** (z. B. WohnortBahnhof ↔ Großstadt Hbf).

Typische Nutzung: Tablet zu Hause. „Wann muss ich los, um meinen Zug nach Großstadt zu erreichen?“ (Abfahrt) und „Wann kommt mein Besuch aus Großstadt an?“ (Ankunft). Keine allgemeine Fahrplanauskunft.

**Erfolgskriterien**

- Einrichtung nur über das Konfigurationsformular, ohne EVA-Nummern oder Skripte.
- Verspätungen, Ausfälle, Gleiswechsel und Hinweise erscheinen auf der Tafel. Die alte Lösung zeigte immer „pünktlich“, weil sie nur Solldaten abrief.
- „erreichbar / knapp / nicht erreichbar“ und die Restzeit stimmen jederzeit, nicht nur zum Zeitpunkt des Abrufs.
- Darstellung im alten WebFront (`~HTMLBox`) und in der Tile-Visualisierung.
- Wenige API-Abrufe (Standard: 4 pro Stunde).

**Nicht Teil von v1**

- Umsteigeverbindungen (die DB Timetables API kennt nur Bahnhofstafeln, siehe „Grenzen“).
- Umschalter pro Client in der Kachel. v1 schaltet global wie bisher, das Datenmodell erlaubt den Ausbau.
- Erinnerung oder Alarm zu einer bestimmten Verbindung. Geplante Ausbaustufe, das Datenmodell ist darauf vorbereitet (stabile Fahrt-ID).
- Filter nach Linie.

## Datenquelle: DB Timetables API

Basis-URL `https://apis.deutschebahn.com/db-api-marketplace/apis/timetables/v1/`. Authentifiziert wird über die Header `DB-Client-Id` und `DB-Api-Key`, die Antwort ist XML.

| Endpunkt | Zweck |
|---|---|
| `station/{suchbegriff}` | Bahnhofssuche: Name → EVA-Nummer (nur im Formular) |
| `plan/{eva}/{yymmdd}/{hh}` | Soll-Fahrplan einer Stunde: Zug, Linie, Sollzeit, Sollgleis, Laufweg `ppth` |
| `fchg/{eva}` | alle bekannten Änderungen: geänderte Zeit `ct`, geändertes Gleis `cp`, Ausfall `cs="c"`, Meldungen `m` |

Abfahrt (`dp`) und Ankunft (`ar`) stecken in denselben Antworten. Eine Aktualisierung umfasst `plan` für die vorherige, die aktuelle und die nächste Stunde sowie einmal `fchg`, also 4 Abrufe. Die vorherige Stunde wird gebraucht, weil ein um 09:58 geplanter Zug mit Verspätung um 10:05 noch kommt (Ergänzung nach dem Review). Das API-Limit (60 pro Minute) wird weit unterschritten.

Die Stops aus `plan` und `fchg` werden über ihr `id`-Attribut zusammengeführt. Diese ID dient als stabile Fahrt-ID.

**Zu prüfen bei der Umsetzung:** Wie Meldungen in `fchg` aussehen. Teils ist Text enthalten, teils nur ein Code (`c`), der über eine Codetabelle in deutschen Text übersetzt werden muss. Der Parser wird an echten, anonymisierten Antworten von WohnortBahnhof entwickelt. Diese liegen als Test-Fixtures im Repo.

## Aufbau

Struktur wie bei `symcon-doorstation`: eine Bibliothek, ein Gerätemodul, eigener Test-Runner ohne Symcon-Server.

```
IPSBahn/
  library.json              Bibliothek „IPSBahn“, Autor captmajac, compatibility 9.0
  Bahntafel/
    module.json             Typ 3 (Gerät), Präfix BAHN
    module.php              Symcon-Anbindung: Eigenschaften, Variablen, Timer, Formular-Aktionen
    form.json
    module.html             Tile-Visualisierung
    locale.json
  libs/
    TimetablesClient.php    HTTP-Abrufe und XML-Parsing → Arrays von Fahrten
    BoardBuilder.php        Soll + Änderungen zusammenführen, filtern, Erreichbarkeit, Restzeit
    BoardHtml.php           HTML für die HTMLBox
    TrainBadge.php          Zuordnung Linie → Gattung und SVG-Badge
  tests/                    run.php, stubs.php, helpers.php, test_*.php, fixtures/*.xml
  docs/                     acceptance.md (Abnahme am echten System), Spec
```

Die Klassen in `libs/` kennen IPS nicht und werden mit Fixtures getestet. `module.php` verbindet sie nur mit Symcon. Den HTTP-Abruf bekommt `TimetablesClient` als austauschbare Funktion übergeben, damit Tests ohne Netz laufen.

Ein späterer Umbau zu „Splitter mit Zugangsdaten + mehrere Bahntafeln“ verschiebt nur `TimetablesClient` in einen Splitter.

## Konfigurationsformular

| Bereich | Feld | Standard | Bemerkung |
|---|---|---|---|
| Zugang | Client-ID | leer | |
| | API-Key | leer | Passwortfeld |
| | Button „Verbindung testen“ | | ruft `station/{name}` mit dem gewählten Bahnhof ab und meldet Erfolg oder Fehler |
| Bahnhof | Suchfeld + Button „Suchen“ | | füllt die Auswahlliste über `station/{suchbegriff}` |
| | Auswahl Heimatbahnhof | – | zeigt Klarnamen. Gespeichert werden Name und EVA-Nummer (unsichtbare Eigenschaften). |
| Strecke | Bahnhof der Strecke (optional) | leer | Auswahl aus Liste. Der Button „Bahnhöfe laden“ füllt sie mit allen Bahnhöfen aus den Laufwegen der aktuell geladenen Züge, alphabetisch und ohne Doppelte. |
| | Andere Züge | ausblenden | Auswahl: „ausblenden“ oder „grau darstellen“ |
| Anzeige | Zuggattungen | alle außer Bus | Häkchen: Fernverkehr (ICE/IC/EC), Regionalverkehr (RE/RB), S-Bahn, Bus, Tram, Sonstige |
| | Anzahl Zeilen | 10 | 1–20 |
| Erreichbarkeit | Wegezeit in Minuten | 0 | 0 schaltet die Erreichbarkeit aus |
| | Toleranz „knapp“ in Minuten | 4 | |
| Aktualisierung | Intervall in Minuten | 60 | 15–240 |

Zugangsdaten stehen nur in den Instanz-Eigenschaften, nie im Code, in Tests, Fixtures oder in der Doku.

## Variablen und Darstellung

- **Anzeige** (Integer, Ident `Board`): 0 = Abfahrt, 1 = Ankunft. Darstellung als Aufzählung, schaltbar per `RequestAction`, gilt global für alle Clients. Ersetzt das Skript `AnzeigeBahnTafel`.
- **Tafel** (String, Ident `BoardHtml`, `~HTMLBox`): die gerade gewählte Tafel fürs alte WebFront. Es gibt eine Variable statt zweier, die ein- und ausgeblendet werden.
- **Tile:** Die Instanz selbst ist die Kachel (`SetVisualizationType(1)`, `module.html`) und bekommt die Daten als JSON über `UpdateVisualizationValue`.

## Datenfluss

1. **Aktualisieren** (Timer im eingestellten Intervall, beim Übernehmen der Einstellungen und beim Umschalten der Anzeige; dabei höchstens einmal pro 5 Minuten): `TimetablesClient` ruft `plan` für 3 Stunden und `fchg` ab. Reichen die geladenen Stunden bei einer Neuberechnung weniger als eine Stunde voraus, wird sofort nachgeladen (höchstens alle 5 Minuten). Nach dem Start von Symcon wird einmal abgerufen. Das Ergebnis wird als JSON-Rohdatenliste im Attribut `Trips` gespeichert, mit Zeitstempel `FetchedAt`.
2. **Neu berechnen** (jede Minute, nach jeder Aktualisierung und beim Umschalten), ohne API-Abruf: `BoardBuilder` erzeugt aus `Trips`, der aktuellen Uhrzeit und den Einstellungen die Tafel für die gewählte Anzeige.
   - Echtzeit vor Soll: Wirksam ist die geänderte Zeit bzw. das geänderte Gleis, falls vorhanden.
   - Vergangene Fahrten fallen weg, maßgeblich ist die wirksame Zeit.
   - Filter Gattung, dann Strecke (ausblenden oder als grau markieren), dann Begrenzung auf die Zeilenzahl. Graue Zeilen zählen mit.
   - Erreichbarkeit nur bei Abfahrt und Wegezeit > 0: Wegezeit ist die entspannte Zeit, Toleranz das, was man mit zügigem Gehen spart. Restzeit (nach Planzeit, bei früherer Abfahrt die frühere) < Wegezeit − Toleranz → nicht erreichbar, < Wegezeit → knapp, sonst erreichbar.
   - Ergebnis: Liste von Zeilen mit `id`, `line`, `operator`, `category`, `time`, `delayed`, `platform`, `platformChanged`, `cancelled`, `direction`, `minutesLeft`, `reach`, `offRoute`, `messages` sowie Kopfdaten (Bahnhof, Anzeige, Stand, Fehlerhinweis).
3. **Ausgeben:** `BoardHtml` schreibt die Variable „Tafel“, die Kachel bekommt dieselben Zeilen als JSON.

**Gattung aus der Linie:** Die Zuordnung erfolgt über die Linie (`l`), nicht über das Kategorie-Attribut, weil dort oft Betreiberkürzel wie NVA, NVB oder SBX stehen. `S…` → S-Bahn, `RE…`/`RB…` → Regionalverkehr, ICE/IC/EC → Fernverkehr, `Bus` → Bus, `STR`/Tram → Tram, sonst Sonstige. Ohne Linie wird das Kategorie-Attribut ausgewertet.

**Strecke:** Bei Abfahrt muss der Streckenbahnhof im Laufweg *nach* dem Heimatbahnhof stehen (`dp/@ppth`), bei Ankunft im Laufweg *davor* (`ar/@ppth`). Verglichen wird der genaue Name aus der Liste.

## Aussehen

Entwürfe wurden im Brainstorming abgestimmt.

**Badges:** eigene SVG-Badges, weißes Schild mit grauem Rand. ICE/IC kursiv rot, RE/RB kursiv dunkelgrau, S-Bahn grüner Kreis mit weißem S, BUS violett, TRAM rot, Sonstige „?“. Die DB-GIFs des alten Systems werden nicht verwendet und nicht eingecheckt.

**WebFront (HTMLBox)**, dunkler Stil ähnlich dem alten, Spalten Typ · Zug · Zeit · Status · Richtung · Gleis:
- **Zug:** Linie groß (`RE3`), Betreiber klein darunter (`NVA`).
- **Zeit:** wirksame Uhrzeit, darunter klein „in N min“. Bei Verspätung steht nur die neue Uhrzeit in Rot, die Sollzeit fällt weg.
- **Status:** bei Abfahrt erreichbar (grün) / knapp (orange) / nicht erreichbar (rot), bei Ausfall „fällt aus“ (rot). Bei Ankunft „pünktlich“ (grün) oder „fällt aus“, bei Verspätung leer. Ist die Erreichbarkeit aus, verhält sich die Abfahrt wie die Ankunft.
- **Gleis:** wirksames Gleis, bei Änderung nur das neue Gleis in Rot.
- **Ausfall:** ganze Zeile ausgegraut.
- **Nicht auf der Strecke** (Modus „grau“): Zeile ausgegraut, ohne Erreichbarkeit.
- **Hinweise:** eigene Zeile unter dem Zug, orange.
- **Fuß:** „Stand: TT.MM.JJJJ HH:MM“, bei Fehler oder veralteten Daten mit Hinweis.

**Tile:** übernimmt die Text- und Akzentfarben des Symcon-Themes (hell und dunkel). Rot, Grün und Orange für den Status bleiben fest. Keine eigene Überschrift, Symcon zeigt den Instanznamen. Klein oben steht „Abfahrt“ bzw. „Ankunft“ und die Uhrzeit des Stands.
- **Normale Kachel:** kompakte Liste mit Badge · Linie · Richtung · Zeit · Gleis · Farbpunkt Erreichbarkeit. Hinweise klein darunter, Zeilenzahl nach verfügbarer Höhe (höchstens die eingestellte Zahl).
- **Kleine Kachel:** nur der nächste erreichbare Zug auf der Strecke, mit Badge, Linie, großer Uhrzeit, „in N min · Gl. X“ und Richtung. Bei Ankunft der nächste ankommende Zug.

## Fehlerbehandlung

| Situation | Verhalten |
|---|---|
| Kein Bahnhof gewählt oder Zugangsdaten leer | Instanzstatus „Bitte Bahnhof und Zugangsdaten eintragen“ (inaktiv), keine Abrufe |
| HTTP 401/403 | Instanzstatus Fehler „Zugangsdaten ungültig“, Meldung im Log |
| Netzwerkfehler, HTTP 5xx, kaputtes XML | letzte gute `Trips` bleiben, Fuß „Aktualisierung fehlgeschlagen“, Meldung im Log über `$this->LogMessage` |
| `fchg` fehlgeschlagen, `plan` ok | Tafel aus Solldaten, Fuß „ohne Echtzeitdaten“ |
| Daten älter als 2 Intervalle | Fuß „Daten veraltet“ |
| Keine Züge nach Filter | Tafel „Keine passenden Züge in den nächsten 2 Stunden“ |

## Grenzen

- Es gibt nur Direktverbindungen. Ob ein Zug auf der Strecke liegt, ergibt sich aus seinem Laufweg, Umstiege werden nicht erkannt.
- Der Zeitraum ist die aktuelle und die nächste Stunde, also zwischen 1 und 2 Stunden Vorschau. Kurz nach Mitternacht wird über das Datum hinweg abgerufen.
- Echtzeitdaten sind höchstens ein Intervall alt (Standard 60 Minuten). Umschalten aktualisiert sofort, höchstens alle 5 Minuten.

## Tests

- **Eigener Runner** wie bei der Doorstation (`tests/run.php` mit Symcon-Stubs), ausführbar mit `../tools/php/php tests/run.php`.
- **Parser:** `plan`- und `fchg`-Fixtures → erwartete Fahrten (Zeiten, Gleise, Ausfall, Meldungen, Laufweg).
- **BoardBuilder:** wirksame Zeit, Vergangenes entfernen, Gattungs- und Streckenfilter in beiden Modi, Zeilenbegrenzung, Erreichbarkeitsgrenzen (genau an Wegezeit und Toleranz), Datumswechsel um Mitternacht.
- **TrainBadge:** Zuordnung für S1, RE3, RB…, ICE 123, IC/EC, Bus, unbekannt.
- **BoardHtml:** Escaping von Namen und Meldungen, Rot bei Verspätung und Gleiswechsel, graue Zeilen.
- **Modul gegen Stubs:** Timer, `RequestAction` mit 5-Minuten-Sperre, Statuscodes, Erhalt der letzten Daten bei Fehler.
- **Konventionen** wie in IPSTuya bzw. Doorstation (z. B. kein `IPS_LogMessage`).
- **Abnahme am echten System** als Checkliste in `docs/acceptance.md`.
