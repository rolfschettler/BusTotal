# E-Rechnung: XRechnung für ausgehende Rechnungen (Etappe „Spur E“)

Stand: 2026-11-22. **XRechnung gebaut (Backend und Frontend) und im Browser getestet. Die Beispiel-XML einer Rechnung wurde am 2026-11-22 vom Nutzer mit dem Online-Prüfdienst erechnungsvalidator.service-bw.de fachlich geprüft und für in Ordnung befunden. Die Stornorechnung (Typ 381) ist noch nicht extern geprüft.** Anlass: Der Kunde fordert E-Rechnungen zwingend. Die gesetzliche Pflicht zur Ausstellung an inländische Unternehmer beginnt mit Umsätzen **ab 1.1.2027** für Aussteller mit mehr als 800.000 € Vorjahresumsatz, sonst ab 2028 (`docs/fachlich/aufbewahrung-erechnung-ergebnisse.md`, Frage 24).

## 1. Entscheidungen (mit dem Auftraggeber, 2026-11-22)
- **Format zuerst: XRechnung** (reine XML-Datei nach EN 16931, Syntax **UN/CEFACT CII**). ZUGFeRD (PDF mit eingebettetem XML) folgt danach (2026-11-22 umgesetzt: dieselbe XML, Profil XRechnung, wird mit `ZugferdDocumentPdfMerger` in das archivierte Beleg-PDF eingebettet; Route `GET /api/documents/{id}/zugferd`, nicht gespeichert, bei jedem Abruf zusammengesetzt; die strenge PDF/A-3-Prüfung, zum Beispiel mit veraPDF oder dem Online-Dienst, steht noch aus).
- **Empfänger:** Firmenkunden (B2B) **und** öffentliche Auftraggeber (Schulträger, Kommunen, Behörden). Öffentliche Auftraggeber brauchen die **Leitweg-ID** als Käuferreferenz.
- **Privatkunden** bleiben bei der PDF-Rechnung (keine Pflicht, § 14 Abs. 2 UStG).
- **Belegarten:** Rechnung, Anzahlungsrechnung, Stornorechnung. Angebot und Auftragsbestätigung sind keine Rechnungen.
- **Auslieferung:** Download der XML-Datei am Beleg. Den Versand (E-Mail, Portal) macht der Nutzer. E-Mail-Versand aus der Software ist ein eigener späterer Baustein.
- **Empfang** von E-Rechnungen (Pflicht seit 2025) ist **nicht** Teil dieser Etappe: Dafür genügt ein E-Mail-Postfach, das XML-Anhänge annimmt.

## 2. Ablauf
1. Am Kunden wird **„E-Rechnung statt PDF“** gesetzt und bei öffentlichen Auftraggebern die **Leitweg-ID** (Käuferreferenz) eingetragen.
2. Beim **Festschreiben** einer Rechnung dieses Kunden prüft die Software, ob alle Pflichtangaben vorliegen. Fehlt etwas, **wird nicht festgeschrieben**, keine Nummer verbraucht, die Meldung nennt die fehlenden Angaben (gleiche Regel wie beim Archiv-PDF).
3. Beim Festschreiben entsteht neben dem **Archiv-PDF** die **XRechnung-XML**, wird mit **SHA-256** im privaten Speicher abgelegt (`archive/documents/{id}.xml`) und nie neu erzeugt (GoBD: Original der E-Rechnung aufbewahren, § 14b UStG).
4. Im Beleg gibt es die Taste **„E-Rechnung (XML)“**. Sie liefert die archivierte Datei nach Prüfsummenprüfung.
5. Bei **älteren Rechnungen** (vor dem Festschreiben ohne XML) wird die XML beim ersten Abruf **einmal nacherzeugt** und archiviert, mit Protokolleintrag (wie beim Archiv-PDF).

## 3. Feldzuordnung (EN 16931 / XRechnung 3.0)
| Daten bei uns | Feld der Norm | Bemerkung |
|---|---|---|
| Belegnummer | **BT-1** Rechnungsnummer | |
| Belegdatum | **BT-2** Rechnungsdatum | |
| Belegart | **BT-3** Rechnungstyp: **380** Rechnung, **386** Anzahlungsrechnung, **381** Stornorechnung (Gutschriftsanzeige) | Die Stornorechnung wird mit **positiven** Beträgen und Verweis auf die stornierte Rechnung übermittelt (BG-3, BT-25/26), nicht mit negativen |
| Währung | **BT-5** | EUR |
| Fälligkeit | **BT-9** | `due_date` |
| Käuferreferenz | **BT-10** | **Leitweg-ID** des Kunden, sonst die Kundennummer (BR-DE-15 verlangt eine Angabe) |
| Leistungszeitraum | **BT-73/74** (oder BT-72 bei einem Tag) | `service_date_from/to` |
| Betreff | **BT-22** Bemerkung | |
| Absender: Name, Anschrift | **BT-27, BG-5** | aus dem **Schnappschuss `seller`** des festgeschriebenen Belegs |
| Absender: USt-IdNr. / Steuernummer | **BT-31** / **BT-32** (Schema `VA` / `FC`) | eines von beiden ist Pflicht |
| Absender: Kontakt | **BG-6, BT-41..43** | Ansprechpartner (Geschäftsführer, sonst Firmenname), Telefon, **E-Mail** (Pflicht in der XRechnung) |
| Absender: elektronische Adresse | **BT-34** | E-Mail der Firma, Schema `EM` |
| Empfänger: Name, Anschrift | **BT-44, BG-8** | aus dem **Schnappschuss `recipient`**; Straße, PLZ, Ort, Land sind Pflicht |
| Empfänger: USt-IdNr. | **BT-48** | wenn vorhanden |
| Empfänger: elektronische Adresse | **BT-49** | E-Mail des Kunden (Schema `EM`), bei Leitweg-ID-Empfängern die Leitweg-ID als Adresse (Schema `0204`) |
| Zahlungsart, IBAN, BIC | **BG-16, BT-81, BT-84, BT-86** | Zahlungsart **58** (SEPA-Überweisung), IBAN ist Pflicht bei dieser Zahlungsart |
| Zahlungsbedingungen | **BT-20** | „Zahlbar innerhalb von X Tagen bis zum …“ |
| Positionen | **BG-25**: BT-126 (Nr.), BT-153 (Name), BT-154 (Beschreibung), BT-129 (Menge), BT-130 (Einheit), BT-146 (Preis netto), BT-131 (Netto) | Einheiten nach UN/ECE Rec. 20: km `KMT`, Tag `DAY`, Std `HUR`, Stück/pauschal `C62` |
| Steuersatz je Position | **BT-151** Kategorie, **BT-152** Satz | Satz > 0: **S**; Satz 0: **E** (steuerbefreit) mit Grund aus dem Steuerhinweis, bei „nicht steuerbar“ **O** |
| Steuer je Satz | **BG-23** | aus `tax_summary` (Steuer je Satz aus der Nettosumme, wie im Beleg) |
| Summen | **BT-106, 109, 110, 112** | Netto, Netto, Steuer, Brutto |
| Abgezogene Anzahlungen | **BT-113** vorausgezahlter Betrag, dazu je Anzahlung **BG-3** (Rechnungsnummer und Datum) | `deduction_gross`; Schlussrechnung |
| Zu zahlen | **BT-115** | `payable_total` |

**Besonderheiten, die später dazukommen:** § 25-Rechnungen (kein Steuerausweis, Hinweis „Sonderregelung für Reisebüros“, Steuerkategorie **O** mit Grund), Sammelrechnung mit Anlage (die Anlage wird als eingebettete Datei **BG-24** mitgegeben), Auslandsanteil (Kategorie **O** für den nicht steuerbaren Teil). Der Aufbau trägt sie, gebaut werden sie mit ihren Etappen.

## 4. Neue Daten
- **Kunde:** `einvoice` (Kennzeichen „E-Rechnung statt PDF“) und `buyer_reference` (Leitweg-ID oder andere Käuferreferenz, bis 46 Zeichen, die Länge der Leitweg-ID).
- **Beleg:** `einvoice_path`, `einvoice_sha256`, `einvoice_format` (`xrechnung`), `einvoice_at` (nach dem Festschreiben setzbar wie die Archivfelder des PDFs).
- **Firma:** keine neuen Felder in dieser Etappe. Kontakt, E-Mail, Telefon, IBAN und Steuernummer stehen in den Firmendaten; es ist zu prüfen, dass die E-Mail dort Pflicht wird, wenn E-Rechnungen ausgestellt werden.

## 5. Prüfen
- **Eigene Prüfung** vor dem Festschreiben: Pflichtangaben der Tabelle oben (Meldung in Klartext).
- **XSD-Prüfung** der erzeugten XML (Schema der Syntax CII) im Test.
- **Fachliche Prüfung** (Schematron-Regeln der KoSIT) läuft in PHP nicht. Sie gelingt mit dem **KoSIT-Validator** (Java) von Hand, Ablauf: Rechnung eines Kunden mit E-Rechnung-Kennzeichen festschreiben, im Beleg **E-Rechnung (XML)** herunterladen und mit dem KoSIT-Validator samt XRechnung-Konfiguration prüfen (Java nötig). Der Prüfbericht muss „akzeptiert“ zeigen. Beispieldateien werden damit einmal geprüft, bevor der Kunde die erste E-Rechnung verschickt.

## 6. Bibliothek
Eingebaut ist **`horstoeko/zugferd`** (Freigabe des Nutzers am 2026-11-22) (MIT-Lizenz, enthält das CII-Datenmodell und die Profile XRechnung und ZUGFeRD, Abhängigkeiten: `jms/serializer`, `symfony/validator`, `setasign/fpdi`). Sie lässt sich mit PHP 8.4 und den vorhandenen Erweiterungen installieren (Probelauf am 2026-11-22).
## 7. Offene Punkte
- Muss der Kunde **Anhänge** (Fahrtenliste als Anlage der Sammelrechnung) in der XRechnung mitschicken? Kommt mit der Sammelrechnung.
- **Leitweg-ID-Format** prüfen (Grobadressierung, Feinadressierung, Prüfziffer)? Zunächst nur Länge und Zeichen.
- **ZUGFeRD** (PDF/A-3 mit XML): Für ein normkonformes PDF/A-3 braucht es ein Werkzeug, das auf dem Hoster nicht sicher vorhanden ist (dompdf erzeugt kein PDF/A). Folgeetappe nach Klärung.
