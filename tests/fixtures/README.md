# Test-Fixtures

Stand 2026-11-04: **synthetische** Antworten im Format der DB Timetables API für einen erfundenen Bahnhof „WohnortBahnhof“ (EVA 8099001) mit erfundenen Nachbarbahnhöfen, Linien und Betreibern.

Echte Antworten holt `tools/capture-fixtures.php <eva>` nach `tests/fixtures/raw/`. Sie werden nicht eingecheckt, weil sie den Bahnhof und damit den Wohnort verraten. Ein Abgleich am 04.11.2026 ergab:
- Der Aufbau von `plan` und `fchg` entspricht den synthetischen Dateien (zusätzlich ein ungenutztes Attribut `fb`).
- Meldungen kommen nur als Code: `t="d"` (z. B. 5, 31, 34, 36, 43, 45, 57, 99) und `t="q"` (95, 97). HIM-Meldungen (`t="h"`) mit Text gab es nicht. Code 54 steht nicht in `MessageCodes` und wird weggelassen.
- `fchg` enthält Stops über viele Stunden. `cp` ist oft gleich dem Sollgleis, `cs="c"` markiert Ausfälle.

Offen: ein Abruf über Mitternacht (Stunde 23 und 00) und die Namenssuche (`station/<name>`, eventuell nur mit `*`).

## Synthetische Antworten

| Datei | Inhalt |
|---|---|
| `plan_wohnort_18.xml` | 04.11.2026, 18 Uhr: 10 Züge der Abfahrtstafel aus dem Screenshot und ein Bus 100. RE7 18:06 (`-7002…`) hat nur `dp` (Start in WohnortBahnhof). |
| `plan_wohnort_19.xml` | 19 Uhr: S1, IC 2000 ohne `l` (Linie aus `c` + `n`), RE7 19:20 nur `ar` (endet in WohnortBahnhof), RB10 (Betreiber `rbx`) nur `dp`. |
| `plan_wohnort_23.xml`, `plan_wohnort_00.xml` | Mitternacht: RE3 23:58 und S1 00:03 am 05.11. |
| `fchg_wohnort.xml` | RE3 18:19 (`-7004…`) +12 min auf 18:31, Gleis 3 statt 2, Code 43. RE7 18:06 fällt aus, HIM-Meldung mit `cat`. S2 18:37 +4 min. S1 18:06 mit `ct` = Sollzeit (pünktlich). Ein Stop `-9999…` ohne Plan-Eintrag. RE6 mit Qualitätsmeldung Code 70 (`t="q"`, wird nicht angezeigt). |
| `station_wohnort.xml` | Suche „WohnortBahnhof“: WohnortBahnhof (8099001) und WohnortBahnhof ZOB. |

Bekannter Trip für Tests: `-7008-2611041854-3`, RE6 (NVB), Abfahrt 18:54 Gleis 1, Ziel Grenzstadt, über Großstadt Hbf.

Meldungen: `t="d"` (Verspätungsgrund) und `t="h"` (HIM, Text in `cat`) werden angezeigt, `t="q"` (Qualität, z. B. WLAN) nicht. Mit echten Antworten prüfen, ob HIM-Meldungen weitere Textattribute haben.
