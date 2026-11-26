# Fachlicher Workflow: Reisevorhaben, Fahrtauftrag, Rückerfassung, Rechnung

Stand: 2026-11-21 (Konzept, **noch nichts davon gebaut**), **ergänzt 2026-11-22 um die Auswirkungen der Recherche (Abschnitt 7) und die festgelegte Reihenfolge (Abschnitt 8)**. Festgehalten nach Absprache mit dem Auftraggeber. Verbindliche Grundlage für den Umsetzungsplan der Phasen 5, 6 und 7 in `docs/FAHRPLAN.md`. **Steuerliche Festlegungen stehen in `docs/fachlich/loesungskonzept-abrechnung.md`** (sie gehen bei Abweichungen vor), die Recherche in den `*-ergebnisse.md`. Wo dieser Text überholt ist, steht es in Abschnitt 7.

## 1. Leitgedanken
1. **Strecke ist nicht Fahrzeug.** Eine Strecke wird mit einem **Fahrzeugtyp** (z. B. Doppeldecker, Kleinbus) und einer Anzahl geplant. Das konkrete Fahrzeug und die Fahrer wählt der Disponent kurz vor der Fahrt.
2. **Ein Angebot kann mehrere Strecken und mehrere Fahrzeuge enthalten** (Beispiel Wien: Doppeldecker und Kleinbus).
3. **Pro Fahrzeug ein Fahrtauftrag.** Eine Strecke mit zwei Bussen ergibt zwei Fahrtaufträge.
4. **Der Fahrer ergänzt unterwegs** Zusatzleistungen (z. B. Sonderfahrten vor Ort) und unvorhergesehene Kosten (z. B. Maut) und notiert den **km-Stand bei Grenzein- und Grenzaustritt**.
5. **Zwei Fahrer auf einem Fahrzeug** sind bei Langstrecken möglich.
6. **Die Rechnung entsteht aus der Rückerfassung** der Fahrtaufträge, nicht direkt aus der Auftragsbestätigung. Die AB trägt einen **vorläufigen** Preis.
7. **Tourdaten werden zweifach gespeichert:** als wiederverwendbares **Tour-Archiv** (Nachschlagen für weitere Angebote) und als **eigene Kopie** (`journey_sections.tour_data`), die mit dem Angebot an der Strecke der Reise entsteht; daraus bildet das Festschreiben den **unveränderlichen Schnappschuss am Beleg** (Dokumentation, Beschreibung in Angebot und Auftrag). Alles in der Belegkette arbeitet mit der Kopie, **das Tour-Archiv ist nur noch Vorlage für künftige Angebote und darf deshalb frei geändert und gelöscht werden** (Entscheidung 2026-11-25; der Verweis `trip_id` der Strecke wird beim Löschen leer). Alle Orte sind geocodiert und liegen in der zentralen Tabelle `places` (kein erneutes Geocodieren, siehe `docs/STRECKENPLANUNG.md`, Gespeicherte Orte).

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
- **Die Reise ist der Ort der Planung (Entscheidung 2026-11-25):** Man legt sie von Hand an („Neue Reise planen“: Name, Kunde) und fügt **Strecke für Strecke** hinzu (Hinfahrt, Fahrten vor Ort, Rückfahrt; Quelle: in der Streckenplanung berechnet, Tour aus dem Archiv oder eigene Angaben, je mit Fahrzeugtyp und Anzahl, Datum und Zuschlag). Das **Angebot entsteht aus der Reise** („Angebot erstellen“) und wird bei Änderungen an einer Strecke **je Strecke abgeglichen** („Angebot aktualisieren", solange es ein Entwurf ist; Positionen unveränderter Strecken bleiben unberührt, `journey_sections.priced_signature` erkennt geänderte Strecken). Der **Kurzweg** bleibt: In der Streckenplanung legt „Angebot erstellen“ für eine einzelne Fahrt Reise und Angebot in einem Schritt an (`manual = false`; diese Reise verschwindet mit ihrem einzigen Entwurf, eine von Hand geplante nicht). Strecken werden **nicht mehr im Beleg** hinzugefügt. Code: `JourneyPlanner`, `JourneyController`, `QuoteService::linesForSection`.
- **Fahrtauftrag und Sammelrechnung funktionieren auch ohne Reisevorhaben** (Werksverkehr).
- Abschnitte können einzeln geändert, abgerechnet oder storniert werden (Rückfahrt entfällt, Fahrten vor Ort kommen hinzu).
- Belege (Angebot, AB, Anzahlung, Rechnung) hängen am Reisevorhaben und verweisen auf Abschnitte. Die Beleg-Kette über `source_id` bleibt bestehen, das Vorhaben ist die sichtbare Klammer darüber.
- Die **Marge** (§ 25, falls zutreffend) wird **je Besteller und Reise** gerechnet (nicht pauschal je Vorhaben, siehe Abschnitt 7).

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
- **Auslandsanteil** (§ 3b UStG): Inlandsanteil der Beförderung nach **Streckenlängen** aus den Kilometerständen an der Grenze (**nur Kilometer**, keine Zeit). Festgelegt in `loesungskonzept-abrechnung.md` Abschnitt 2.2.
- **§ 25-Marge** (Entgelt minus Vorleistungen, je Besteller und Reise) für Reisen mit eingekauften Leistungen. **Vorleistungen** in eigener Tabelle `travel_costs`, **nicht** an den Positionen (Abschnitt 4 unten ist insoweit überholt). Bauplan: `paragraph-25-abrechnung.md`, Festlegungen: `loesungskonzept-abrechnung.md` Abschnitt 1.
- Auswertung je Zeitraum (PDF/Export): **Voranmeldungsbericht auf Basis der Zahlungseingänge**, Datenexport, DATEV.
- Strittige Punkte sind **einstellbar** und werden mit der **Freigabeliste** (Lösungskonzept Abschnitt 4) vom Kunden und seinem Berater bestätigt. Es wird nicht auf eine Klärung vorab gewartet.

## 4. Vorbild: Fahrtauftrag der Altlösung
Der Auftraggeber hat einen Bildschirm des Fahrtauftrags aus seiner alten Lösung gezeigt. Daraus übernehmen wir (nicht eins zu eins, aber inhaltlich):
- **Kopf:** Kunde, Ansprechpartner, Fahrt-Nr., Reisetermin von–bis, Fahrtziel, Personenzahl, Reiseart, Einsatzart, Status, Optionsdatum, „übernommen aus“ (Basisvorgang), Kennzeichen *Gedruckt / Erledigt / Nur Sammelrechnung*.
- **Reiter:** Leistungen (Ablauf), Fahrerinfo, Notizen, Zustiege, Einleitung, Abrechnung, Rechnungsdaten, Termine.
- **Ablauf:** Bereitstellung, Abfahrt Hinreise, Ankunft Ziel, Abfahrt und Ausstieg Rückreise, Ende am Betriebshof; Fahrzeugprofil und Fahrzeug, Fahrerprofil, Fahrer 1 und 2, Dispohinweis.
- **Positionen mit EK-Preis, Leistungsgeber und Marge** (Altlösung). **Überholt:** Die Vorleistungen für § 25 liegen in der Tabelle `travel_costs` je Reise, weil die Marge je Besteller und Reise zu bilden ist und die Lieferantenrechnungen nach der Kundenrechnung eintreffen. Ein Komfortfeld „EK-Preis“ an der Position kann später `travel_costs` füllen.
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
3. ~~Gilt § 25 nur für Pauschalreisen oder auch für reine Beförderung?~~ **Beantwortet (2026-11-22):** § 25 gilt nur bei einem Leistungsbündel mit mindestens einer eingekauften Reisevorleistung, reine Beförderung nie (UStAE 25.1 Abs. 2, 8, 9). Offen bleibt nur, **ob und wie viele Reisen der Kunde so verkauft** (Freigabeliste).
4. Werksverkehr: Preis je Fahrt aus einer Rahmenvereinbarung (Festpreis, km-Satz, Tarif) oder frei je Fahrtauftrag? Wer legt wiederkehrende Fahrtaufträge an (Vorlage mit Wochenmuster)?
5. Lizenz der Routendaten: Die Streckenlinie soll mitgespeichert werden. Für Orte ist das Speichern entschieden, für die Linie ist der Quellenhinweis zu klären (`docs/STRECKENPLANUNG.md`).

## 7. Auswirkungen der Recherche vom 2026-11-22 auf diesen Workflow
Geprüft wurde jede Stelle des Workflows gegen `loesungskonzept-abrechnung.md` und die `*-ergebnisse.md`. **Das Grundgerüst bleibt unverändert** (Reisevorhaben als optionaler Container, Fahrtauftrag je Bus, Rückerfassung, Rechnung aus Fahrtaufträgen, Tour zweifach gespeichert). Geändert oder ergänzt wird:

| Stelle | Bisher | Jetzt | Folge für den Bau |
|---|---|---|---|
| **Steuersatz** (A, B, G) | ein Satz für alle Positionen (heute `StandardPriceCalculator`, ein `tax_rate` aus den Einstellungen) | **Fahrtart** bestimmt den Satz (Charter 19 %, 7 % nur Linie und Schulfahrten bis 50 km), Warnung bei Abweichung | **Fahrtart** an Position und Fahrtauftrag, **vor** allem anderen nötig |
| **Zahlungseingang** (C, G) | „Anzahlung wie bisher“, jede festgeschriebene Anzahlung gilt als erhalten | Steuer auf Anzahlungen und Stornos hängt am **tatsächlichen Zahlungseingang** (§ 13; UStAE 13.5, 17.1) | **Baustein Zahlungseingang** (Tabelle, offene Posten) **früh**; Voraussetzung für den Voranmeldungsbericht |
| **AB mit vorläufigem Preis** (C, G) | Rechnung ersetzt die vorläufigen Positionen | bestätigt: AB ist keine Rechnung, die Rechnung über Ist-Werte ist die **erste** Rechnung; Anzahlungen mit Steuer abziehen (UStAE 14.1, 14.8) | keine Änderung am Ablauf |
| **Grenzdaten** (E, F, H) | „km-Stand und Zeit je Land“ | nur **Kilometer**; Teilstrecken müssen als *Betriebshof leer*, *Beförderung*, *Leerfahrt während Beförderung* gekennzeichnet sein, je Angabe die **Quelle** (Fahrer, Tachograph, Navigation) | Erfassungsteil und Rückerfassung erweitern |
| **Kosten und Zusatzleistungen** (E, F) | „Fremdkosten mit EK-Preis und Leistungsgeber“ | Kosten haben eine **Art**: Betriebskosten (Maut, Parken eigener Bus), Reisevorleistung (Hotel, Eintritt, Fähre für Gäste, Fremdbus in Reise), Weiterberechnung (Teil des Entgelts). Zusatzleistungen haben **Zahlungsart** (bar: Quittung nach § 33 UStDV, auf der Schlussrechnung „bereits bezahlt“) | Kostenart und Zahlungsart in der Rückerfassung |
| **Sammelrechnung** (G.2) | „Werksfahrt, 22 × 180 €“ | nur mit **Anlage** (Fahrtenliste mit Datum und Strecke), ausdrücklichem Verweis und **Leistungszeitraum** mit Beginn und Ende; Positionen **nach Steuersatz** getrennt (UStAE 14.5) | Anlage als Teil der Rechnung; Felder `service_date_from/to` am Beleg **sind schon da** |
| **Fahrzeug am Fahrtauftrag** (C, D) | eigenes Fahrzeug, Fahrer 1 und 2 | zusätzlich **Fremdunternehmer** (Subunternehmer-Bus) und **gestellter Bus**; Fremdbus innerhalb einer Reise ist **Reisevorleistung** | Fahrtauftrag darf ohne Fahrzeug-Stammdaten an einen Fremdunternehmer gehen, mit Kostenbezug zu `travel_costs` |
| **Reisevorhaben und Marge** (2) | Marge je Reisevorhaben | Marge **je Besteller und Reise**; **Kalkulation der Vorleistungen** ist Pflicht vor der ersten Anzahlung; Eigenleistung (Art der Position) wird herausgerechnet; Anzahlungs- und Schlussrechnung ohne Steuerausweis | Position bekommt **Art** (Eigenleistung/Reiseleistung); Vorhaben trägt Kalkulation und `margin_scheme` |
| **Storno** (Beleg) | Beleg „Gutschrift“ | Bezeichnung **Stornorechnung/Rechnungskorrektur** (Option), Steuerkorrektur mit der **Rückzahlung**; Stornogebühr einer Reise ist Schadensersatz | kleine Anpassung am bestehenden Beleg; Zahlungseingang nötig |
| **Fahrtauftrag als Beleg** (C bis F) | Status disponiert und erledigt | gilt für die **Aufbewahrung** (10 Jahre vorsorglich) und braucht **Änderungsprotokoll** der Rückerfassung; nach Abrechnung **gesperrt** | Löschsperre und Protokoll von Anfang an im Modell vorsehen |
| **E-Rechnung** | nicht im Workflow | Pflicht ab Umsätzen **2027** (Aussteller über 800.000 € Vorjahresumsatz) oder **2028**; gilt auch für Sammel- und § 25-Rechnungen | eigener Baustein **neben** dem Workflow; neue Rechnungsarten müssen ihn bedienen |
| **Offene Fragen** (Abschnitt 6) | Fragen 1 bis 5 | Frage 3 beantwortet; 1, 2, 4, 5 bleiben | keine |

## 8. Reihenfolge der Umsetzung (festgelegt 2026-11-22)
**Leitgedanken:** (1) zuerst, was **alle späteren Schritte** brauchen (Fahrtart, Zahlungseingang); (2) dann der **Rückgrat-Strang** Tour, Reisevorhaben, Fahrtauftrag, Rückerfassung, Rechnung, weil jeder Schritt den vorigen braucht; (3) **§ 25 zuletzt** im Strang, weil es nur für Reisen mit eingekauften Leistungen gilt, am meisten Fachentscheidungen braucht und eine Freigabe durch den Kunden verlangt; (4) die **E-Rechnung** als eigene Spur mit Termin; (5) jede Etappe endet mit Migration (von mir ausgeführt), Tests, Hilfetext und **Stand in `funktionen.md`** auf „Verfügbar“.

| Etappe | Inhalt | Braucht | Fertig, wenn |
|---|---|---|---|
| **0 Grundlagen im Belegwesen** | **0.1 Fahrtart und Steuersatz** (Enum, Voreinstellung, Warnung bei 7 % und Entfernung, `StandardPriceCalculator` setzt den Satz je Fahrtart); **0.2 Zahlungseingang** (Zahlungen am Beleg, offene Posten, Anzahlung bezahlt, Rückzahlung); **0.3 Belegfeinschliff** (Bezeichnung „Stornorechnung“ als Option, Anzahlung im PDF als Anzahlung erkennbar, Leistungszeitraum im PDF) | nichts | Angebot aus Tour setzt den Satz je Fahrtart, Zahlung erfassbar, Tests grün |
| **E (Spur) E-Rechnung** | Ausgabe XRechnung und ZUGFeRD aus Rechnung, Anzahlung, Storno; Firmeneinstellung „E-Rechnungspflicht ab“; Kundenmerkmale (Leitweg-ID, Unternehmer, Zustimmung); Originalformat archivieren | Etappe 0.1 (Steuersatz im Beleg) | **Termin hängt vom Vorjahresumsatz des Kunden ab:** über 800.000 € ⇒ **sofort nach Etappe 0, vor Etappe 1**; sonst bis Ende 2027, **vor Etappe 4**. Spätere Rechnungsarten (Sammel, § 25) werden mit ihrer Etappe angeschlossen |
| **1 Tour, Schnappschuss, Reisevorhaben** | Tour-Archiv (`trips`, Orte über `places`), Schnappschuss am Beleg beim Festschreiben, Reisevorhaben automatisch mit dem ersten Angebot, Abschnitte, **mehrere Strecken und Fahrzeugtypen** im Angebot (Positionen je Abschnitt) | Etappe 0.1 | Tour speichern, Angebot mit zwei Abschnitten und zwei Fahrzeugtypen, Vorhaben sichtbar |
| **2 Fahrtauftrag** | Beleg aus der AB je Abschnitt und Bus, Fahrzeugtyp, Zuordnung Fahrzeug, Fahrer 1 und 2 (Warnung bei Abwesenheit und Fristen), **Fremdunternehmer und gestellter Bus**, Fahrer-PDF ohne Preise, Status, Protokoll und Sperre | Etappe 1 | AB erzeugt Fahrtaufträge, Disponent weist zu, PDF druckbar |
| **Zwischenschritt: Reisen abschließen und absagen** (ergänzt 2026-11-26) | Reisenliste mit Ansicht (Laufende als Vorgabe, Abgeschlossen, Abgesagt, Alle), „Abgeschlossen“ abgeleitet, „Reise absagen“ mit Grund (Belege bleiben, offene Fahrtaufträge abgesagt); Einzelheiten in `docs/FAHRPLAN.md` | Etappe 2 | Liste zeigt nur laufende Reisen, eine abgesagte Reise lässt sich nicht mehr disponieren |
| **3 Rückerfassung** | Zeiten, Kilometerstände, **Grenzstände mit Quelle und Teilstreckenart**, Zusatzleistungen mit Zahlungsart, Kosten mit Art, Plausibilitätsprüfung, **Abrechnungsstatus** | Etappe 2 | Fahrtauftrag „erledigt“ mit plausiblen Angaben |
| **4 Rechnung aus Fahrtaufträgen** | Gesamtrechnung je Vorhaben, **Sammelrechnung mit Anlage** (Werksverkehr), Rahmenauftrag und wiederkehrende Fahrten, **Auslandsanteil nach § 3b** (Methode als Einstellung), Barzahlung als Quittung und „bereits bezahlt“, Anzahlungsabzug | Etappe 3, 0.2 | Rechnung aus einem Vorhaben und Monatsrechnung aus 20 Fahrten, Auslandsanteil korrekt, **Anzahlung und Endrechnung korrekt (Pflichtpunkte in `docs/FAHRPLAN.md`)** |
| **5 § 25** | Kalkulation je Reise, **`travel_costs`**, `MarginCalculator` **mit Tests zuerst**, Position-Art, `margin_scheme` und Rechnungstext, Anzahlung ohne Steuerausweis, Eigenleistung, Drittland, Storno, **Margenabrechnung** mit Abschluss | Etappe 4, **Freigabeliste für § 25** vom Kunden | Beispielrechnungen aus `ustae-25-ergebnisse.md` rechnen exakt, Abschluss sperrt |
| **6 Finanzamt und Betrieb** | **Voranmeldungsbericht** (nach Zahlungseingang), **Datenexport** für die Betriebsprüfung, DATEV-Export, **Löschsperre** mit Löschbefehl, Vorlage Verfahrensdokumentation, DSGVO-Anbindung | Etappen 0.2, 4, 5 | Bericht stimmt mit den Beispielen überein |
| **7 Disposition** (Phase 7) | Plantafel, Fahrer-Zeitkonto, Konfliktprüfung | Etappe 2 | unabhängig, kann **vorgezogen** werden, wenn der Kunde sie vor der Rechnung braucht |

**Offene Entscheidungen des Auftraggebers, die diese Reihenfolge ändern können:**
1. **Vorjahresumsatz über 800.000 €?** Ja: E-Rechnung vorziehen (Spur E vor Etappe 1).
2. **Verkauft der Kunde Reisen mit eingekauften Leistungen?** Ja und häufig: Etappe 4 und 5 **eng hintereinander** bauen (Etappe 5 braucht die Rechnung aus Etappe 4), die Freigabeliste für § 25 **früh** einholen. Nein: Etappe 5 kann hinter Etappe 6 rücken.
3. **Wie wichtig ist der Werksverkehr?** Hoch: Sammelrechnung, Rahmenauftrag und Fahrtauftrag **ohne Reisevorhaben** als eigener Zwischenschritt nach Etappe 2 (Etappe 3 und 4 in verkürzter Form für Werksfahrten).
4. **Fahrer-App oder Papier** für die Erfassung (Offene Frage 2): bestimmt nur das Datenmodell, nicht die Reihenfolge.
