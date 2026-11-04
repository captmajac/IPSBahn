# Abnahme an IP-Symcon 9

Stand 2026-11-04: noch nichts am echten System geprüft.

Vorbereitung: Bibliothek installieren, Instanz *Bahntafel* anlegen, Client-ID und API-Key eintragen. Kachel auf dem Tablet und das alte WebFront auf dem Laptop öffnen. Die alte Lösung unter „Bahnverbindung“ zum Vergleich vorerst laufen lassen.

- [ ] 0. *(abends am 04.11.2026 erledigt, Parser passt; Mitternacht noch offen)* Echte API-Antworten für die Tests holen: `DB_CLIENT_ID=… DB_API_KEY=… ../tools/php/php tests/capture-fixtures.php <EVA deines Bahnhofs>`, einmal abends und einmal gegen 23:30. Prüfen, ob die Meldungen (`<m>`) wie in `tests/fixtures/README.md` beschrieben aussehen. Weichen sie ab, Parser und Fixtures anpassen.
- [ ] 1. „Bahnhof suchen“ mit `WohnortBahnhof` → Liste mit WohnortBahnhof. Auswählen, übernehmen → Tafel gefüllt, Status aktiv.
- [ ] 2. Falscher API-Key → Status „Zugangsdaten von der DB API abgelehnt“, Meldung im Log. „Verbindung testen“ sagt dasselbe.
- [ ] 3. „Bahnhöfe laden“ → Großstadt Hbf auswählbar. Andere Züge „ausblenden“ → S2 nach Nordstadt fehlt. „grau darstellen“ → S2 grau, ohne Punkt.
- [ ] 4. Wegezeit 20 → grün/orange/rot passend zur Restzeit. Nach ein paar Minuten zählt „in N min“ herunter und abgefahrene Züge verschwinden.
- [ ] 5. Ein verspäteter Zug erscheint mit roter Uhrzeit (Abgleich mit bahn.de oder dem DB Navigator), ein Gleiswechsel mit rotem Gleis.
- [ ] 6. Umschalten Abfahrt/Ankunft im alten WebFront und in der Kachel → beide zeigen dieselbe Tafel.
- [ ] 7. Kleine Kachel zeigt den nächsten erreichbaren Zug auf der Strecke.
- [ ] 8. Kachel im hellen und im dunklen Theme lesbar, Badges (RE, S, ICE …) sichtbar.
- [ ] 9. Netzwerk des Pi kurz trennen und „Aktualisieren“ abwarten → letzte Tafel bleibt, Fuß „Aktualisierung fehlgeschlagen“.
- [ ] 10. Alte Skripte (Abfrage, AnzeigeBahnTafel) und das Ereignis deaktivieren, Ergebnis eine Woche mit der alten Tafel vergleichen, danach die alte Lösung löschen.
