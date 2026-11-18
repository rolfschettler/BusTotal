# Streckenplanung (Phase 5)

Stand: Grundlage im Backend fertig (Routing-Schnittstelle, Adresssuche, Tourberechnung mit Lenk- und Ruhezeiten).
**Noch nicht gebaut:** Oberfläche mit Karte, Speichern der Tour als Kalkulationsbasis (siehe „Nächste Schritte“).

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

**Kontingent:** Der kostenlose Tarif hat Tages- und Minutengrenzen. Gleiche Anfragen werden zwischengespeichert (Strecken 12 Stunden,
Ortssuche 7 Tage). Die Wege sind auf 60 Anfragen je Minute begrenzt. **Vor dem Produktivbetrieb** prüfen: Nutzungsbedingungen für
den gewerblichen Einsatz und das Kontingent im Verhältnis zur Zahl der Firmen (alle Firmen teilen sich einen Schlüssel).

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

## Nächste Schritte

1. Oberfläche: Seite „Streckenplanung“ mit Ortssuche, Halteliste (Reihenfolge, Aufenthalt), Fahrzeugwahl, Karte (Leaflet, Kachelanbieter wählen: nicht die
   openstreetmap.org-Kacheln produktiv), Ablauf-Tabelle mit Warnungen; Hilfethema und `data.help`-Eintrag.
2. Betriebshof als Einstellung der Firma (Adresse mit Koordinaten).
3. Tour speichern (UUID, Halte, Ergebnis eingefroren) als Kalkulationsbasis; Verknüpfung mit Angebot (Phase 6).

## Tests

`tests/Unit/DrivingTimePlannerTest.php` (Lenk-/Ruhezeiten, reine Rechnung) und `tests/Feature/RoutePlanningTest.php` (Rechte, Adresssuche,
Tourberechnung, Fehler des Dienstes, Schlüssel nie in Adresse oder Meldung). Der Routing-Dienst wird nie wirklich angerufen (`Http::fake`).
