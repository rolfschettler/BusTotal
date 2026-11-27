# Plan Etappe 3: Rückerfassung

Stand 2026-11-27, **gebaut** (Annahmen vom Nutzer bestätigt). Grundlagen: `fachlich/workflow-reisevorhaben.md` (Abschnitte 3, 7, 8), `fachlich/loesungskonzept-abrechnung.md` (1.4, 2.2, 2.3).

## Ziel
Der Fahrer kommt mit dem Erfassungsblatt zurück. Das Büro tippt die Angaben ein und setzt den Fahrtauftrag auf **Erledigt**. Daraus entsteht in Etappe 4 die Rechnung.

## Ablauf
```
Disponiert ─┐
            ├─ Rückerfassung eintippen (Speichern, beliebig oft) ─ „Fahrt erledigt …“ ─> Erledigt
Ausgegeben ─┘                                                                              │
     ^──────────────────── „Erledigt zurücknehmen“ (mit Begründung) ──────────────────────┘
```

## Mit dem Nutzer geklärt
- Pflicht für „Erledigt“: nur **km-Stand Anfang und Ende**, dazu vollständige Grenzzeilen, wenn welche eingetragen sind. Alles andere freiwillig, Unstimmigkeiten als gelbe Hinweise.
- Erfasst werden **Disponierte und Ausgegebene**. Fehlt die Nummer, wird sie beim Erledigen vergeben.
- Korrektur über **„Erledigt zurücknehmen“** mit Begründung, solange nicht abgerechnet.
- Rechte: **Administrator, Disponent, Buchhaltung**.
- **Eigene Seite**, aufgebaut **wie das Papierblatt**; Zugang auch aus Fahrtauftragsliste und Reise, mit Filter „Noch zu erfassen“.

## Die Seite „Rückerfassung“
```
Rückerfassung FA-2026-00001 · München, 01.12.2026 · B-01 · Bauer, Stefan
✓ Disponiert › [● Ausgegeben] › ○ Erledigt
Nächster Schritt: Tippen Sie das Blatt ab. Für „Erledigt“ fehlen: km-Stand Ende.

Zeiten und Kilometer
Ereignis              Datum  Ort  Uhrzeit  km-Stand  Strecke bis hier
Abfahrt Betriebshof   …                              –
Abfahrt erster Halt   …                              leer ab Betriebshof
Ankunft Ziel          …                              mit Fahrgästen
Ende am Betriebshof   …                              leer zum Betriebshof
[+ Zeile]        Gefahren: 560 km (geplant 548 km)
Personen tatsächlich [  ]

Grenzübertritte   Land · km Einfahrt · km Ausfahrt · km im Ausland (gerechnet) · Angabe von
Zusatzleistungen  Leistung · Menge · Betrag brutto · bar / auf Rechnung · Steuersatz
Kosten            Art · Betrag brutto · Beleg-Nr. · an Kunden weiterberechnen
Notizen

[Speichern] [Zurück]                    [Fahrt erledigt …] [Abbrechen]
Erledigt:    [Zurück]                   [Erledigt zurücknehmen …]
```
Formular nach `ClaudeCodePatterns/EditFormPattern.md`. Datum und Ort sind wie auf dem Blatt vorbelegt (`TripOrderPdf::eventRows`). Zeilen zum Betriebshof gibt es nur, wenn die Strecke eine Leerfahrt dorthin hat.

## Regeln
**Abgelehnt** (nicht möglich): km-Stand Ende kleiner als Anfang; Grenze Ausfahrt kleiner als Einfahrt; „Erledigt“ vor dem Beginn der Fahrt; abgesagte oder abgerechnete Aufträge.

**Gelbe Hinweise** (nie eine Sperre): km-Stände oder Zeiten nicht aufsteigend; Grenzstände außerhalb von Anfang und Ende; gefahrene km weichen mehr als 15 % von der Planung ab; km-Stand liegt unter dem letzten Ende desselben Busses; Barzahlung ohne Betrag; bei Grenzzeilen „Ausländische Umsatzsteuer prüfen“.

**Vorbelegungen:**
- Steuersatz der Zusatzleistung aus der Fahrtart (`PricingSetting::taxRateFor`).
- Kostenart bestimmt „Reisevorleistung“: ja bei Hotel, Verpflegung, Eintritt, Fähre für Gäste, Reiseleiter, Fremdbus; nein bei Maut, Parken, Treibstoff, Reparatur.
- Grenzangaben „vom Fahrer“ (sonst Tachograph oder Navigation).

## Annahmen (bitte bestätigen)
1. Bei **Fremdunternehmern** sind die km-Stände keine Pflicht, weil sie oft nicht vorliegen.
2. „Erledigt zurücknehmen“ führt immer auf **Ausgegeben**; die Eingaben bleiben erhalten.
3. Das **Erfassungsblatt** (PDF) bekommt zusätzlich „Personen tatsächlich“ und bei den Zusatzleistungen eine Spalte „Betrag“.
4. Beträge nur in **Euro**.

## Nicht in Etappe 3
Rechnung, Quittung bei Barzahlung und Auslandsanteil (Etappe 4), Vorleistungen mit Lieferant und Belegdatei (Etappe 5), Fahrer-App.

## Bau in Schritten
1. **Datenmodell:** `trip_orders` um `actual_passengers`, `report_notes`, `completed_at`, `completed_by`; Tabellen `trip_order_times`, `trip_order_borders`, `trip_order_extras`, `trip_order_costs`; Index für „Noch zu erfassen“. Migration ausführen.
2. **Server:** Dienst `TripReportService` (speichern, Hinweise, erledigen, zurücknehmen) mit Protokoll; `GET/PUT /api/trip-orders/{id}/report`, `POST …/report/complete`, `POST …/report/reopen`. Nummernvergabe wie in `TripOrderService::issue`. Tests.
3. **Seite** `/fahrtauftraege/:id/rueckerfassung` mit Rückfrage vor „Erledigt“ (Kurzfassung der Angaben) und Fenster für die Begründung.
4. **Zugänge:** Taste im Fahrtauftrag, Ablaufleiste („Erledigt“ ohne „folgt“), Symbol und Filter in der Liste, Link in der Reise-Tabelle, Zähler „erledigt“.
5. **Erfassungsblatt**, Hilfe (`help/fahrten.md#rueckerfassung`, `funktionen.md`), `FAHRPLAN.md`.

Jeder Schritt endet mit Tests und Build. Browser-Prüfung nur auf Anweisung.
