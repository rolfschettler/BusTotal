# Fachlicher Workflow: Reisevorhaben, Fahrtauftrag, Rückerfassung, Rechnung

Stand: 2026-11-21 (Konzept, **noch nichts davon gebaut**). Festgehalten nach Absprache mit dem Auftraggeber. Verbindliche Grundlage für den Umsetzungsplan der Phasen 5, 6 und 7 in `docs/FAHRPLAN.md`. Steuerliche Aussagen sind **nicht** geprüft, offene Fragen stehen am Ende und in `docs/fachlich/belegwesen.md` (Abschnitt 8).

## 1. Leitgedanken
1. **Strecke ist nicht Fahrzeug.** Eine Strecke wird mit einem **Fahrzeugtyp** (z. B. Doppeldecker, Kleinbus) und einer Anzahl geplant. Das konkrete Fahrzeug und die Fahrer wählt der Disponent kurz vor der Fahrt.
2. **Ein Angebot kann mehrere Strecken und mehrere Fahrzeuge enthalten** (Beispiel Wien: Doppeldecker und Kleinbus).
3. **Pro Fahrzeug ein Fahrtauftrag.** Eine Strecke mit zwei Bussen ergibt zwei Fahrtaufträge.
4. **Der Fahrer ergänzt unterwegs** Zusatzleistungen (z. B. Sonderfahrten vor Ort) und unvorhergesehene Kosten (z. B. Maut) und notiert den **km-Stand bei Grenzein- und Grenzaustritt**.
5. **Zwei Fahrer auf einem Fahrzeug** sind bei Langstrecken möglich.
6. **Die Rechnung entsteht aus der Rückerfassung** der Fahrtaufträge, nicht direkt aus der Auftragsbestätigung. Die AB trägt einen **vorläufigen** Preis.
7. **Tourdaten werden zweifach gespeichert:** als wiederverwendbares **Tour-Archiv** (Nachschlagen für weitere Angebote) und als **unveränderlicher Schnappschuss am Beleg** (Dokumentation, Beschreibung in Angebot und Auftrag). Alle Orte sind geocodiert und liegen in der zentralen Tabelle `places` (kein erneutes Geocodieren, siehe `docs/STRECKENPLANUNG.md`, Gespeicherte Orte).

## 2. Oberbegriff: Reisevorhaben
Eine Reise ist ein **Reisevorhaben**. Es fasst Kunde, Angebot, Auftrag, Fahrtaufträge, Rückerfassung, Rechnungen und Auswertung zusammen. Es besteht aus **Abschnitten**:
- **Hinfahrt**
- **Fahrten vor Ort am Reiseziel** (Ausflüge, Transfers, Sonderfahrten)
- **Rückfahrt**

Jeder Abschnitt hat eine eigene Strecke und einen oder mehrere Busse (je Fahrzeugtyp):

`Reisevorhaben → Abschnitte (Strecke) → Fahrzeugbedarf (Typ × Anzahl) → je Bus ein Fahrtauftrag`

**Entscheidung: optionaler, schlanker Container.**
- Das Reisevorhaben hat nur Kopfdaten (Kunde, Titel, Zeitraum), die Abschnitte und Verweise auf Belege und Fahrtaufträge.
- **Summen, Marge und Status werden abgeleitet**, nie doppelt gespeichert. Keine eigene Preis- oder Steuerlogik.
- Es entsteht **automatisch** mit dem ersten Angebot aus der Streckenplanung (Name der Reise), niemand legt es von Hand an.
- **Fahrtauftrag und Sammelrechnung funktionieren auch ohne Reisevorhaben** (Werksverkehr).
- Abschnitte können einzeln geändert, abgerechnet oder storniert werden (Rückfahrt entfällt, Fahrten vor Ort kommen hinzu).
- Belege (Angebot, AB, Anzahlung, Rechnung) hängen am Reisevorhaben und verweisen auf Abschnitte. Die Beleg-Kette über `source_id` bleibt bestehen, das Vorhaben ist die sichtbare Klammer darüber.
- Die **Marge** (§ 25, falls zutreffend) wird je Reisevorhaben gerechnet.

## 3. Ablauf in Phasen

### A. Planen (Streckenplanung)
- Der Nutzer plant eine oder mehrere Strecken und wählt je Strecke **Fahrzeugtyp und Anzahl** (Bedarf, kein Fahrzeug).
- „Tour speichern“ legt die Tour im **Tour-Archiv** ab.
- Ergebnis: Kalkulation je Fahrzeugtyp (Kilometer, Lenkzeit, Tage, Zuschläge).

### B. Angebot
- Einstieg aus der Streckenplanung („Angebot erstellen“) oder direkt im Beleg („Tour aus Archiv wählen“).
- Positionen je Strecke und Fahrzeugtyp, Fahrtbeschreibung aus dem Tour-Schnappschuss.
- Beim Festschreiben friert der **Schnappschuss** die Tourdaten am Beleg ein.

### C. Auftragsbestätigung mit vorläufigem Preis
- Aus dem Angebot, Preis ausdrücklich vorläufig, Abrechnung nach tatsächlichem Ablauf. Festpreis optional je Position.
- Optional **Anzahlung** wie bisher.
- Mit der AB entstehen die **Fahrtaufträge als Entwürfe**: je Strecke und Fahrzeug einer, Fahrzeugtyp vorbelegt, Fahrzeug und Fahrer offen. Stationen und Zeiten kommen aus dem Schnappschuss.

### D. Disposition (kurz vor der Fahrt)
- Der Disponent weist **Fahrzeug, Fahrer 1 und ggf. Fahrer 2** zu und prüft Verfügbarkeit, Lenk- und Ruhezeiten (`docs/fachlich/lenk-ruhezeiten.md`).
- Weicht die Zahl der Fahrzeuge ab (zusätzlicher Bus), wird das sichtbar und als Änderung an der AB gemeldet (Nachtrag).
- Der Fahrtauftrag wird ausgegeben (Druckvorschau, **Fahrer-Fassung ohne Preise**) und gilt als „disponiert“.

### E. Durchführung (Fahrer im Fahrzeug)
Der Fahrtauftrag ist zugleich Erfassungsblatt. Der Fahrer notiert:
- **Zeiten und km-Stand** (Start, Ende, je Station),
- **Grenzübertritte** (Land, Ein-/Austritt, km-Stand, Uhrzeit),
- **Zusatzleistungen** (Sonderfahrt, Wartezeit) mit Menge und Preis,
- **Unvorhergesehene Kosten** (Maut, Parken, Fähre) mit Betrag und Beleg,
- Notizen und Besonderheiten.

Zunächst Papier und Rückerfassung im Büro. Eine Erfassung per App oder Tablet kommt später, das Datenmodell soll sie nicht verbauen.

### F. Rückerfassung
- Das Büro erfasst die Angaben je Fahrtauftrag nach, Status „erledigt“.
- Plausibilitätsprüfung: km-Stand, Zeiten, Grenzdaten lückenlos.
- Aus den Grenzstandsangaben werden **Kilometer und Zeit je Land** abgeleitet.
- Zusatzleistungen und Kosten gehen als Positionen in die Abrechnung, Fremdkosten mit EK-Preis und Leistungsgeber.

### G. Rechnung
Rechnung entsteht aus den zurückerfassten Fahrtaufträgen: vorläufige AB-Positionen werden durch Ist-Werte ersetzt oder ergänzt, Anzahlungen werden abgezogen. **Zwei Wege:**
1. **Gesamtrechnung je Reisevorhaben:** Alle zurückerfassten Fahrtaufträge werden automatisch zusammengefasst, gruppiert nach Abschnitt (Hinfahrt, vor Ort, Rückfahrt). Auf Wunsch Teilrechnung einzelner Abschnitte.
2. **Sammelrechnung über beliebige Fahrtaufträge** eines Kunden, auch ohne gemeinsames Reisevorhaben. Beispiel: tägliche Werksfahrten, Abrechnung am Monatsende. Der Nutzer wählt Kunde und Zeitraum, das System schlägt alle zurückerfassten, noch nicht abgerechneten Fahrtaufträge vor (abwählbar). Gruppierung nach Tag/Fahrt oder zusammengefasst nach Leistung („Werksfahrt, 22 × 180 €“).

Weitere Regeln:
- Jeder Fahrtauftrag trägt einen **Abrechnungsstatus** (offen / abgerechnet mit Rechnung X). Nichts wird doppelt oder gar nicht abgerechnet.
- Das Kennzeichen „Nur Sammelrechnung“ am Fahrtauftrag schließt ihn von der Einzelrechnung aus.
- Regelmäßige Fahrten (Werksverkehr) brauchen kein Angebot je Fahrt: **Rahmenauftrag / wiederkehrende Fahrtaufträge** als Einstieg ohne Reisevorhaben.
- Im Beleg zeigt eine Fortschrittsleiste *Angebot → Auftrag → Fahrtaufträge → Rückerfassung → Rechnung* den Stand, eine Schaltfläche „Nächster Schritt“ führt weiter.

### H. Abrechnung für das Finanzamt
- **Auslandsanteil** (Kilometer und Zeit je Land aus den Grenzdaten) für die steuerliche Behandlung der Beförderung (Steuerberater-Frage 2).
- **§ 25-Marge** (Entgelt minus Vorleistungen) für Reiseleistungen, falls es Pauschalreisen gibt (Steuerberater-Frage 3). Voraussetzung: EK-Preis und Leistungsgeber an den Positionen.
- Auswertung je Zeitraum (PDF/Export).
- **Zu klären:** Die Grenzdaten liefern den Auslandsanteil. Ob und wie sie in die § 25-Rechnung eingehen, muss der Steuerberater bestätigen, bevor gerechnet wird.

## 4. Vorbild: Fahrtauftrag der Altlösung
Der Auftraggeber hat einen Bildschirm des Fahrtauftrags aus seiner alten Lösung gezeigt. Daraus übernehmen wir (nicht eins zu eins, aber inhaltlich):
- **Kopf:** Kunde, Ansprechpartner, Fahrt-Nr., Reisetermin von–bis, Fahrtziel, Personenzahl, Reiseart, Einsatzart, Status, Optionsdatum, „übernommen aus“ (Basisvorgang), Kennzeichen *Gedruckt / Erledigt / Nur Sammelrechnung*.
- **Reiter:** Leistungen (Ablauf), Fahrerinfo, Notizen, Zustiege, Einleitung, Abrechnung, Rechnungsdaten, Termine.
- **Ablauf:** Bereitstellung, Abfahrt Hinreise, Ankunft Ziel, Abfahrt und Ausstieg Rückreise, Ende am Betriebshof; Fahrzeugprofil und Fahrzeug, Fahrerprofil, Fahrer 1 und 2, Dispohinweis.
- **Positionen mit EK-Preis, Leistungsgeber und Marge.** Daher gehören die Vorleistungen für § 25 an die Positionen, nicht in eine getrennte Kostentabelle.
- Das Fahrer-PDF blendet die Preise aus. Plantafel und „in Dispo“ gehören zu Phase 7.

## 5. Folgen für die Bausteine
| Baustein | Bedeutung |
|---|---|
| Reisevorhaben mit Abschnitten | optionale Klammer, Summen und Status abgeleitet |
| Tour-Archiv + Schnappschuss am Beleg | zweifache Speicherung, Orte über `places` |
| Angebot mit mehreren Strecken und Fahrzeugtypen | Position gehört zu Abschnitt und Fahrzeugtyp |
| Fahrtauftrag (mehrere je Auftrag) | Fahrzeugtyp → Fahrzeug, Fahrer 1/2, Status disponiert/erledigt |
| Erfassungsteil am Fahrtauftrag | Zeiten, km, Grenzübertritte, Zusatzleistungen, Kosten |
| Rückerfassung | Plausibilitätsprüfung, Übernahme in die Rechnung |
| Rechnung aus Fahrtaufträgen | Ist-Werte, Anzahlungsabzug, Gesamt- und Sammelrechnung |
| Abrechnungsstatus am Fahrtauftrag | verhindert Doppelabrechnung |
| Rahmenauftrag / wiederkehrende Fahrten | Werksverkehr ohne Reisevorhaben |
| Auslandsanteil / § 25 | Auswertung für das Finanzamt |

## 6. Offene Fragen
1. Wird die AB bei einer Änderung (zusätzlicher Bus) neu versioniert oder gibt es einen Nachtragsbeleg?
2. Soll die Rückerfassung später über Fahrer-App oder Tablet laufen (Datenmodell vorsehen)?
3. Gilt § 25 nur für Pauschalreisen oder auch für reine Beförderung? Das entscheidet, ob der Margenteil nötig ist.
4. Werksverkehr: Preis je Fahrt aus einer Rahmenvereinbarung (Festpreis, km-Satz, Tarif) oder frei je Fahrtauftrag? Wer legt wiederkehrende Fahrtaufträge an (Vorlage mit Wochenmuster)?
5. Lizenz der Routendaten: Die Streckenlinie soll mitgespeichert werden. Für Orte ist das Speichern entschieden, für die Linie ist der Quellenhinweis zu klären (`docs/STRECKENPLANUNG.md`).

**Vorgeschlagene Reihenfolge der Umsetzung:** Tour speichern → mehrere Strecken im Angebot → Reisevorhaben → Fahrtaufträge → Rückerfassung → Rechnung aus Fahrtaufträgen → Auslandsanteil und § 25.
