# Ergebnisse der Recherche zum Anwendungserlass (UStAE) zu § 25 UStG

Stand: 2026-11-22. Gelesen wurden die **Abschnitte 25.1 bis 25.5 des UStAE** nach dem Abdruck von [steuerschroeder.de](https://www.steuerschroeder.de/steuergesetze/ustae/25.1) (Stand „Amtliche Umsatzsteuer-Handausgabe 2024“, Abschnitt 25.1 Abs. 12 neu gefasst durch BMF-Schreiben vom 20.12.2024). Der Abdruck ist **keine amtliche Quelle**; rechtsverbindlich ist nur die Veröffentlichung im Bundessteuerblatt. Der Server des BMF (usth.bundesfinanzministerium.de) war am 2026-11-22 nicht erreichbar (503), die Haufe-Fassung hinter einer Bezahlschranke. **Nicht geprüft:** Änderungen nach 2024. Vor dem Bau gegen die aktuelle Fassung des BMF abgleichen.

Kürzel: *A* = Abschnitt, *Abs.*, *S.* = Satz. Ergebnisse zu den Fragen aus `docs/fachlich/fragen-steuerberater.md` (Nummern dort).

## Frage 1: Wann gilt die Margenbesteuerung?
- **Leistungsbündel nötig** (A 25.1 Abs. 2 S. 1): § 25 setzt grundsätzlich ein Bündel von Einzelleistungen voraus, darunter mindestens eine Beförderungs- oder Beherbergungsleistung. **Kein** Leistungsbündel liegt vor, wenn der Unternehmer **nur An- und Abreise organisiert**; unerheblich ist, ob ein oder mehrere Beförderungsmittel eingesetzt werden (Abs. 2 S. 3 und 4).
- **Fall a** (eigener Bus, Hotel bucht der Verein): keine Reiseleistung, **reguläre Besteuerung der Beförderung**.
- **Fall b** (eigener Bus **und** eingekauftes Hotel): **gemischte Leistung** (A 25.1 Abs. 11). Die Beförderung mit dem eigenen Bus wird **nach den allgemeinen Vorschriften** besteuert, Beherbergung und Verpflegung nach § 25. Die Aufteilung folgt A 25.3 Abs. 2 (siehe Frage 2).
- **Fall c** (nur Fremdbus, kein Hotel, keine weiteren Leistungen): Nach Abs. 2 S. 3 und 4 **kein Leistungsbündel**, also **kein § 25**; die Rechnung des Subunternehmers wird regulär mit Vorsteuer behandelt **[abgeleitet, nicht ausdrücklich im Text]**. Wichtig: Abs. 8 S. 9 behandelt **Gestellungsverträge** (Mietbus mit Fahrer): Ein eigenes Beförderungsmittel kann auch vorliegen, wenn wir Weisungsbefugnis und Verantwortung haben.
- **Eigenleistung** (Abs. 8 S. 1): § 25 gilt nur bei Inanspruchnahme von **Reisevorleistungen**, nicht bei Eigenleistungen wie eigenem Bus, eigenem Hotel oder angestellten Reiseleitern. Abs. 8 S. 4: Abgrenzung nach den tatsächlichen Umständen, nicht aus Sicht des Reisenden. S. 5: volle Verantwortung allein macht noch keine Eigenleistung. S. 12: Betreuung durch angestellte Reiseleiter ist nicht margenbesteuert.
- **Fremdbus innerhalb einer Reise** (Abs. 8 S. 10 und 11): Ein Fremdunternehmer, der die Beförderung **im eigenen Namen und in eigener Verantwortung** ausführt, liefert eine **Reisevorleistung**.
- **Reisevorleistung** (Abs. 9 S. 1): Leistung eines Dritten, die dem Reisenden **unmittelbar** zugutekommt. Ausnahmen (nur mittelbar, **keine** Vorleistung): vermittelndes Reisebüro, **Reparatur eines Busses während der Reise** (Abs. 9 S. 4).

## Frage 2: Eigene Busfahrt in einer Reise mit eingekauften Leistungen
**Möglichkeit 2 ist richtig:** Die Eigenleistung wird **herausgerechnet** und regulär besteuert (A 25.3 Abs. 2).
- **Maßstab** (S. 1 und 2): grundsätzlich im **prozentualen Verhältnis der Einzelverkaufspreise**; ohne Einzelverkaufspreise nach den **entstandenen Kosten einschließlich Umsatzsteuer**. **Gemeinkosten und kalkulatorischer Unternehmerlohn bleiben außen vor** (S. 3).
- **Beispiel 1 des Erlasses** (Buspauschalreise, Preis 600 €, Einzelverkaufspreise Bus 130 €, Hotel und Verpflegung 500 €, Summe 630 €): Eigenleistung 600 × 130/630 = **123,81 €** brutto, Anteil § 25 = 476,19 €, Vorleistung 400 € brutto, Marge **76,19 €**, darin Steuer 12,16 €, Bemessungsgrundlage 64,03 €. Eigenleistung netto 104,04 € (19 %). Gesamtbemessungsgrundlage 168,07 €.
- **Beispiel 2** (ohne Einzelverkaufspreise, Kosten Bus 100 zu Hotel 400): Eigenleistung 20 % = 120 €, Anteil § 25 480 €, Marge 80 €, Steuer 12,77 €, Bemessungsgrundlage 67,23 €.
- **Folge für die Software:** Eigenleistung ist **eine Eingabe mit Maßstab** (Einzelverkaufspreise oder Kosten), kein Freitext. Der Steuersatz der Eigenleistung ergibt sich aus der Beförderungsart (Frage 11).

## Frage 3: Kunde ist eine Firma
- **Nicht abschließend gefunden.** Gefunden wurden: A 25.1 Abs. 1 S. 11 (Arbeitgeber, die Arbeitnehmern Reisen überlassen, erbringen Reiseleistungen) und A 25.3 Abs. 4 Nr. 4 (**Dienstreisen von Angestellten** und **Kundengeschenke** sind keine Leistungen nach § 25). Zum **Betriebsausflug eines Unternehmerkunden** steht in den gelesenen Abschnitten nichts. **Offen.**
- Hinweis: A 25.2 Abs. 7 verlangt bei **Abgabe an einen anderen Unternehmer** für sein Unternehmen die Angabe des **Umfangs der Drittlandsleistungen** in der Rechnung (§ 14 Abs. 4 Nr. 7). Das setzt voraus, dass auch **Unternehmerkunden** Margenrechnungen erhalten (**Weiterverkauf, Kettengeschäft**, A 25.1 Abs. 9 S. 3).

## Frage 4: Marge je Auftrag oder je Teilnehmer
- **Grundsatz** (A 25.3 Abs. 1 S. 1 und 2): Marge **je einzelner Reise**; im Beispiel **je Reiseteilnehmer**, **soweit nicht einzelne Leistungsempfänger im eigenen Namen für weitere Teilnehmer auftreten** (Beispiel 1 S. 5). Tritt der Besteller (Verein, Firma) **im eigenen Namen** für die Mitreisenden auf, ist **er** der Leistungsempfänger, die Marge wird dann je Besteller und Reise gebildet. Beispiel: 440 €/Person, 40 Personen, Bahn 3.200 € und Hotel 12.000 € brutto → Vorleistungen je Person 380 €, Marge **60 €**, Steuer **9,58 €**, Bemessungsgrundlage **50,42 €**.
- **Vereinfachung** (Abs. 1 S. 3 bis 5): Mehrere Reisen mit **einheitlichem Aufschlags- oder Kalkulationssatz** dürfen zusammengefasst werden. **Gilt dann für alle Reisen innerhalb eines Besteuerungszeitraums** (S. 5). Fest eingekaufte **Kontingente** (Übernachtungen, Beförderung) dürfen **voll** als Vorleistung angesetzt werden, auch wenn sie nicht abgerufen werden (S. 4).
- **Gesamtmarge für den Besteuerungszeitraum:** in der gelesenen Fassung **nicht vorgesehen**. Der Suchtreffer, der sie nennt („alle Reiseleistungen eines Besteuerungszeitraums in einer Summe“), stammt offenbar aus einer älteren Fassung. Der Text von 25.2 Abs. 4 handelt von der **Zielortregelung beim Flug**, nicht von der Gesamtmarge. **Für die Software: keine Gesamtmarge bauen.**

> **Ergänzung 2026-11-22 (Fachartikel Haufe, Juli 2026, bestätigt Frage 4 und 5):** „Einzelmargenermittlung seit VZ 2022, Gruppen- oder Globalmarge entfallen, negative Margen dürfen nicht mit positiven anderer Reisen verrechnet werden.“ Seit 18.12.2019 gilt § 25 auch für Reiseleistungen an Unternehmer (bestätigt Frage 3). Das BMF hat die Nichtbeanstandungsregelung für Drittlandsunternehmen mit Schreiben vom 28.04.2026 für Reiseleistungen bis 31.12.2027 verlängert (für uns unerheblich). **Die Vereinfachung** (Zusammenfassen bei einheitlichem Kalkulationssatz, A 25.3 Abs. 1 S. 3 bis 5) bleibt nach dem Erlasstext **bestehen**, der Artikel erwähnt sie nicht.

## Frage 5: Negative Marge
- **Vereinfachung** angewendet: negative Einzelmargen sind **nicht mit positiven verrechenbar** (A 25.3 Abs. 1 S. 6). Ohne Vereinfachung enthält der Text keine weitere Regel zur negativen Marge.
- **Vorsteuer:** Besteht für die Reiseleistung **keine Bemessungsgrundlage** (Marge ≤ 0), gibt es **trotzdem keinen** Vorsteuerabzug für die Reisevorleistungen (A 25.4 Abs. 2 S. 3).
- **Nicht abgerufene Kontingente:** Ohne Vereinfachung sind sie **keine** Reisevorleistung, der Vorsteuerabzug steht insoweit zu (Abs. 1 S. 7 und 8). Beispiel 2: 100 Zimmer, 7 Nächte, 35 € je Nacht, Zahlungspflicht 24.500 €; kalkuliert mit voller, tatsächlich 80 % Belegung: Vorleistung je Reise zunächst 245 €, tatsächlich 306,25 €, Margenminderung 61,25 € je Reise, insgesamt 4.900 €, darin Steuer 782,35 €, Bemessungsgrundlage −4.117,65 €. **Korrektur, sobald die Belegung bekannt ist**; eine **Einmalberichtigung** ist zulässig, wenn keine negativen Einzelmargen entstehen (S. 6 bis 8).

## Frage 6: Drittland
- **Ganz im Drittland** (A 25.2 Abs. 1): voll **steuerfrei**. **Ganz in der EU** (Abs. 2): voll **steuerpflichtig**. **Gemischt** (Abs. 3): nur **insoweit** steuerfrei, wie die Leistung im Drittland bewirkt wird. **Beförderung über beide Gebiete: Aufteilung nach den jeweils zurückgelegten Strecken** (Abs. 3 S. 2 und 3). **Damit sind die Kilometerstände an der Grenze die richtige Datengrundlage.**
- **Aufteilung der Marge** (A 25.3 Abs. 3, Beispiel 1: Preis 1.100 €, Vorleistungen 1.000 €, davon Hotel 75 % im Drittland, Flug 25 % mit 40 % Drittlandsstrecke): Marge 100 €. Steuerfrei: **75 €** (Hotel) und **10 €** (25 % × 40 % der Marge) = **85 €**; steuerpflichtig **15 €**, Steuer **2,40 €**, Bemessungsgrundlage **12,60 €**. Die Marge wird also **im Verhältnis der Vorleistungsanteile** aufgeteilt, wie wir vorgeschlagen hatten. Die Tabelle im Abdruck ist unübersichtlich; die Spaltenzuordnung ist aus dem Text abgeleitet.
- **Zielortregelung** (A 25.2 Abs. 4 bis 6): Vereinfachung **nur für Flug** (Zielort im Drittland → ganze Beförderung Drittland) und **Seeverkehr** (Kreuzfahrt). **Für Busreisen gilt sie nicht**; Beispiel 3 (Busreise Zürich–Rom mit Hin- und Rückflug): Der Flug gilt als Drittland, die **Busreise wird in Drittland- und EU-Anteil aufgeteilt**. Wer die Vereinfachung nutzt, nutzt sie für **alle** Reisen eines Veranlagungszeitraums (Abs. 5).
- **Rechnung an Unternehmer:** Angabe des Umfangs der Drittlandsleistungen (Abs. 7; § 14 Abs. 4 Nr. 7). **Vorrang:** Liegt zusätzlich eine Befreiung vor, die den Vorsteuerabzug ausschließt, geht diese vor (Abs. 8).
- **Nachweis:** A 25.2 erwähnt **§ 72 UStDV nicht**. Aufzeichnung dazu: A 25.5 Abs. 9 (Drittlandsvorleistungen getrennt von EU-Vorleistungen; bei gemischter Vorleistung den **Aufteilungsmaßstab dokumentieren**; ist nur ein Teil steuerfrei, muss die Bemessungsgrundlage **für beide Teile** erkennbar sein).

## Frage 7: Anzahlung
- **Anzahlungsbesteuerung gilt** (A 25.3 Abs. 5 S. 1 und 2; § 13 Abs. 1 Nr. 1 Buchst. a Satz 4). Bei Vereinnahmung vor Ausführung ist ggf. auf Reiseleistung und Eigenleistung aufzuteilen.
- **Marge bei Anzahlung** (S. 3 bis 8): Vom auf die Reiseleistung entfallenden Betrag sind die **kalkulierten Reisevorleistungen** abzuziehen, **auch wenn sie noch nicht erbracht oder bezahlt** sind. Bei **anteiliger** Vereinnahmung: **kalkulierte steuerpflichtige Marge × vereinnahmter Betrag ÷ Gesamtreisepreis** (S. 5). Planmäßig steuerfreie Leistungen bleiben außen vor (S. 4). **Schätzung** nach Kalkulation der Reise oder Erfahrungssätzen der Vorjahre (S. 6, EuGH C-422/17 *Skarpa Travel*), **Korrektur spätestens**, wenn die Informationen vorliegen; **Einmalberichtigung** zulässig, wenn keine negativen Einzelmargen entstehen (S. 7 und 8). Wird die Reise nicht erbracht, setzt die Berichtigung nach § 17 Abs. 2 Nr. 2 UStG die **tatsächliche Rückzahlung** voraus (S. 9).
- **Folge:** Die Software braucht eine **Kalkulation je Reise** (kalkulierte Vorleistungen) schon bei der Anzahlung.

## Frage 8: Zeitpunkt und Stornierung
- **Stornogebühr** (A 25.1 Abs. 14 S. 1 und 2): **echter Schadensersatz, kein Entgelt**; Gutschrift an ein Reisebüro ist Entgelt (S. 3), **Umbuchungsgebühren erhöhen das Entgelt** (S. 4). A 25.3 Abs. 6: Vorleistungen, die der Unternehmer nicht ersparen kann, sind durch die Stornogebühr entschädigt und **keine Vorleistungen mehr**, auch bei **pauschalierten** Stornogebühren; Vorleistungen, die er selbst **gegen Gebühr stornieren** muss, sind **insoweit keine Vorleistungen**. **Unser Beispiel** (Stornogebühr 3.600 €, Hotelstorno 500 €): Die Gebühr ist Schadensersatz, das Hotelstorno keine Vorleistung. Es entsteht **keine** Marge.
- **Voranmeldungszeitraum der Marge** (Reiseende, Reisebeginn, anteilig): **nicht in den gelesenen Abschnitten**. Nur die Anzahlungsregel (Frage 7) und § 13 sind belegt. **Offen.**

## Frage 9: Maut, Parken, Fähre, Eintritte
- **Reparatur eines Busses während der Reise** ist **keine** Reisevorleistung (A 25.1 Abs. 9 S. 4). **Vorsteuer** aus Leistungen, die der Reise nur **mittelbar** zugutekommen, ist abziehbar (A 25.4 Abs. 1 S. 4); ebenso der **Kauf eines Reisebusses** (S. 3).
- **Daraus abgeleitet [nicht ausdrücklich im Text]:** Maut und Parken für den **eigenen Bus** sind Betriebskosten (Vorsteuer abziehbar, keine Vorleistung). **Eintritte**, Fähre für Gäste und ähnliche Leistungen an die Reisenden sind **unmittelbar** und damit Reisevorleistungen. Die **Weiterberechnung an den Kunden** (Durchlaufposten oder Entgelt) ist **nicht** behandelt. **Offen.**

## Frage 10: Fremdbus
- **Ohne weitere Leistungen:** kein Leistungsbündel, **kein § 25**, Vorsteuer aus der Subunternehmerrechnung nach § 15 (siehe Frage 1, abgeleitet).
- **In einer Reise mit Hotel:** Der Fremdbus ist **Reisevorleistung** (A 25.1 Abs. 8 S. 10 und 11); die Vorsteuer daraus ist **nicht abziehbar** (A 25.4 Abs. 1 S. 1). Der Ausschluss gilt **nur, soweit steuerpflichtige Reiseleistungen** nach § 25 erbracht werden (A 25.4 Abs. 2 S. 1). Vorsteuer aus **Eingangsleistungen, die keine Reisevorleistungen sind** (Büro, Fahrzeuge, Prospekte), bleibt abziehbar, auch wenn sie für steuerfreie Reiseleistungen verwendet werden (A 25.4 Abs. 4).

## Aufzeichnungen (A 25.5) für die Software
- **Jede einzelne Reise** aufzeichnen, **getrennt** von den übrigen Umsätzen, darunter **Eigenleistungen** (Abs. 1 S. 1 bis 3). Steuerpflichtige und steuerfreie Reiseleistungen **getrennt** (Abs. 2).
- **Entgelt** brutto einschließlich Umsatzsteuer, **Preisänderungen festhalten** (Abs. 3). **Mehrere gleichartige Reisen** an einen Leistungsempfänger dürfen als **Gesamtbetrag** aufgezeichnet werden (Abs. 4).
- **Gemischte Leistungen:** Gesamtbetrag der Reise **und** die auf Reiseleistung und Eigenleistung entfallenden Beträge (Abs. 5).
- **Reisevorleistungen** brutto einschließlich Umsatzsteuer, **Änderungen festhalten** (Abs. 6). **Zuordnung zur Reise** nachvollziehbar; bei Vorleistungen für mehrere Reisen die **Teilbeträge je Reise** (Abs. 7).
- **Bemessungsgrundlage** (steuerpflichtig und steuerfrei) und **Änderungen** (Abs. 8). **Drittland und EU** getrennt, **Aufteilungsmaßstab** dokumentieren (Abs. 9).
- **Aufbewahrungsfrist:** in A 25.5 **nicht geregelt**.

## Folgen für den Bauplan (Änderungen gegenüber `paragraph-25-abrechnung.md`)
1. **Keine Gesamtmarge** je Zeitraum. Einzelmarge je Besteller und Reise, optional **Vereinfachung** (Zusammenfassen bei einheitlichem Kalkulationssatz, **dann für alle Reisen des Zeitraums**, negative Margen nicht verrechenbar).
2. **Eigenleistung** wird herausgerechnet: Maßstab **Einzelverkaufspreise** (sonst Kosten brutto, ohne Gemeinkosten und Unternehmerlohn). Die Rechenfunktion bekommt dafür **zwei Maßstäbe**.
3. **Kalkulation je Reise** (kalkulierte Vorleistungen) ist **Pflicht** für Anzahlungen, die Marge der Anzahlung wird **anteilig** gerechnet und später **korrigiert**.
4. **Stornogebühr** ist kein Entgelt, nicht ersparbare Vorleistungen sind keine Vorleistungen mehr. Die Margenabrechnung braucht den Fall „storniert“.
5. **Kontingente** (Betten, Plätze) mit Kennzeichen „voll als Vorleistung“ (Vereinfachung) oder „nur abgerufen“ (Regelfall).
6. **Drittlandsanteil** je Vorleistung, bei Beförderung nach **gefahrenen Kilometern** (Grenzstände), bei Unterkunft nach Standort. **Rechnung an Unternehmer** zeigt den Drittlandsanteil.
7. **Aufzeichnungen nach 25.5** als Auswertung: je Reise Entgelt, Vorleistungen, Bemessungsgrundlage, getrennt nach steuerpflichtig und steuerfrei, Eigenleistung getrennt.
8. **Vorsteuer** aus Reisevorleistungen **nie** abziehbar, aus dem übrigen Einkauf **immer** (Schnittstelle zur Buchhaltung: Kennzeichen am Eingangsbeleg).

## Noch offen (Quellen zeigen keine Antwort)
- Frage 3 (Unternehmerkunde bucht eine Reise **für sein Unternehmen**), Frage 8 (Voranmeldungszeitraum der Marge), Frage 9 (Weiterberechnung von Maut und Parken), Frage 10 (Fall c ausdrücklich).
- **Nicht recherchiert:** Teil 2 bis 4 der Fragen (Steuersatz der Beförderung, § 3b, Rechnungen, Aufbewahrung, E-Rechnung, DATEV).
