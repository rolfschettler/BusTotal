# Fachliche Recherche: Belegwesen (Phase 6)

Stand: 2026-11-20. Webrecherche, **keine Rechts- oder Steuerberatung**. Die Quellen sind überwiegend IHK-Merkblätter, Fachportale und Softwareanbieter, kaum Primärquellen (Gesetzestext, BMF-Schreiben).
Kennzeichnung: **[belegt]** = von mehreren Quellen übereinstimmend genannt, **[abgeleitet]** = von mir geschlossen, **[offen]** = nicht geklärt, Steuerberater fragen.
Vor dem Produktiveinsatz muss der Steuerberater die Umsetzung prüfen.

## 1. Unveränderbarkeit, Storno, Nummernkreise (GoBD)
- **[belegt]** Eine festgeschriebene Rechnung darf nicht mehr geändert oder gelöscht werden. Fehler werden durch eine **Stornorechnung** (klar mit dem Original verknüpft) und bei Bedarf eine **neue Rechnung** korrigiert. Grundlage: § 146 Abs. 4 AO, § 239 Abs. 3 HGB. Das gilt auch für E-Rechnungen. ([kostenlose-erechnung.de](https://kostenlose-erechnung.de/ratgeber/gobd-betriebspruefung-vorbereiten-prueferexport/), [Deutsche Handwerks Zeitung](https://www.deutsche-handwerks-zeitung.de/e-rechnung-fehler-korrigieren-und-richtig-archivieren-143681/), [accountable.de](https://www.accountable.de/blog/gobd-konform/))
- **[belegt]** Die Rechnungsnummer muss **fortlaufend und eindeutig** sein (§ 14 Abs. 4 Satz 1 Nr. 4 UStG). Lücken lösen bei Prüfungen Rückfragen aus.
- **[belegt, Praxis]** Entwürfe erhalten die endgültige Nummer erst beim **Festschreiben**. Eine stornierte Nummer bleibt belegt und bleibt im Bestand, die Korrektur bekommt die nächste freie Nummer ([Lexware](https://help.lexware.de/de-form/articles/548199-abgeschlossene-rechnungen-stornieren-und-neu-ausstellen); laut Lexware Best Practice, keine gesetzliche Pflicht).
- **[offen]** Der Originaltext der GoBD (BMF-Schreiben vom 14.11.2014, Randziffern zur Unveränderbarkeit ca. Rz. 107 ff.) wurde nicht direkt geprüft.

**Folgen für die App:**
1. Beleg hat Status `Entwurf` → `festgeschrieben` (→ `storniert`). Nach dem Festschreiben sind alle Felder gesperrt (auch im Backend, nicht nur in der Oberfläche).
2. Nummer wird **erst beim Festschreiben** vergeben, in einer Datenbank-Transaktion mit Sperre (kein Doppel, keine Lücke durch Parallelzugriffe).
3. Storno erzeugt einen **eigenen Beleg** (Stornorechnung mit Verweis auf das Original, eigene Nummer), nie ein Löschen.
4. Der Beleg speichert alle Werte **zum Zeitpunkt der Erstellung** (Kundenanschrift, Firmenangaben, Preise, Steuersatz), nicht nur Verweise. Spätere Änderungen am Kundenstamm dürfen alte Belege nicht verändern.
5. Änderungsprotokoll (wer, wann, was) für alles vor dem Festschreiben.
6. Nummernkreise je Belegart und Firma (Angebot, Auftragsbestätigung, Rechnung, Storno/Gutschrift), Format je Firma einstellbar (z. B. Jahr + laufende Nummer). **[offen]** Ob die Firma für Stornos denselben oder einen eigenen Kreis wünscht, mit Steuerberater klären.

## 2. Aufbewahrung
- **[belegt]** Rechnungen (Buchungsbelege): **8 Jahre** seit 1.1.2025 (vorher 10), durch das Vierte Bürokratieentlastungsgesetz. Grundlage § 257 HGB, § 147 AO, § 14b UStG. ([Haufe](https://www.haufe.de/id/beitrag/aufbewahrungsfristen-HI844776.html), [IHK Konstanz](https://www.ihk.de/konstanz/recht-und-steuern/steuer-und-finanzpolitik/finverwal/aufbewahrung-von-geschaeftsunterlagen-1672476))
- **[belegt]** Handels- und Geschäftsbriefe (ohne Rechnungen): **6 Jahre**.
- **[abgeleitet]** Angebote und Auftragsbestätigungen fallen unter die 6 Jahre (Quellen nennen sie nicht ausdrücklich). **[offen]** Steuerberater bestätigen lassen.
- **[belegt]** Fristbeginn: Ende des Kalenderjahres der Entstehung. Eine Verlängerung auf 10 Jahre betrifft nur Finanzunternehmen, nicht unsere Kunden.
- **[belegt]** E-Rechnungen müssen im **Ursprungsformat unverändert** aufbewahrt werden.

**Folge für die App:** Belege nie physisch löschen. Eine Löschsperre bis Fristende (Rechnungen 8 Jahre, Angebote 6 Jahre ab Jahresende). Die Löschung personenbezogener Daten im Kundenstamm (DSGVO) darf Belege nicht berühren. Das Zusammenspiel DSGVO und Aufbewahrung gehört in die Datenschutz-Recherche.

## 3. Pflichtangaben der Rechnung (§ 14 Abs. 4 UStG)
**[belegt]** (IHK-Merkblätter, z. B. [IHK Bremen](https://www.ihk.de/bremen-bremerhaven/beraten-informieren2/steuern/umsatzsteuer-pflichtangaben-rechnungen-5017686)):
- Name und Anschrift des Leistenden und des Empfängers
- **Steuernummer oder USt-IdNr.** des Leistenden
- Ausstellungsdatum
- **fortlaufende Rechnungsnummer**
- Menge und Art der Leistung
- **Zeitpunkt der Leistung** (bei uns: Fahrtdatum bzw. -zeitraum)
- Entgelt (netto), Steuersatz und Steuerbetrag, ggf. Hinweis auf Steuerbefreiung
- Rechnung innerhalb von **6 Monaten** nach der Leistung (§ 14 Abs. 2 UStG)
- Fehlen Steuernummer/USt-IdNr., Datum oder Rechnungsnummer, gefährdet das den Vorsteuerabzug des Kunden.

**Folge:** Festschreiben ist nur möglich, wenn alle Pflichtangaben gefüllt sind (Prüfung im Backend). Steuernummer/USt-IdNr. gehören in die Firmenstammdaten (je Mandant).

## 4. Umsatzsteuer bei Personenbeförderung
- **[belegt]** Personenbeförderung ist in Deutschland umsatzsteuerpflichtig, im Linien- wie im Gelegenheitsverkehr. Mietbus mit Fahrer, Ausflugs- und Ferienfahrten zählen zum **Gelegenheitsverkehr** ([Merkblatt Finanzamt Sachsen](https://www.finanzamt.sachsen.de/download/Finanzaemter/214_Merkblatt_USt_zur_Personenbefoerderung_DE.pdf)).
- **[belegt]** Regelsatz **19 %**. **7 %** nach § 12 Abs. 2 Nr. 10 UStG gelten u. a. für den genehmigten **Linienverkehr** mit Kraftfahrzeugen, wenn die Beförderungsstrecke im Inland **höchstens 50 km** beträgt, sowie für Kraftdroschken innerhalb einer Gemeinde.
- **[abgeleitet, wichtig]** Für eine reine **Charterfahrt (Gelegenheitsverkehr) mit dem Reisebus** ergäbe sich daraus der **Regelsatz 19 %**. Die Sachsen-Quelle bindet die 50-km-Regel ausdrücklich an den Linienverkehr. Es gibt BFH-Rechtsprechung, die touristische Beförderungen (Stadtrundfahrt, [BFH V R 44/10](https://rewis.io/urteile/urteil/ktz-30-06-2011-v-r-4410/)) ermäßigt besteuert sah. **[offen] Das muss der Steuerberater für jede Fahrtart verbindlich festlegen.** Der Steuersatz muss in der App je Positionsart **einstellbar** sein, nicht fest verdrahtet.
- **[belegt, Ansatz]** Bei grenzüberschreitenden Fahrten gilt: Die Beförderung wird dort ausgeführt, wo sie bewirkt wird (§ 3b UStG), nach **Streckenanteilen** (inländischer Anteil steuerbar). Es gibt Erleichterungen für den Gelegenheitsverkehr mit Kraftfahrzeugen. **[offen]** Genaue Anwendung (Strecke je Land, Satz auf den Inlandsanteil, Ausland) ist nicht geklärt. Die Streckenplanung kann künftig den **Streckenanteil je Land** liefern. Das ist eine Vorbereitung, kein Muss für die erste Version.
- **[offen]** **Reiseleistungen nach § 25 UStG** (Margenbesteuerung), falls ein Kunde Pauschalreisen veranstaltet (Bus + Hotel + Programm). Die Suche hat dazu nichts geliefert. Bei Pauschalreisen ändern sich Rechnung und Steuerausweis grundlegend (kein gesonderter Steuerausweis). **Zuerst beim Auftraggeber klären, ob das vorkommt.**

**Folgen für die App:** Steuersatz je Rechnungsposition, Vorbelegung über eine **Regel** (später die Preisfindung), immer manuell überschreibbar. Steuerhinweis (z. B. steuerfrei, Ausland) als Textfeld je Position.

## 5. Belegarten und Ablauf
- Kette laut Fahrplan: Anfrage → Angebot → Auftragsbestätigung → Rechnung. Angebot und Auftragsbestätigung sind **keine Buchungsbelege**, die strengen GoBD-Regeln der Rechnung gelten für sie nicht in gleicher Härte. **[abgeleitet]** Trotzdem sollten sie nach Versand **unveränderbar versioniert** werden (neue Version statt Änderung), das schützt vor Streit mit dem Kunden.
- **[belegt]** **Anzahlungs- und Abschlagsrechnungen** brauchen dieselben Pflichtangaben wie normale Rechnungen und den Steuerausweis je Zahlung. Die Steuer entsteht bei Anzahlungen mit dem Zahlungseingang (§ 13 UStG), eine Quelle widerspricht (nicht verlässlich). Die **Schlussrechnung** zieht erhaltene Anzahlungen samt Steuer ab und zeigt Gesamtleistung, Gesamtsteuer, Zahlungen und Restbetrag ([Lexware](https://www.lexware.de/wissen/unternehmerlexikon/abschlagsrechnung/), [kostenlose-erechnung.de](https://kostenlose-erechnung.de/ratgeber/teilrechnung-vs-abschlagsrechnung/)). Im Busgeschäft ist die **Anzahlung vor der Fahrt** üblich, daher Pflicht für die erste Version.
- **[offen]** Gutschriften und Rechnungskorrekturen (§ 14c UStG: unrichtiger Steuerausweis) wurden nicht recherchiert.
- **[offen]** Rechnung an Privatpersonen: Kleinbetragsrechnungen (bis 250 €) haben erleichterte Pflichtangaben, sind aber von der E-Rechnungspflicht ausgenommen. Die App nutzt der Einfachheit halber immer die volle Rechnung.

**Erweiterter Ablauf (festgehalten 2026-11-21):** Zwischen Auftragsbestätigung und Rechnung steht der **Fahrtauftrag** (je Bus einer) mit Rückerfassung. Die Rechnung entsteht aus den zurückerfassten Fahrtaufträgen, entweder als Gesamtrechnung je **Reisevorhaben** oder als Sammelrechnung über beliebige Fahrtaufträge. Ausführlich in `docs/fachlich/workflow-reisevorhaben.md`.

## 6. E-Rechnung
- **[belegt]** Format: Norm **EN 16931** (z. B. XRechnung, ZUGFeRD). Rechnungen **empfangen** müssen alle Unternehmen seit 1.1.2025 können.
- **[belegt]** **Ausstellungspflicht** im inländischen B2B-Verkehr: ab **1.1.2027** für Unternehmen mit Vorjahresumsatz **> 800.000 €**, ab **1.1.2028** für alle. Bis dahin sind sonstige Rechnungen (Papier, PDF) erlaubt. EDI-Rechnungen sind mit Zustimmung des Empfängers erlaubt ([IHK Stuttgart](https://www.ihk.de/stuttgart/fuer-unternehmen/recht-und-steuern/steuerrecht/steuermeldungen/e-rechnungen-5864496), [Grant Thornton](https://www.grantthornton.de/themen/2026/e-rechnungspflicht-2027-die-wichtigsten-fragen-und-antworten-zur-e-rechnung/)).
- **[belegt]** Ausgenommen sind u. a. steuerfreie Umsätze nach § 4 Nr. 8–29 UStG, Kleinbetragsrechnungen und Fahrausweise. **Rechnungen an Privatkunden (B2C) sind nicht betroffen.** Anzahlungsrechnungen vor Eintritt der Pflicht müssen keine E-Rechnung sein.
- **[offen]** Die Quellen widersprechen sich bei der Datierung der BMF-Schreiben (2024, 2025, evtl. 2026), und für 2027 wird eine mögliche Nachbesserung des Gesetzgebers genannt. **Vor dem Bau der E-Rechnung den aktuellen Stand am BMF-Original prüfen.**

**Folgen für die App:** Rechnung intern als **strukturierte Daten** speichern (nicht nur PDF). Daraus lassen sich PDF und später XRechnung/ZUGFeRD erzeugen. Die E-Rechnung ist ein eigener späterer Baustein, aber das Datenmodell der Rechnung muss ihn jetzt schon tragen (Käufer-/Verkäuferdaten, Positionen mit Steuersatz und -kategorie, Zahlungsbedingungen, Leitweg-ID für öffentliche Auftraggeber).

## 7. Zusammenfassung für den Bau von Phase 6
**Jetzt umsetzbar** (Punkte 1–3, 5): Belegstatus und Sperre, Nummernvergabe beim Festschreiben, Storno als eigener Beleg, Wertekopie im Beleg, Pflichtangaben-Prüfung, Anzahlung/Schlussrechnung, Versionierung von Angebot und Auftragsbestätigung, Löschsperre.
**Umsetzungsstand** (siehe `docs/FAHRPLAN.md`, Phase 6): gebaut sind Status und Sperre, Nummernvergabe, Storno, Wertekopie, Pflichtangaben-Prüfung, Anzahlung/Schlussrechnung, PDF und Archiv-PDF. **Noch nicht gebaut:** Löschsperre, Versionierung von Angebot und Auftragsbestätigung.
**Einstellbar statt fest** (Punkt 4): Steuersatz je Position, Preisfindung als austauschbare Regel.
**Später:** E-Rechnung (Datenmodell jetzt vorbereiten), Streckenanteil je Land, § 25-Reiseleistungen falls nötig.

## 8. Fragen an den Steuerberater
1. Welcher Steuersatz gilt für unsere Fahrtarten (Charter, Transfer, Schulbus, Linie, Ausflug), insbesondere die 50-km-Regel?
2. Grenzüberschreitende Fahrten: Wie werden Streckenanteile und Steuersatz behandelt? Welche Nachweise sind nötig?
3. Pauschalreisen (§ 25 UStG): Muss die App das abbilden?
4. Gutschriften, Stornos, Korrekturen: gleicher oder eigener Nummernkreis?
5. Aufbewahrung von Angeboten und Auftragsbestätigungen: 6 Jahre?
6. Umsatzgrenze für die E-Rechnungspflicht 2027: Wer muss ab wann?
7. Anzahlungsrechnungen: Handhabung der Umsatzsteuer, Verbuchung (DATEV).

## Fragen an den Auftraggeber
1. Kommen Pauschalreisen oder nur Beförderungsleistungen vor?
2. Welche Belegarten braucht der Alltag (Angebot, Auftragsbestätigung, Anzahlung, Schlussrechnung, Gutschrift, Mahnung)?
3. Formate der Belegnummern (z. B. `RE-2027-00001`) je Firma?
4. Wie viele Rechnungen pro Monat (Größenordnung für den Lasttest)?
