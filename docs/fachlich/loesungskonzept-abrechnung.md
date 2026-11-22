# Lösungskonzept Abrechnung: unsere Entscheidungen nach bestem Wissen

Stand: 2026-11-22. **Grundlage:** die Recherche in `ustae-25-ergebnisse.md`, `befoerderung-ergebnisse.md`, `rechnungen-ergebnisse.md` und `aufbewahrung-erechnung-ergebnisse.md` (Gesetzestexte, Erlass im Abdruck der Handausgabe 2024, nicht amtlich). **Ausgangslage:** Wir liefern ein Produkt und müssen dem Kunden eine Lösung anbieten, denn Steuerberater sind sich zu § 25 UStG erfahrungsgemäß uneinig. Deshalb gilt:
1. **Wir entscheiden nach dem Wortlaut von Gesetz und Erlass** und wählen im Zweifel die **vorsichtigere** Variante.
2. **Strittiges ist einstellbar** (Firmeneinstellungen), nichts Steuerliches ist fest im Programm verdrahtet.
3. **Jede Entscheidung ist nachvollziehbar:** Beleg und Margenabrechnung speichern die **Regelversion** und die **Einstellungen**, nach denen gerechnet wurde.
4. **Die Software rechnet, sie berät nicht.** Der Kunde lässt die Voreinstellungen **vor dem Produktivbetrieb** von seinem Berater freigeben (Freigabeliste, Abschnitt 4). Das ist Teil der Lieferung, kein Mangel.

Kennzeichnung: **[belegt]** steht im Gesetz oder Erlass, **[Entscheidung]** ist unsere Festlegung ohne ausdrückliche Vorgabe, **[Einstellung]** ist je Firma änderbar.

## 1. Margenbesteuerung (§ 25 UStG)

### 1.1 Wann gilt sie
- **[belegt]** § 25 gilt nur bei einem **Bündel** mit mindestens einer Beförderungs- oder Beherbergungsleistung **und** eingekauften **Reisevorleistungen** (UStAE 25.1 Abs. 2, 8, 9). Reine Beförderung, auch mit Fremdbus, ist **kein** § 25 (Abs. 2 S. 3 und 4).
- **[Entscheidung]** Das Kennzeichen „Reiseleistung nach § 25“ liegt am **Reisevorhaben** (und wird an die Belege vererbt). Die Software **schlägt es vor**, sobald das Vorhaben **mindestens eine eingekaufte Reisevorleistung** hat (Hotel, Fähre, Eintritt, Fremdbus **innerhalb** einer Reise). Der Nutzer kann es mit Begründung ändern. **Ohne** eingekaufte Vorleistung ist die Beförderung **immer regulär**.
- **[belegt]** Ein **gemieteter Bus mit Fahrer** ist ein **eigenes Beförderungsmittel**, wenn wir Weisungsbefugnis und Verantwortung haben (25.1 Abs. 8 S. 9). **[Entscheidung]** Am Fahrzeug einer Position steht „eigen / gestellt / Fremdunternehmer“; **gestellt** zählt als eigen.

### 1.2 Marge, Methode
- **[belegt]** Marge = Reisepreis **minus** Reisevorleistungen, beides **brutto**; Steuer = Marge × 19/119 (§ 25 Abs. 3; § 12 Abs. 1; UStAE 25.3 Abs. 1). Vorsteuer aus Reisevorleistungen ist **nie** abziehbar, aus dem übrigen Einkauf **immer** (25.4).
- **[belegt]** Marge **je Besteller und Reise** (tritt der Besteller im eigenen Namen für die Mitreisenden auf), sonst je Reisendem. **[Entscheidung]** Standard: **je Besteller und Reisevorhaben**.
- **[belegt]** **Negative Marge** wird nicht mit positiven verrechnet (25.3 Abs. 1 S. 6, im Erlass für die Vereinfachung; der Fachartikel nennt es allgemein). **[Entscheidung]** Steuer 0, kein Erstattungsanspruch, Warnung im Abschluss, **nie verrechnen**.
- **[Einstellung] Vereinfachung** (25.3 Abs. 1 S. 3 bis 5: Reisen mit einheitlichem Kalkulationssatz zusammenfassen, dann **für alle Reisen des Zeitraums**): **aus** als Standard, einschaltbar je **Kalenderjahr**, Wechsel nur zum Jahresbeginn. **Gesamtmarge** je Zeitraum: **nicht gebaut** (nach dem gelesenen Text nicht vorgesehen).

### 1.3 Eigenleistung (eigener Bus in einer Reise mit eingekauftem Hotel)
- **[belegt]** Die Eigenleistung wird **herausgerechnet** und regulär besteuert: Maßstab **Einzelverkaufspreise**, ersatzweise **Kosten einschließlich Umsatzsteuer, ohne Gemeinkosten und Unternehmerlohn** (25.3 Abs. 2; Beispiel 600 € ergibt Marge 76,19 €).
- **[Entscheidung]** Jede Position eines § 25-Vorhabens trägt die Art **„Eigenleistung“** oder **„Reiseleistung“**. Die Software rechnet nach dem Maßstab aus der **Einstellung** (Standard: Einzelverkaufspreise; Alternative: Kosten) und zeigt die Rechnung **in der Margenabrechnung** offen an. Der **Steuersatz der Eigenleistung** folgt der Fahrtart (Charter, Ausflug, Gruppe: **19 %**).

### 1.4 Vorleistungen erfassen (Tabelle `travel_costs`)
- **[belegt]** Reisevorleistung = Leistung eines Dritten, die dem Reisenden **unmittelbar** zugutekommt (25.1 Abs. 9). **Kein** Reisevorleistung: Reparatur des Busses während der Reise, Reisebüro-Vermittlung (Abs. 9 S. 4).
- **[Entscheidung]** Jede erfasste Kostenart hat ein Kennzeichen **„Reisevorleistung ja/nein“** mit Voreinstellung: **ja** für Hotel, Verpflegung, Eintritte, Fähre für Gäste, Reiseleiter (extern), Fremdbus innerhalb einer Reise; **nein** für **Maut, Parkgebühren, Treibstoff, Reparatur** des eigenen oder gestellten Busses (Betriebskosten, Vorsteuer abziehbar).
- **[Entscheidung] Weiterberechnung** von Maut oder Parken an den Kunden: **Teil des Entgelts der Fahrt** (gleicher Steuersatz), **nicht** als durchlaufender Posten. Das ist die vorsichtigere Lesart (durchlaufender Posten verlangt Handeln im fremden Namen und auf fremde Rechnung, § 10 Abs. 1 S. 6, **nicht gelesen**). Einstellbar je Kostenart.
- **[Entscheidung]** **Kontingente** (feste Betten, Plätze): Standardmäßig nur **abgerufene** Menge als Vorleistung; mit eingeschalteter Vereinfachung **voll** (25.3 Abs. 1 S. 4).
- Pflichtfelder: Lieferant, Rechnungsnummer, Datum, **Brutto**, **Land**/Ort der Nutzung, **Zuordnung zur Reise**, bei Beförderung **Kilometer im Drittland**; Beleg als Datei. **Nie löschen**, nur stornieren.

### 1.5 Drittland (Schweiz, Großbritannien, Türkei u. a.)
- **[belegt]** Aufteilung der Marge **im Verhältnis der Vorleistungsanteile** (25.3 Abs. 3, Beispiel: 85 € von 100 € steuerfrei); Beförderung **nach zurückgelegten Strecken** (25.2 Abs. 3 S. 2 und 3); Rechnung an Unternehmer nennt den **Umfang der Drittlandsleistungen** (25.2 Abs. 7); Aufzeichnung des **Aufteilungsmaßstabs** (25.5 Abs. 9).
- **[Entscheidung]** Land je Vorleistung, für Beförderungen **Kilometer im Drittland** aus den Grenzständen. Die **Zielortregelung** (nur Flug und Schiff, 25.2 Abs. 4 und 6) wird **nicht** gebaut. **[Entscheidung]** Gebiete nach § 1 Abs. 3 UStG (Freihäfen) zählen **nie** als Drittland.

### 1.6 Anzahlung, Storno, Zeitpunkt
- **[belegt]** Steuer bei **Vereinnahmung**; Marge der Anzahlung = **kalkulierte** Marge × vereinnahmter Betrag ÷ Gesamtpreis; **Korrektur**, sobald die Vorleistungen bekannt sind; **Einmalberichtigung** zulässig, wenn keine negativen Einzelmargen (25.3 Abs. 5).
- **[Entscheidung]** Jedes § 25-Vorhaben hat eine **Kalkulation der Vorleistungen** (Pflicht vor der ersten Anzahlung). Der Voranmeldungsbericht rechnet die Anzahlungsmarge **daraus**, der Abschluss der Reise **korrigiert**.
- **[belegt]** Stornogebühr = **Schadensersatz, kein Entgelt**; nicht ersparbare Vorleistungen, die durch die Gebühr entschädigt werden, sind **keine Vorleistungen** mehr (25.1 Abs. 14; 25.3 Abs. 6). **[Entscheidung]** Der Storno einer § 25-Reise erzeugt eine **Stornoabrechnung** mit der Gebühr als **nicht steuerbarem** Posten und der Neuberechnung der Marge.
- **[belegt]** Leistungsausführung einer sonstigen Leistung = **Vollendung**, bei Dauerleistungen **Ende** (UStAE 14.5 Abs. 16 Nr. 4). **[Entscheidung]** Die Marge gehört in den **Voranmeldungszeitraum des Reiseendes**; bei Zahlungen vor Reiseende gilt die Anzahlungsregel.

### 1.7 Rechnung
- **[belegt]** Hinweis **„Sonderregelung für Reisebüros“**, **kein gesonderter Steuerausweis** (§ 14a Abs. 6); übrige Pflichtangaben bleiben; bei Unternehmerkunden **Drittlandsumfang** angeben (25.2 Abs. 7).
- **[Entscheidung]** Beleg-Rechner: `margin_scheme` ⇒ keine Steuerzeile, keine Steuersatzspalte, Hinweistext **einstellbar** (Standardtext wie oben). **Anzahlungsrechnung** einer § 25-Reise ebenfalls ohne Steuerausweis, mit Hinweis und Kennzeichnung als Anzahlung. Die **Margenabrechnung** ist ein **internes** Dokument (nicht für den Kunden).

## 2. Beförderung, Ausland

### 2.1 Steuersatz
- **[belegt]** 7 % nur für **genehmigten Linienverkehr**, Taxi und Ähnliches, **Schüler- und Schulträgerfahrten** (wie Linienverkehr) innerhalb einer Gemeinde oder bis **50 km** (tatsächliche Strecke, bei Auslandsfahrten nur der Inlandsteil). **Gelegenheitsverkehr mit Omnibus** (Charter, Ausflug, Gruppe, Mietbus mit Fahrer) **19 %** (§ 12 Abs. 2 Nr. 10; UStAE 12.13, 12.14).
- **[Entscheidung]** Jede Fahrt hat eine **Fahrtart** (Enum). Voreinstellung des Satzes daraus. **7 %** nur bei Fahrtart *Linienverkehr genehmigt* oder *Schul-/Schülerfahrt*; die Software **prüft die Entfernung** und **warnt**, wenn 7 % bei anderer Fahrtart oder über 50 km Inlandsstrecke gewählt wird. Die **Genehmigung** (Nummer, Gültigkeit) wird je Firma hinterlegt. **Stadtrundfahrt, Sightseeing, Flughafentransfer mit festem Fahrplan:** Standard **19 %** (Gelegenheitsverkehr), **einstellbar** je Fahrtart auf „Linienverkehr“ **nur** mit hinterlegter Genehmigung.

### 2.2 Auslandsanteil (§ 3b)
- **[belegt]** Nur der **Inlandsanteil** ist steuerbar; Aufteilung des **Nettopreises** im Verhältnis der **Streckenlängen** der Beförderung **mit Fahrgästen** (Leerkilometer **während** der Beförderung zählen, die Fahrt vom und zum Betriebshof ohne Fahrgäste **nicht**); **Brutto-Methode** zulässig; Methode **je Verkehrsart** im Zeitraum **einheitlich** (UStAE 3b.1 Abs. 4 und 6).
- **[Einstellung]** Methode **netto** (Standard) oder **brutto**, je Verkehrsart.
- **[Entscheidung]** Die Kilometerstände an der Grenze (Fahrer, im Fahrtauftrag, Rückerfassung durch das Büro) sind die **Grundlage**; Teilstrecken werden gekennzeichnet (*Betriebshof leer*, *Beförderung*, *Leerfahrt während Beförderung*). Fahrtenschreiberdaten bleiben beim Kunden verfügbar, die Software **liest sie nicht aus**. Ob das Finanzamt Kilometerstände anerkennt, ist **nicht belegt**: In der Rückerfassung wird die **Quelle** je Angabe vermerkt (Fahrer notiert, Tachograph, Navigation).
- **[Entscheidung]** Rechnung: **Inlandsanteil** mit Steuersatz und Steuerbetrag, **Auslandsanteil** als eigene Zeile **„nicht steuerbar, Ort der Leistung im Ausland (§ 3b Abs. 1 UStG)“** (Text **einstellbar**). Das entspricht dem Grundsatz des Erlasses (Entgelt nach Steuersätzen und Befreiungen, UStAE 14.5 Abs. 18 und 21); ein **fester Wortlaut** steht nicht im Text.
- **[Entscheidung]** **Ausländische Umsatzsteuer** (Registrierung, Abführung im Zielland, z. B. Österreich): **nicht gerechnet**. Die Software zeigt bei Auslandsstrecken den **Hinweis** „Ausländische Umsatzsteuer prüfen“ am Fahrtauftrag und in der Rechnungsvorschau. Diese Frage **liegt beim Kunden** (nicht recherchiert).

### 2.3 Barzahlung vor Ort
- **[belegt]** **Kleinbetragsrechnung** bis 250 € mit den Angaben aus § 33 UStDV; keine Rechnungsnummer nötig (UStAE 14.5 Abs. 14).
- **[Entscheidung]** Die Rückerfassung kennt **Zusatzleistungen** (Art, Betrag, Zahlungsart bar/Rechnung, Steuersatz aus der Fahrtart, Ort). **Barzahlungen** erzeugen eine **Quittung** (Kleinbetragsrechnung) für den Kunden, **ohne** eigene Rechnung; sie erscheinen **zusätzlich** auf der Schlussrechnung als „bereits bar bezahlt“ (kein Doppelumsatz). Sonderfahrten im Ausland: **nicht steuerbar** (Ort), mit Hinweis.

## 3. Rechnungen, Korrektur, Aufbewahrung, Technik

### 3.1 Rechnungen
- **[belegt] [Entscheidung]** Rechnungsmerkmale wie in `rechnungen-ergebnisse.md`: Anzahlungsrechnung **als Anzahlung erkennbar** mit Steuerausweis (reguläre Beförderung), Endrechnung mit **Abzug von Entgelt und Steuer** der Anzahlungen; Auftragsbestätigung mit **vorläufigem Preis ist keine Rechnung**; **Sammelrechnung** mit selbst erstellter **Anlage** (Fahrtenliste mit Datum und Strecke) und ausdrücklichem Verweis, **Leistungszeitraum mit Beginn und Ende** (Kalendermonat zulässig), Positionen **nach Steuersatz getrennt**.
- **[Entscheidung] Storno und Korrektur:** Minuszeichen, **Verweis auf die Nummer der Ursprungsrechnung**, **eigener Nummernkreis** (zulässig), Bezeichnung **„Stornorechnung“** (bei Teilbetrag „Rechnungskorrektur“) statt „Gutschrift“ (UStAE 14.3 Abs. 2 S. 5; 14c.1 Abs. 4a; 14.11 Abs. 1). **Gutschrift** im steuerlichen Sinn (Abrechnung des Leistungsempfängers) wird **nicht** gebaut.
- **[Entscheidung] Zahlungseingang** wird erfasst (Voraussetzung für die Voranmeldung); **Anzahlungen und Rückzahlungen** wirken **im Zeitraum der Zahlung**, nicht des Belegs (UStAE 13.5; 17.1 Abs. 2).
- **[Entscheidung] Rundung:** kaufmännisch auf Cent, **Steuer je Steuersatz aus der Summe** der Nettobeträge (bisherige Regel). **§ 32 UStDV ist nicht gelesen**, daher als **Freigabepunkt** geführt.
- **[Entscheidung]** Pflicht zur Rechnung nach Kundenart: **Unternehmer und juristische Personen** (Vereine, Schulen, Kommunen) **mit Pflicht** (6 Monate), **Privatpersonen** auf Wunsch (§ 14 Abs. 2). Die Software stellt **immer die volle Rechnung** aus (zulässig).
- **[Entscheidung]** Steuerbefreiungs-/Nicht-steuerbar-Hinweise als **Freitext mit Vorschlägen** (UStAE 14.5 Abs. 20: Grund umgangssprachlich genügt).

### 3.2 Aufbewahrung, Löschsperre, Datenschutz
- **[belegt]** Rechnungen 8 Jahre (§ 14b Abs. 1 UStG), Buchungsbelege 8 Jahre, Handelsbriefe und sonstige Unterlagen 6 Jahre (§ 147 AO; § 257 HGB), Fristbeginn Jahresende, Ablaufhemmung bei laufender Festsetzungsfrist. Der Erlass nennt für Rechnungen noch 10 Jahre.
- **[Entscheidung] [Einstellung]** Löschsperre standardmäßig **10 Jahre ab Jahresende** für **Rechnungen, Anzahlungen, Stornorechnungen, Fahrtaufträge, Kostenbelege und Margenaufzeichnungen**; **6 Jahre** für **Angebote und Auftragsbestätigungen**. Einstellbar je Belegart. **Danach Löschung** per Befehl mit Protokoll, **Ablaufhemmung** durch Sperrkennzeichen.
- **[belegt] [Entscheidung]** **Archiv-PDF** mit SHA-256 (vorhanden); zusätzlich **Datenexport** aller Belege, Positionen, Zahlungen, Fahrtaufträge, Kosten (CSV/XML) für die Außenprüfung (§ 147 Abs. 6 AO). **Verfahrensdokumentation:** **Vorlage** der Software (Belegkette, Festschreiben, Archiv, Prüfsumme, Rechte, Datensicherung), **Ergänzung und Aufbewahrung** (10 Jahre) beim Betrieb. **GoBD nicht gelesen.**
- **[belegt] [Entscheidung]** DSGVO: Stammsatz anonymisieren, Belegkopie **nur** für Aufbewahrung nutzbar, nach Fristende löschen (Art. 17 Abs. 3 Buchst. b, Art. 18; § 35 BDSG).

### 3.3 E-Rechnung (dringend, Pflicht für große Aussteller ab 1.1.2027)
- **[belegt]** Ab Umsätzen nach dem 31.12.2026 Pflicht zur E-Rechnung an inländische Unternehmer, **sofern der Vorjahresumsatz 800.000 € übersteigt**; für alle ab 2028; Ausnahmen für **Kleinbetragsrechnungen**, **Fahrausweise**, Privatkunden, steuerfreie Umsätze nach § 4 Nr. 8 bis 29 (§ 14 Abs. 2; § 27 Abs. 38; UStDV §§ 33, 34).
- **[Entscheidung]** Priorität **hoch**. Ausgabe **XRechnung und ZUGFeRD** (Norm EN 16931) **zusätzlich** zum Archiv-PDF, **Originalformat archivieren**, Firmeneinstellung **„E-Rechnungspflicht ab“** (Datum) und Kundenmerkmale (**Leitweg-ID**, Empfänger **ist Unternehmer**, **Zustimmung zum Versand**). Charterbeleg als „Fahrausweis“: **nicht angenommen**, Charterrechnungen an Unternehmer **werden als E-Rechnung ausgestellt**, sobald die Pflicht gilt. **§ 25-Rechnungen** ebenso (keine Ausnahme im Text).

### 3.4 Buchhaltung
- **[Einstellung]** Konten und Steuerschlüssel **je Firma** frei einstellbar. Vorschlagswerte (aus einem Fachartikel, **nicht verifiziert**): SKR 03 Marge 8198, Reisevorleistungen 3220, Ausbuchung 8193; SKR 04 4133, 5220, 4138. Der Kunde bestätigt sie.
- **[Entscheidung]** **Voranmeldungsbericht** (Hilfsbericht, ersetzt keine Voranmeldung): Umsätze je Steuersatz, Marge steuerpflichtig und steuerfrei, nicht steuerbare Auslandsanteile, **auf Basis der Zahlungseingänge**, **ohne** Kennzahlen (Kennzahlen **nicht gefunden**, werden nach Bestätigung durch den Kunden ergänzt). **DATEV-Export:** zunächst Rechnungsausgang (EXTF) mit den eingestellten Konten.

## 4. Freigabeliste für den Kunden (vor Produktivbetrieb)
Der Kunde und sein Berater prüfen und **zeichnen ab**, dass die Voreinstellungen für **sein** Geschäft passen. Die Liste ist die Tabelle in Teil B von `fragen-steuerberater.md` (17 Punkte, mit Fundstellen) **plus** diese Punkte:
1. Gilt § 25 für **seine** Reisen (Hotel, Fremdbus, Programm)? Wenn **nein**, bleibt das Kennzeichen aus.
2. **Marge je Reise** oder **Vereinfachung** (Jahr), Maßstab der Eigenleistung (Einzelverkaufspreise oder Kosten).
3. Voreinstellung **Maut/Parken = Betriebskosten**, **Weiterberechnung = Teil des Entgelts**.
4. **Auslandsanteil:** Methode netto oder brutto, Rechnungstext, **ausländische Umsatzsteuer** (Registrierung im Zielland).
5. **Löschsperre:** 10 Jahre für Rechnungen und Fahrtaufträge, 6 Jahre für Angebote.
6. **E-Rechnung:** Pflicht ab 2027 oder 2028, Format, Leitweg-ID.
7. **Konten, Steuerschlüssel, Kennzahlen** der Voranmeldung.
8. **Haftungshinweis:** Die Software setzt die in der Einstellung hinterlegten Regeln um; die steuerliche Verantwortung liegt beim Unternehmer und seinem Berater.

## 5. Folgen für den Bauplan
- `paragraph-25-abrechnung.md` bleibt der **Bauplan** (Datenmodell, Rechner, Oberfläche); es **gilt dieses Dokument bei Abweichungen**.
- Neu aufgenommen: **Regelversion** und **Einstellungsschnappschuss** an der Margenabrechnung, **Fahrtart** an Fahrt und Position, **Art der Position** (Eigenleistung, Reiseleistung), **Quelle** der Grenzstände, **Zahlungseingang** als Baustein **vor** dem Voranmeldungsbericht, **E-Rechnung** als eigener Baustein mit Frist, **Datenexport** und **Löschsperre**.
- **Reihenfolge** (Vorschlag): Fahrtart und Steuersatz → Zahlungseingang → Tour und Reisevorhaben → Fahrtauftrag und Rückerfassung (mit Grenzständen) → § 3b-Aufteilung → Rechnung aus Fahrtaufträgen (Sammelrechnung) → E-Rechnung → § 25 (Vorleistungen, Margenrechner, Margenabrechnung) → Voranmeldungsbericht, Export, Löschsperre.

## 6. Nicht entschieden, bewusst offen
- **Ausländische Umsatzsteuer** (z. B. Österreich): Hinweis, keine Rechnung.
- **Kennzahlen der Voranmeldung**, **Steuerschlüssel**, **GoBD-Einzelheiten**, **§ 32 UStDV**, **UStAE 14.7** und **10.4**: nicht gelesen. Die Entscheidungen oben beruhen darauf nicht oder sind als Freigabepunkt geführt.
