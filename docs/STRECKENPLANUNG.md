# Streckenplanung (Phase 5)

Stand: Backend und Oberfläche (Seite „Streckenplanung“ mit Ortssuche, Halteliste, Karte, Zeitplan) gebaut; mit dem echten Dienst noch nicht ausprobiert.
**Noch nicht gebaut:** Speichern der Tour als Kalkulationsbasis (siehe „Nächste Schritte“). Betriebshöfe sind gebaut, siehe „Gespeicherte Orte“.

## Überblick

Die Streckenplanung nutzt einen **externen Routing-Dienst** (auf dem Mittwald-Hosting ist kein eigener Routing-Server möglich).
Die Anwendung kennt nur zwei Schnittstellen, nie einen bestimmten Dienst:

| Schnittstelle | Aufgabe | Datei |
|---|---|---|
| `RoutingProvider` | Route über mehrere Punkte mit Bus-/Lkw-Profil | `app/Routing/RoutingProvider.php` |
| `GeocodingProvider` | Orte und Adressen zu Koordinaten | `app/Routing/GeocodingProvider.php` |

Umgesetzt ist beides mit **openrouteservice.org** (`app/Routing/OpenRouteService.php`, Daten von OpenStreetMap, Profil `driving-hgv`
mit Höhe, Breite, Länge und Gewicht des Fahrzeugs). Ein anderer Dienst (GraphHopper, eigener Server) braucht nur eine neue Klasse
und eine Zeile im `AppServiceProvider`.

## Einrichtung (Betreiber)

1. Kostenloses Konto bei openrouteservice.org anlegen, API-Schlüssel erzeugen.
2. In `backend/.env` eintragen: `ORS_API_KEY=...` (nie ins Frontend, nie in die Datenbank, nie ins Repository).
3. Ohne Schlüssel antwortet die Streckenplanung mit einer klaren Meldung („noch nicht eingerichtet“), alles andere läuft weiter.

Weitere Werte (optional): `ORS_BASE_URL` (eigene Instanz), `ORS_TIMEOUT`, `ROUTING_DURATION_FACTOR`.

**Kontingent:** Der kostenlose Tarif hat Tages- und Minutengrenzen; die Ortssuche ist am knappsten. Das „Quota exceeded“ meldet der Dienst als
**403** (nicht 429). Schutzmaßnahmen:

- **Keine Vorschläge beim Tippen** (kein Autocomplete). Die Ortsfelder sind reiner Text; gesucht wird nur beim Berechnen, je geändertem Feld
  eine Anfrage (`RouteService.locate`, `limit=1`), wie im Projekt RATIOserver.
- **Zwischenspeicher im Browser:** Text → gefundener Ort in `localStorage` (`bustotal.planung.orte.v2`, höchstens 500 Einträge; die Version
  im Namen verwirft Einträge früherer Programmstände). Derselbe Text wird nie zweimal gesucht. „Nichts gefunden“ wird nicht gemerkt.
- **Grundsatz: Anfragen sparen, nie doppelt ohne Notwendigkeit** (Entscheidung des Nutzers 2026-11-24):
  „Strecke berechnen“ ist gesperrt, solange das Ergebnis aktuell ist (`needsCalc`, auch Enter im Feld); die Trefferliste „Ort wählen“ fragt
  dieselbe Suche nur einmal an; **Optimieren** (Fahrzeitmatrix) läuft nie von selbst, nur auf Klick, und der Tipp dazu erscheint nur nach
  einer eigenen Berechnung, nicht beim Laden einer Tour. Eine Berechnung kostet höchstens eine Routenanfrage (alle Punkte in einem Aufruf),
  mit Optimieren eine Matrixanfrage dazu. **Ratetreffer** der Ortssuche (Sicherheit unter 0,5) filtert der Server weg.
- **Zwischenspeicher auf dem Server** für alle Firmen: Strecken und Fahrzeitmatrix 12 Stunden, Ortssuche 7 Tage. Der Schlüssel der Ortssuche
  ist normalisiert (Kleinschreibung, mehrfache Leerzeichen, Satzzeichen am Ende).
- **Sperre nach „Quota exceeded“:** Merker `ors:quota:<bereich>` (geocode, directions, matrix) bis Mitternacht UTC. Solange gehen in diesem
  Bereich keine Anfragen mehr hinaus; die anderen Bereiche laufen weiter, Treffer aus dem Zwischenspeicher auch.
- **Zähler** je Bereich und Tag: `ors:count:<bereich>:<JJJJ-MM-TT>`, abrufbar mit `OpenRouteService::usage()`.
- **Bremse je Benutzer:** `/geocode` 30, `/routes/calculate` 60 Anfragen je Minute.

**Vor dem Produktivbetrieb** prüfen: Nutzungsbedingungen für den gewerblichen Einsatz und das Kontingent im Verhältnis zur Zahl der Firmen
(alle Firmen teilen sich einen Schlüssel). Reicht es nicht, einen anderen Geocoder hinter `GeocodingProvider` setzen (eigener
Photon-/Nominatim-Server oder bezahlter Tarif).

## Schnittstellen der Anwendung

Nur Administrator und Disponent. Fehler des Dienstes kommen mit deutscher Meldung zurück, **nie als 5xx** (das Frontend würde 502/503/504
sonst als „Server nicht erreichbar“ deuten): `422` = am Ort liegt es (keine Straße in der Nähe, Route zu lang), `424` = der Dienst selbst
(nicht eingerichtet, Kontingent erreicht, antwortet nicht); das Feld `reason` nennt den Grund.

- `GET /api/geocode?q=Ulm&limit=5` → `{ data: [{ label, lat, lon, postal_code, city, country }] }`
- `POST /api/routes/calculate`

```json
{
  "stops": [
    { "lat": 48.14, "lon": 11.58, "label": "München Hbf", "dwell_minutes": 30, "driver_free": false },
    { "lat": 47.66, "lon": 9.48, "label": "Friedrichshafen" }
  ],
  "depot": { "lat": 49.30, "lon": 10.57, "label": "Betriebshof" },
  "return_to_depot": true,
  "vehicle_id": "<uuid>",
  "start_at": "2027-03-01T06:00:00+01:00",
  "extended_days": 0
}
```

Antwort (Auszug): `distance_m`, `empty_distance_m` / `loaded_distance_m` (Leerfahrten getrennt), `drive_s`, `elapsed_s`, `days`, `legs[]`
(mit `empty`), `schedule[]` (Ablauf mit Fahren, Pause, Halt, Ruhezeit und Zeitpunkten), `warnings[]`, `geometry` (Linienzug für die Karte,
`[lat, lon]`), `vehicle_cost` (Kilometer × Kostensatz des Fahrzeugs, nur eine Rechengrundlage). Es wird **nichts gespeichert**.

- **Leerfahrten:** Mit Betriebshof sind die Fahrt vom Betriebshof zum ersten Halt und zurück Leerfahrten.
- **Halte** zählen als Arbeitszeit. Ist der Fahrer währenddessen frei (`driver_free`), zählt ein Halt ab 45 Minuten als Pause und ab
  11 Stunden (Übernachtung) als Tagesruhezeit.
- Fahrzeugmaße gehen als Einschränkung an den Dienst (Brückenhöhen, Gewicht).

## Lenk- und Ruhezeiten (`DrivingTimePlanner`)

Planung nach VO (EG) Nr. 561/2006, bewusst die einfache, sichere Auslegung:

- Nach 4,5 Stunden Lenkzeit 45 Minuten Pause am Stück (die erlaubte Teilung 15 + 30 wird nicht ausgenutzt, das ist die Reserve).
- Tageslenkzeit 9 Stunden, `extended_days` (0 bis 2) erlaubt für die ersten Tage 10 Stunden.
- Tagesruhezeit 11 Stunden; ein Arbeitstag (Lenken, Pausen, Halte) dauert höchstens 13 Stunden. Reicht das Fenster nicht mehr für die Pause,
  beginnt gleich die Ruhezeit.
- **Nicht geprüft** (steht als Hinweis im Ergebnis, soweit erkennbar): Wochenlenkzeit 56 Stunden, Doppelwoche 90 Stunden, Wochenruhezeit,
  verkürzte Tagesruhezeit, mehrere Fahrer, bereits gelenkte Zeit vor der Tour. **Die Planung ersetzt nicht die Auswertung des Fahrtenschreibers**
  und ist vor dem Einsatz fachlich zu prüfen (Steuerberater/Verkehrsrecht, Verband).

## Offene fachliche Punkte

- **Fahrzeit abgleichen:** Das Lkw-Profil rechnet eher langsamer als ein Reisebus (100 km/h auf der Autobahn erlaubt). Mit echten Fahrten
  vergleichen und `ROUTING_DURATION_FACTOR` setzen (Fahrplan, Verifikation).
- Mautkosten kommen nicht aus dem Dienst; sie gehören zur Kalkulation (Phase 6).
- Ausland: Lenkzeitregeln gelten EU-weit, Maut, Sonntagsfahrverbote und Umweltzonen nicht berücksichtigt.

## Gespeicherte Orte (`places`) {#gespeicherte-orte}

**Grundsatz:** Koordinaten stehen **nie** in den Fachtabellen und werden **nie** von dort direkt beim Adressdienst angefragt. Jeder Datensatz mit Adresse, der
in einer Geo-Funktion vorkommen kann (Betriebshof, später Kundenadresse, Haltestelle, Reiseziel), verweist mit `place_id` auf die zentrale Tabelle `places`.

- **Tabelle `places`:** Suchtext (`query`), Schlüssel `lookup_key` (sha1 des Textes in Kleinschreibung ohne doppelte Leerzeichen, eindeutig), Ortsname (`label`), `lat`, `lon`,
  PLZ, Ort, Land, `source` (welcher Dienst, aus der Klasse des `GeocodingProvider`) und `geocoded_at`. Orte werden nur angelegt, nie geändert: Neue Adresse, neuer Ort.
- **Dienst `App\Services\PlaceResolver`:** DIE Stelle, die den Adressdienst für gespeicherte Datensätze anruft. `resolve($text)` liefert den Ort aus der Datenbank und fragt nur bei
  einer unbekannten Adresse (auch von einem anderen Datensatz bekannt zählt). `assign($datensatz)` setzt `place_id` und fragt nur, wenn die Adresse neu oder geändert ist.
- **Baustein `HasPlace`** am Modell: Beziehung `place()`, `needsPlace()`; das Modell nennt `placeFields()` (Felder der Adresse) und `placeQuery()` (Suchtext). `place_id` gehört nie in `$fillable`.
- **Neue Tabelle anbinden:** Spalte `place_id` (uuid, leer erlaubt, Verweis auf `places`), `use HasPlace`, beim Speichern `PlaceResolver::assign()` aufrufen, `place` mitladen.
  Beispiel: `Depot` und `DepotController`. Fehler des Dienstes über `RespondsToRoutingFailure` (422 am Ort, 424 am Dienst).
- **Nicht gespeichert:** Die Halte der Streckenplanung (Von, Über, Nach, „Anderer Ort“) laufen weiter über `/geocode` mit den Zwischenspeichern; sie sind flüchtige Eingaben.
- **Offen:** Bestehende Adressen (Kunden, Fahrer) sind noch nicht angebunden. Ein Befehl zum Nachtragen für alle Datensätze ohne `place_id` fehlt noch.

## Nächste Schritte

1. ~~Oberfläche~~ gebaut (siehe „Oberfläche“); offen: Kachelanbieter wählen, im Browser prüfen.
2. ~~Betriebshöfe~~ gebaut (mehrere je Firma, Standard, gespeicherter Ort; siehe „Gespeicherte Orte“).
3. Tour speichern (UUID, Halte, Ergebnis eingefroren) als Kalkulationsbasis; Verknüpfung mit Angebot (Phase 6).

## Tests

`tests/Unit/DrivingTimePlannerTest.php` (Lenk-/Ruhezeiten, reine Rechnung) und `tests/Feature/RoutePlanningTest.php` (Rechte, Adresssuche,
Tourberechnung, Fehler des Dienstes, Schlüssel nie in Adresse oder Meldung). Der Routing-Dienst wird nie wirklich angerufen (`Http::fake`).

## Oberfläche

Seite `/streckenplanung` (Menü „Planung“, nur Admin und Disponent), Hilfethema `strecken.md`.

- `pages/route-planning/`: Seite (Signale, keine Formular-Klasse), reine Hilfsfunktionen in `route-planning-view.ts` (Zeitformate, Halte
  verschieben, Anfrage bauen) mit Testfällen in `route-planning-view.spec.ts` (lauffähig mit `ng test` bzw. ohne Browser per Hilfsskript).
- `shared/map.component.ts`: Kartenbaustein mit Leaflet (nummerierte Markierungen als HTML-Symbole, Linienzug, Texte nur als Text).
- `core/route.service.ts`: `geocode()` und `calculate()`; `VehicleService.choices()` (neu: `GET /api/vehicles/choices`, alle aktiven Fahrzeuge ohne Seiten).
- Eingabe **Von – Über – Nach** (2026-11-22 vereinfacht auf Wunsch des Nutzers): je Ort ein Suchfeld (`place-search.component.ts`), Treffer direkt anklicken; Suche 0,5 s nach dem Tippen (ab 3 Zeichen) oder mit Enter, Treffer mit `<app-highlight>` markiert, gleiche Suchen kommen aus dem Zwischenspeicher. Ein gewählter Ort bleibt ein normales Eingabefeld: Klick markiert den Text, Tippen ersetzt ihn und startet die Suche neu (kein gesperrtes Feld). Betriebshof, Aufenthalte und lange Tage stehen eingeklappt unter „Weitere Angaben“. Marker: A = Von, Zahlen = Über, Z = Nach, H = Betriebshof. Die Halteliste baut `buildStops()` (mit Tests).
- Der Betriebshof kommt aus den Einstellungen (Auswahl, Standard vorgewählt) oder als „Anderer Ort“ aus einem Textfeld. Ändert sich die Eingabe nach einer Berechnung, gilt das Ergebnis als „veraltet“.
- **Kartenkacheln:** `core/map-config.ts` nutzt vorerst `tile.openstreetmap.org` (nur zum Entwickeln und Ausprobieren). **Vor dem Produktivbetrieb
  Anbieter wählen** und URL und Quellenangabe dort eintragen.
- Im Browser mit erfundenen Antworten geprüft; nach dem Einbau von Leaflet `ng serve` neu starten (`angular.json` bindet das CSS ein).

## Erfahrungen mit dem echten Dienst (2026-11-22)

- Direktionsanfrage: `Accept: application/geo+json` ist Pflicht (sonst 406, Code 2007). `instructions` und `geometry_simplify` **nicht** setzen: ohne Anweisungen
  fehlen die Abschnitte (`segments`), die Leerfahrten und Zeitplan brauchen. Der Linienzug wird in `OpenRouteService::thin()` auf höchstens 1500 Punkte ausgedünnt.
- Fehlercodes des Dienstes: 2010 = Ort nicht auf einer Straße, 2009 = keine Route zwischen den Orten (mit Fahrzeugmaßen oft eine zu niedrige Unterführung
  oder Gewichtsgrenze), 2004 = Anfrage zu groß, 2007 = falscher Medientyp.
- Ortssuche (Pelias): trifft Namen aus OpenStreetMap, „Ulm Hbf“ statt „Ulm Hauptbahnhof“; Länderfilter nicht gesetzt wegen Auslandstouren.
- Beispiel Ansbach → Ulm → München → Ansbach (Setra S 515 HD, 3,65 × 2,55 × 12 m, 18 t): 501 km, 7:02 Std. reine Fahrzeit (Ø 71 km/h).
- **Zwischenspeicher:** Es werden nur einfache Arrays abgelegt, nie Objekte. Laravel gibt eigene Klassen beim Zurücklesen nicht frei (`__PHP_Incomplete_Class`):
  Die erste Suche ging, jede gleiche zweite gab einen Serverfehler. Die Schlüssel tragen eine Formatversion (`ors:v2:…`); ändert sich das Abgelegte, Version erhöhen.
  Der Test mit dem echten Dateispeicher (`test_wiederholte_anfragen_funktionieren_mit_einem_serialisierenden_speicher`) hält das fest; der Test-Speicher `array` zeigt den Fehler nicht.

## Lizenz und Nutzungsbedingungen von openrouteservice (gelesen am 2026-11-22)

Quelle: `https://account.heigit.org/info/plans` und `https://account.heigit.org/info/tos` (HeiGIT gGmbH). **Die Bedingungen können sich jederzeit ändern** (sie gelten sofort
nach Veröffentlichung); vor dem Produktivbetrieb erneut lesen. Das ist keine Rechtsberatung.

- **Tarife:** *Standard* kostenlos „für alle“: Directions 2000 Anfragen/Tag und 40/Minute, Geocoding 3000/Tag und 100/Minute. *Collaborative* (höhere Limits) nur für
  humanitäre, akademische, staatliche oder gemeinnützige Anwendungen, für uns nicht passend. *On-Premise* (eigene Installation): keine Grenzen, aber ein eigener Server
  (auf dem Mittwald-Shared-Hosting nicht möglich). Einen bezahlten Tarif mit Zusagen gibt es dort nicht. Alle Firmen von BusTotal teilen sich heute **einen** Schlüssel und diese Limits.
- **Gewerblich:** Ein Verbot gewerblicher Nutzung steht in den Bedingungen nicht, ebenso wenig eine ausdrückliche Erlaubnis. „Use by an Entity“ regelt die Nutzung durch Firmen.
  Keine Gewährleistung, keine Haftung für Richtigkeit der Routen, kein Zugesagter Betrieb. Wiederholtes Überschreiten der Limits kann zur Sperrung des Kontos führen.
- **Personenbezogene Daten:** Unter „Prohibited Conduct“ steht ausdrücklich: *Transmit personal data (apart from the data required by the HeiGIT account management)*.
  **Die Ortssuche sendet den Suchtext an den Dienst.** Gibt jemand die Abholadresse einer Privatperson ein, ist das ein personenbezogenes Datum. Das ist ein Widerspruch zu den
  Bedingungen und eine Datenschutzfrage (Auftragsverarbeitung). **Vor dem Einsatz mit echten Kundenadressen klären** (schriftlich bei HeiGIT, Datenschutzbeauftragte/r) oder auf einen
  Dienst mit Auftragsverarbeitungsvertrag bzw. auf eine eigene Installation wechseln.
- **Quellenangabe Pflicht:** „© openrouteservice by HeiGIT | Data from OpenStreetMap“ (und OSM bei der Ortssuche). Steht jetzt in der Kartenzeile (`core/map-config.ts`); in ausgedruckten
  oder exportierten Ergebnissen später ebenfalls nötig.
- **Lizenz der Ergebnisse:** „Results obtained from openrouteservice in any context are licensed under CC-BY-SA 4.0.“ (Weitergabe unter gleichen Bedingungen). Was das für
  gespeicherte Touren, Angebote und Rechnungen bedeutet (Phase 5/6: „Tour als Kalkulationsbasis speichern“), muss **vor** dem Speichern juristisch geklärt werden.
  Die Daten der Ortssuche stammen aus OpenStreetMap (ODbL), OpenAddresses, GeoNames und Who's On First.
- **Alternativen:** Dank der Schnittstellen (`RoutingProvider`, `GeocodingProvider`) ist ein Wechsel eine neue Klasse: eigene ORS-Installation (On-Premise), GraphHopper, oder ein
  kommerzieller Anbieter mit Vertrag. Kostenpflichtige Anbieter haben meist Auftragsverarbeitung und Zusagen; Kosten und Bedingungen dort ebenfalls lesen.

**Zur Postleitzahl:** Der Dienst wertet sie aus, wenn die Eingabe eine Adresse ist: „Marktplatz 1, 88400 Biberach an der Riß“ und „Hauptstraße 5, 91522 Ansbach“ treffen exakt
(mit PLZ). Nur bei einem unvollständigen Ortsnamen („88400 biberach“) richten sich die Treffer allein nach dem Ortsnamen; „Biberach an der Riß 88400“ trifft eindeutig.
