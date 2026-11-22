// Erzeugt aus EINER Quelle die Word-Fassung und die Markdown-Fassung der Fragen an den Steuerberater.
// Aufruf (im Ordner dieser Datei, einmalig `npm install docx`):
//   node build-fragen.js ../Fragen-Steuerberater.docx ../fragen-steuerberater.md
const fs = require('fs');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, Table, TableRow, TableCell,
  WidthType, BorderStyle, ShadingType, LevelFormat, AlignmentType, Footer, PageNumber,
} = require('docx');

const FONT = 'Arial';
const W = 9638;

// ---------------------------------------------------------------- Inhalt (eine Quelle für Word und Markdown)
const intro = [
  'Wir entwickeln eine Software für Busunternehmen (Angebot, Auftrag, Fahrtauftrag, Rechnung, Abrechnung für das Finanzamt). Das Busunternehmen fährt Gruppen mit eigenen Bussen (Ausflüge, Vereins- und Schulfahrten, Werksverkehr, Auslandsfahrten), setzt bei Bedarf Fremdbusse ein, verkauft teils Reisen mit eingekauften Leistungen (Hotel, Eintritte, Reiseleiter) und rechnet den Werksverkehr am Monatsende gesammelt ab.',
  'Wir haben Gesetzestexte (UStG, UStDV, AO, HGB, DSGVO) und den Umsatzsteuer-Anwendungserlass (Abschnitte 3b.1, 12.13, 12.14, 13.5, 14.x, 14b.1, 14c.x, 17.x, 25.1 bis 25.5; Abdruck Handausgabe 2024) selbst ausgewertet. **Teil A** enthält die Punkte, bei denen wir keine eindeutige Antwort gefunden haben. **Teil B** enthält unsere Auffassung zur Bestätigung (ja, nein oder Anmerkung). Die Fundstellen stehen dabei, damit Sie schnell prüfen können.',
  '**Bitte vorab angeben:** Rechtsform, Soll- oder Istversteuerung, Voranmeldungszeitraum (Monat oder Quartal), ungefährer Vorjahresumsatz, Anteile der Leistungsarten (Charter, Linien- und Schulverkehr, Werksverkehr, Auslandsfahrten, Reisen mit Hotel).',
  '**Hinweis:** Alle Beträge sind erfundene Beispiele. Die Fassungen stammen nicht aus dem Bundessteuerblatt; Änderungen nach 2024 sind nicht geprüft. Wir bitten zu jeder Antwort um die Rechtsgrundlage.',
];

const A = [
  { t: 'Fremdbus allein und Mietbus mit Fahrer', c: [
    ['p', 'Ein Kunde bucht bei uns **nur eine Fahrt** (3.000 €, kein Hotel, kein Programm). Wir fahren sie mit dem Bus eines **anderen Busunternehmers**, der uns 2.000 € zuzüglich 19 % Umsatzsteuer berechnet. Variante: Wir mieten Bus **mit Fahrer** (Gestellungsvertrag), bestimmen aber Ablauf und tragen die Verantwortung gegenüber dem Kunden.'],
    ['p', 'Liegt Margenbesteuerung nach § 25 UStG vor, und dürfen wir die Vorsteuer aus der Rechnung des Subunternehmers abziehen? Wann gilt ein gemieteter Bus mit Fahrer als **eigenes Beförderungsmittel**?'],
    ['a', 'Reine Beförderung ist kein Leistungsbündel, also kein § 25, Vorsteuer regulär (UStAE 25.1 Abs. 2 S. 3 und 4). Ein Bus mit Fahrer im Gestellungsvertrag ist ein eigenes Beförderungsmittel, wenn wir Weisungsbefugnis und Verantwortung haben (25.1 Abs. 8 S. 9). In einer Reise mit eingekauftem Hotel ist der Fremdbus Reisevorleistung, die Vorsteuer daraus nicht abziehbar (25.1 Abs. 8 S. 10 und 11; 25.4 Abs. 1).'],
  ] },
  { t: 'Maut, Parken, Fähre, Eintritte', c: [
    ['p', 'Der Fahrer legt unterwegs 80 € Parkgebühren und 60 € Maut aus. Der Kunde bekommt sie berechnet oder sie sind im Preis enthalten. Bei einer Reise nach § 25 zahlen wir zusätzlich Eintritte und Fährkosten für die Gäste.'],
    ['p', 'Sind Maut und Parken für den eigenen Bus **Betriebskosten** (keine Reisevorleistung, Vorsteuer abziehbar)? Sind Eintritte und Fähre für die Gäste **Reisevorleistungen**? Wie ist die **Weiterberechnung** an den Kunden zu behandeln (durchlaufender Posten oder Entgelt der Fahrt)?'],
    ['a', 'Betriebskosten für den eigenen Bus sind keine Vorleistung, weil sie den Reisenden nur mittelbar zugutekommen (25.1 Abs. 9 S. 4; 25.4 Abs. 1 S. 3 und 4). Eintritte und Fähre für Gäste sind unmittelbar zugutekommend. Zur Weiterberechnung fanden wir nichts.'],
  ] },
  { t: 'Voranmeldungszeitraum der Marge', c: [
    ['p', 'Eine Reise geht vom 28.12. bis 3.1. In welchen **Voranmeldungszeitraum** gehört die Marge (Reiseende, Reisebeginn, anteilig)? Gibt es Teilleistungen bei mehrtägigen Reisen?'],
    ['a', 'Leistungsausführung = Reiseende, Anzahlungen schon bei Vereinnahmung (§ 13 Abs. 1 Nr. 1 Buchst. a; 25.3 Abs. 5). Eine ausdrückliche Regel für den Zeitpunkt der Marge fanden wir nicht.'],
  ] },
  { t: 'Nachweis der Streckenanteile (Inland, Ausland, Drittland)', c: [
    ['p', 'Charterfahrt München–Wien–München, zwei Tage, 800 km, davon 350 km Inland und 450 km Österreich. Der Fahrer notiert die **Kilometerstände bei Grenzeintritt und Grenzaustritt** im Fahrtauftrag, das Büro erfasst sie nach. Bei Reisen mit Fremdbus durch die Schweiz gilt dasselbe für den Drittlandsanteil.'],
    ['p', 'Genügen diese Angaben als **Nachweis** für die Aufteilung nach § 3b UStG und für die Drittlandsaufteilung nach § 25 Abs. 2 (25.2 Abs. 3)? Verlangen Sie zusätzlich Fahrtenschreiberdaten oder eine Routenberechnung?'],
    ['a', 'Der Erlass (3b.1) nennt keine Anforderungen an den Nachweis der Streckenlängen, nur die Aufteilung im Verhältnis der Streckenanteile. A 25.5 Abs. 9 verlangt bei gemischten Vorleistungen die Dokumentation des Aufteilungsmaßstabs.'],
  ] },
  { t: 'Darstellung des Auslandsanteils auf der Rechnung', c: [
    ['p', 'Preis 2.000 € netto, 350 von 800 km im Inland. Wie sieht die Rechnung aus: Inlandsanteil 875 € mit 19 % Steuer (166,25 €), Auslandsanteil 1.125 € als „nicht steuerbar“? Welchen Wortlaut verlangen Sie für den nicht steuerbaren Teil? Dürfen wir alternativ vom Bruttopreis ausgehen (3b.1 Abs. 6 S. 1 Nr. 2)? Muss die Methode je Verkehrsart gleich bleiben?'],
    ['a', 'Aufteilung im Verhältnis der Streckenlängen, ohne die Fahrt vom und zum Betriebshof ohne Fahrgäste (3b.1 Abs. 6 S. 1 Nr. 1 S. 3 bis 5). Zur Darstellung des nicht steuerbaren Teils in § 14 Abs. 4 Nr. 7 und 8 UStG steht nichts Ausdrückliches.'],
  ] },
  { t: 'Umsatzsteuer im Ausland', c: [
    ['p', 'Die Beförderung in Österreich (und anderen Ländern) ist dort steuerbar. Muss ein deutsches Busunternehmen sich dort **registrieren** und Umsatzsteuer abführen? Gibt es Vereinfachungen für Gelegenheitsverkehr? Was bedeutet das für den Preis der Auslandsstrecke?'],
    ['a', 'Dazu haben wir nichts recherchiert. Der Erlass erwähnt nur die „im Ausland für den nicht steuerbaren Teil zu zahlende Steuer“ (3b.1 Abs. 6 S. 2 Nr. 1).'],
  ] },
  { t: 'Sightseeing, Rundfahrt, Transferdienste, Schulträger', c: [
    ['p', 'Ist eine **Stadtrundfahrt mit festen Haltestellen** oder ein **Flughafentransfer mit festem Fahrplan** Linienverkehr (7 % bis 50 km) oder Gelegenheitsverkehr (19 %)? Welche **Genehmigung** muss der Unternehmer vorweisen? Gilt die Ermäßigung für **Fahrten im Auftrag eines Schulträgers**, wenn die Schüler kein Entgelt zahlen, ohne weitere Voraussetzungen?'],
    ['a', 'Linienverkehr verlangt regelmäßige Verbindung mit bestimmten Haltestellen, Genehmigung nötig, Mietomnibus-Genehmigung genügt nicht (12.13 Abs. 4 und 5). Schülerfahrten Wohnung–Schule und Fahrten für Schulträger ohne Entgelt der Fahrgäste gelten wie Linienverkehr (12.13 Abs. 4 S. 3 Nr. 2; Abs. 5 S. 9 Nr. 4). Rundfahrten mit Bussen sind im Text nicht entschieden.'],
  ] },
  { t: 'Einzelmarge oder Vereinfachung', c: [
    ['p', 'Wir verkaufen viele Gruppenreisen mit einheitlichem Kalkulationsaufschlag. Sollen wir die **Marge je Reise** ermitteln oder die **Vereinfachung** nutzen (mehrere Reisen mit einheitlichem Kalkulationssatz zusammenfassen)? Die Vereinfachung gilt dann für **alle** Reisen des Besteuerungszeitraums, negative Margen werden nicht verrechnet. Wann lohnt welche Methode, und ist ein Wechsel zwischen Zeiträumen möglich?'],
    ['a', 'UStAE 25.3 Abs. 1 S. 3 bis 8. Gesamt- oder Gruppenmarge ist nach unserer Lesung seit 2022 entfallen.'],
  ] },
  { t: 'Bar kassierte Zusatzfahrt', c: [
    ['p', 'Der Fahrer fährt auf Wunsch der Gäste eine **Sonderfahrt vor Ort** im Inland (120 €) und kassiert bar. Wie wird sie belegt (Quittung, nachträgliche Rechnung) und der Hauptfahrt zugeordnet? Steuersatz 19 %? Was gilt, wenn die Sonderfahrt im Ausland stattfindet?'],
    ['a', 'Quittung nach § 33 UStDV (bis 250 €) mit Steuersatz; Ort der Leistung dort, wo sie bewirkt wird (§ 3b Abs. 1). Zur Zuordnung zur Hauptfahrt fanden wir nichts.'],
  ] },
  { t: 'Charterbeleg als Fahrausweis, E-Rechnung bei Margenbesteuerung', c: [
    ['p', 'Ist ein **Charterbeleg** (Gruppenfahrt, Rechnung mit Steuer in einer Summe) ein **Fahrausweis** im Sinne von § 34 UStDV, der immer als sonstige Rechnung (auch auf Papier oder als PDF) erteilt werden darf? Muss eine **Rechnung nach § 25** (kein Steuerausweis) an einen Unternehmerkunden ab 2027 als E-Rechnung ausgestellt werden?'],
    ['a', '§ 34 UStDV nennt Charterfahrten nicht. § 14 Abs. 2 S. 2 Nr. 1 UStG kennt für § 25 keine Ausnahme.'],
  ] },
  { t: 'Aufbewahrungsfristen', c: [
    ['p', '**Rechnungen:** 8 Jahre (§ 14b Abs. 1 UStG) oder 10 Jahre (UStAE 14b.1 Abs. 2 S. 1)? Welche Frist gilt für **Angebote und Auftragsbestätigungen**, für **Fahrtaufträge mit Fahrerangaben** (Kilometerstände, Zeiten, Kosten) und **Kostenbelege** (Maut, Hotel) sowie für die **Margenaufzeichnungen** nach § 25 Abs. 5?'],
    ['a', 'Buchungsbelege 8 Jahre, Handelsbriefe 6 Jahre, sonstige steuerlich bedeutsame Unterlagen 6 Jahre (§ 147 AO; § 257 HGB), Ablaufhemmung bei laufender Festsetzungsfrist. Fahrtaufträge und Kostenbelege nennen die Normen nicht; wir behandeln sie vorsichtshalber wie Rechnungen.'],
  ] },
  { t: 'E-Rechnung: Betroffenheit und Format', c: [
    ['p', 'Ab dem 1.1.2027 müssen Aussteller mit mehr als 800.000 € Vorjahresumsatz E-Rechnungen an inländische Unternehmer ausstellen (§ 27 Abs. 38 UStG). Trifft das auf unser Unternehmen zu? Gilt die Pflicht für **Kommunen, Schulträger und Vereine** als Empfänger (sind sie Unternehmer im Sinne der Vorschrift)? Welches **Format** (XRechnung, ZUGFeRD, welche Version) und welches **Archivierungsverfahren** (Originalformat zusätzlich zum PDF) empfehlen Sie? Reicht die **Zustimmung** des Empfängers als Kundenmerkmal?'],
    ['a', '§ 14 Abs. 1 S. 2 bis 6 UStG (strukturiertes Format nach EN 16931). Die aktuellen BMF-Schreiben konnten wir nicht lesen.'],
  ] },
  { t: 'Verfahrensdokumentation (GoBD)', c: [
    ['p', 'In den Gesetzen fanden wir keine Pflicht zu einer Verfahrensdokumentation, nur die Aufbewahrung von „Arbeitsanweisungen und Organisationsunterlagen“ (§ 147 Abs. 1 Nr. 1 AO). Welchen **Umfang** erwarten Sie? Reicht eine **Vorlage der Software** (Belegkette, Festschreiben, Archiv, Prüfsumme, Zugriffsrechte, Datensicherung), die der Betrieb ergänzt?'],
    ['a', 'Die GoBD haben wir nicht gelesen.'],
  ] },
  { t: 'DATEV, Konten, Voranmeldung', c: [
    ['p', 'Kontenrahmen SKR 03 oder 04? Ein Fachartikel nennt für § 25 die Konten 8198 (SKR 03) und 4133 (SKR 04) für Umsatzerlöse 19 %, 3220 und 5220 für Reisevorleistungen und 8193 und 4138 für die Ausbuchung. Stimmt das? Welche **Steuerschlüssel** gelten für Marge, steuerfreien Teil, nicht steuerbaren Auslandsanteil? Welche **Kennzahlen der Umsatzsteuer-Voranmeldung** und der Erklärung gelten für Margenumsätze und für nicht steuerbare Auslandsumsätze (getrennter Ausweis ab 2026)? **Debitorenbereich** ab 10000 oder gleich der Kundennummer?'],
    ['a', 'Kontenangaben stammen aus einem Fachartikel, widersprüchlich und nicht verifiziert. Kennzahlen und Steuerschlüssel fanden wir nicht.'],
  ] },
];

const B = [
  ['Margenbesteuerung: Marge = Reisepreis minus Reisevorleistungen, **beide brutto**; Steuer = Marge × 19/119; Vorsteuer aus Reisevorleistungen nicht abziehbar, aus Gemeinkosten und Eigenleistungen (z. B. Kauf eines Reisebusses) abziehbar.', '§ 25 Abs. 3 und 4; § 12 Abs. 1; UStAE 25.3 Abs. 1, 25.4 Abs. 1 und 4'],
  ['Die Marge wird **je Besteller und Reise** ermittelt (tritt der Besteller im eigenen Namen für Mitreisende auf), sonst je Reisendem. Negative Margen sind nicht verrechenbar, auch bei negativer Marge bleibt die Vorsteuer aus Vorleistungen ausgeschlossen.', 'UStAE 25.3 Abs. 1 S. 1 bis 6; 25.4 Abs. 2 S. 3'],
  ['**Eigenleistung** (eigener Bus) in einer Reise mit eingekauftem Hotel wird herausgerechnet: im Verhältnis der Einzelverkaufspreise, sonst der Kosten einschließlich Umsatzsteuer, ohne Gemeinkosten und Unternehmerlohn. Beispiel 600 €, Bus 130 €, Hotel 500 €: Eigenleistung 123,81 €, Marge 76,19 €, Steuer 12,16 €.', 'UStAE 25.1 Abs. 11; 25.3 Abs. 2'],
  ['**Anzahlung** bei Reisen: Steuer bei Vereinnahmung; Marge anteilig aus der **kalkulierten** Marge, bei Bedarf geschätzt, später korrigiert; Einmalberichtigung zulässig, wenn keine negativen Einzelmargen entstehen.', 'UStAE 25.3 Abs. 5; 13.5'],
  ['**Stornogebühr** ist echter Schadensersatz, kein Entgelt. Nicht ersparbare Vorleistungen, die durch die Gebühr entschädigt werden, sind keine Vorleistungen mehr.', 'UStAE 25.1 Abs. 14; 25.3 Abs. 6'],
  ['**Drittland:** Aufteilung der Marge im Verhältnis der Vorleistungsanteile (Hotel nach Standort, Beförderung nach gefahrenen Strecken); Rechnung an Unternehmer nennt den Drittlandsumfang; Aufzeichnung des Aufteilungsmaßstabs.', 'UStAE 25.2 Abs. 3 und 7; 25.3 Abs. 3; 25.5 Abs. 9; § 72 UStDV'],
  ['**Rechnung nach § 25:** Hinweis „Sonderregelung für Reisebüros“, **kein gesonderter Steuerausweis**; übrige Pflichtangaben nach § 14 Abs. 4 bleiben.', '§ 14a Abs. 6'],
  ['**Steuersatz der Beförderung:** Charter, Ausflug, Gruppenfahrt und Mietbus mit Fahrer 19 %; 7 % nur für genehmigten Linienverkehr und Schüler- und Schulträgerfahrten, jeweils innerhalb einer Gemeinde oder bis 50 km (gemessen an der tatsächlich befahrenen Strecke, bei Auslandsfahrten nur im Inland).', '§ 12 Abs. 2 Nr. 10; UStAE 12.13, 12.14'],
  ['**Grenzüberschreitende Beförderung:** Nur der Inlandsanteil ist steuerbar. Der Preis wird im Verhältnis der Streckenlängen aufgeteilt, ohne die Fahrt vom und zum Betriebshof ohne Fahrgäste. Die Methode bleibt je Verkehrsart einheitlich.', '§ 3b Abs. 1; UStAE 3b.1 Abs. 4 und 6'],
  ['**Anzahlungsrechnung** (reguläre Beförderung): als Anzahlung erkennbar, Steuerbetrag ausgewiesen; die Steuer entsteht erst mit dem **tatsächlichen Zahlungseingang**, die Voranmeldung richtet sich danach. Die Endrechnung setzt Entgelt und Steuer der Anzahlungen ab; wird eine Anzahlung nicht bezahlt, entsteht keine Steuer nach § 14c.', '§ 13 Abs. 1 Nr. 1; § 14 Abs. 5; UStAE 13.5; 14.8 Abs. 1, 2, 4, 5, 7, 10'],
  ['Die **Auftragsbestätigung mit vorläufigem Preis** ist keine Rechnung. Die Rechnung über die Ist-Werte ist die erste Rechnung (keine Berichtigung, keine Gutschrift); Anzahlungsrechnungen werden abgesetzt.', '§ 14 Abs. 1 S. 1; UStAE 14.1; 14.8 Abs. 7'],
  ['**Sammelrechnung** (Werksverkehr): Rechnung mit selbst erstellter **Anlage** (Fahrtenliste mit Datum und Strecke), ausdrücklicher Verweis; Leistungszeitraum Kalendermonat; Positionen nach Steuersatz getrennt.', 'UStAE 14.5 Abs. 1, 15, 16, 18; § 31 Abs. 1 und 4 UStDV'],
  ['**Storno und Korrektur:** Dokument mit Minuszeichen, Verweis auf die Nummer der Ursprungsrechnung, eigener Nummernkreis zulässig, Bezeichnung „Stornorechnung“ statt „Gutschrift“; Steuerkorrektur mit der tatsächlichen Rückzahlung.', 'UStAE 14c.1 Abs. 4a; 14.11 Abs. 1; 14.5 Abs. 11; 14.3 Abs. 2 S. 5; 17.1 Abs. 2'],
  ['**Rundung:** Steuer je Steuersatz aus der Summe der Nettobeträge, kaufmännisch auf den Cent (nicht je Position).', 'UStAE 14.5 Abs. 20, 21; § 32 UStDV (nicht gelesen)'],
  ['**Steuerbefreiungshinweis** umgangssprachlich genügt, die Vorschrift muss nicht genannt werden. **Kleinbetragsrechnung** bis 250 € für Barzahlungen genügt mit wenigen Angaben. An **Privatpersonen** ist eine volle Rechnung zulässig; eine Rechnungspflicht besteht nur gegenüber Unternehmern und juristischen Personen (innerhalb von 6 Monaten).', 'UStAE 14.5 Abs. 20; 14.6; § 33 UStDV; § 14 Abs. 2'],
  ['**Archiv:** Das beim Festschreiben einmal erzeugte PDF mit SHA-256-Prüfsumme, nie neu erzeugt, genügt den Anforderungen an Unveränderbarkeit und Aufbewahrung des Doppels. Für Betriebsprüfungen stellt die Software einen maschinell auswertbaren Datenexport bereit.', '§ 146 Abs. 4 AO; § 14b Abs. 1 UStG; § 147 Abs. 2 und 6 AO'],
  ['**Datenschutz:** Auf Löschverlangen wird der Kundenstamm anonymisiert, der Beleg behält die Kopie von Name und Anschrift **nur** für die Aufbewahrung; nach Fristende werden Belegkopien und Archiv-PDFs gelöscht.', 'Art. 17 Abs. 3 Buchst. b, Art. 18 DSGVO; § 35 BDSG'],
];

// ---------------------------------------------------------------- Markdown
function md() {
  const out = [];
  out.push('# Fragen an Fachberater (Steuerberater, Verband)');
  out.push('');
  out.push('Stand: 2026-11-22. **Diese Datei und `Fragen-Steuerberater.docx` werden aus `fragen-bauen/build-fragen.js` erzeugt (eine Quelle für Word und Markdown). Änderungen dort machen, nicht hier.** Aufruf: siehe Kopfkommentar der Datei. Ergebnisse der Recherche: `befoerderung-ergebnisse.md`, `rechnungen-ergebnisse.md`, `aufbewahrung-erechnung-ergebnisse.md`, `ustae-25-ergebnisse.md`.');
  out.push('');
  intro.forEach((t) => { out.push(t); out.push(''); });
  out.push('## Teil A: Fragen, bei denen wir keine eindeutige Antwort gefunden haben');
  out.push('');
  A.forEach((q, i) => {
    out.push(`### A${i + 1}. ${q.t}`);
    out.push('');
    q.c.forEach(([k, t]) => {
      out.push(k === 'a' ? `*Unsere Auffassung bzw. Fundstelle:* ${t}` : t);
      out.push('');
    });
    out.push('**Antwort und Rechtsgrundlage:** ');
    out.push('');
  });
  out.push('## Teil B: Unsere Auffassung zur Bestätigung');
  out.push('');
  out.push('| Nr. | Unsere Auffassung | Fundstelle | Bestätigt? (ja / nein / Anmerkung) |');
  out.push('|---|---|---|---|');
  B.forEach(([t, f], i) => out.push(`| B${i + 1} | ${t} | ${f} |  |`));
  out.push('');
  return out.join('\n');
}

// ---------------------------------------------------------------- Word
function runs(text, extra = {}) {
  const out = [];
  for (const part of text.split(/(\*\*[^*]+\*\*)/g)) {
    if (!part) continue;
    if (part.startsWith('**')) out.push(new TextRun({ text: part.slice(2, -2), bold: true, font: FONT, ...extra }));
    else out.push(new TextRun({ text: part, font: FONT, ...extra }));
  }
  return out;
}
const p = (t) => new Paragraph({ children: runs(t), spacing: { after: 100 } });
const aPara = (t) => new Paragraph({
  children: [new TextRun({ text: 'Unsere Auffassung bzw. Fundstelle: ', bold: true, italics: true, font: FONT, color: '1F3864', size: 20 }), ...runs(t, { italics: true, color: '1F3864', size: 20 })],
  spacing: { before: 60, after: 100 },
});
const border = { style: BorderStyle.SINGLE, size: 4, color: '808080' };
const borders = { top: border, bottom: border, left: border, right: border };
const margins = { top: 80, bottom: 80, left: 100, right: 100 };

function antwortBox() {
  return new Table({
    width: { size: W, type: WidthType.DXA }, columnWidths: [W],
    rows: [new TableRow({ height: { value: 1100, rule: 'atLeast' }, cantSplit: true, children: [new TableCell({
      width: { size: W, type: WidthType.DXA }, borders, margins,
      shading: { fill: 'F5F5F5', type: ShadingType.CLEAR, color: 'auto' },
      children: [new Paragraph({ children: [new TextRun({ text: 'Antwort und Rechtsgrundlage:', size: 18, color: '595959', font: FONT })] })],
    })] })],
  });
}

function bTable() {
  const cols = [650, 5000, 1988, 2000];
  const head = ['Nr.', 'Unsere Auffassung', 'Fundstelle', 'Bestätigt? ja / nein / Anmerkung'];
  const cell = (w, kids, fill) => new TableCell({
    width: { size: w, type: WidthType.DXA }, borders, margins,
    shading: fill ? { fill, type: ShadingType.CLEAR, color: 'auto' } : undefined, children: kids,
  });
  const rows = [new TableRow({ tableHeader: true, children: head.map((h, i) => cell(cols[i], [new Paragraph({ children: [new TextRun({ text: h, bold: true, size: 18, font: FONT })] })], 'D9E2F3')) })];
  B.forEach(([t, f], i) => rows.push(new TableRow({ cantSplit: true, children: [
    cell(cols[0], [new Paragraph({ children: [new TextRun({ text: `B${i + 1}`, bold: true, size: 18, font: FONT })] })]),
    cell(cols[1], [new Paragraph({ children: runs(t, { size: 18 }) })]),
    cell(cols[2], [new Paragraph({ children: [new TextRun({ text: f, size: 16, font: FONT, color: '404040' })] })]),
    cell(cols[3], [new Paragraph({ children: [] })], 'F5F5F5'),
  ] })));
  return new Table({ width: { size: W, type: WidthType.DXA }, columnWidths: cols, rows });
}

function docx() {
  const ch = [];
  const h1 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun({ text: t, font: FONT })] });
  const h2 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun({ text: t, font: FONT })] });
  ch.push(new Paragraph({ heading: HeadingLevel.TITLE, children: [new TextRun({ text: 'Fragen an Fachberater', font: FONT })] }));
  ch.push(p('Stand: 22.11.2026 · Entwurf zur Abstimmung'));
  ch.push(h1('Hintergrund'));
  intro.forEach((t) => ch.push(p(t)));
  ch.push(h1('Teil A: Fragen ohne eindeutige Antwort'));
  A.forEach((q, i) => {
    ch.push(h2(`A${i + 1}. ${q.t}`));
    q.c.forEach(([k, t]) => ch.push(k === 'a' ? aPara(t) : p(t)));
    ch.push(new Paragraph({ children: [], spacing: { after: 60 } }));
    ch.push(antwortBox());
  });
  ch.push(h1('Teil B: Unsere Auffassung zur Bestätigung'));
  ch.push(p('Bitte je Zeile „ja“, „nein“ oder eine Anmerkung eintragen.'));
  ch.push(bTable());
  return new Document({
    creator: 'Entwurf', title: 'Fragen an Fachberater',
    styles: {
      default: { document: { run: { font: FONT, size: 22 } } },
      paragraphStyles: [
        { id: 'Title', name: 'Title', basedOn: 'Normal', run: { font: FONT, size: 40, bold: true, color: '1F3864' }, paragraph: { spacing: { before: 0, after: 120 } } },
        { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font: FONT, size: 30, bold: true, color: '1F3864' }, paragraph: { spacing: { before: 360, after: 160 }, keepNext: true, outlineLevel: 0 } },
        { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true, run: { font: FONT, size: 24, bold: true }, paragraph: { spacing: { before: 280, after: 100 }, keepNext: true, outlineLevel: 1 } },
      ],
    },
    numbering: { config: [{ reference: 'bul', levels: [{ level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 270 } } } }] }] },
    sections: [{
      properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1134, right: 1134, bottom: 1134, left: 1134 } } },
      footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: 'Seite ', size: 18, font: FONT }), new TextRun({ children: [PageNumber.CURRENT], size: 18, font: FONT })] })] }) },
      children: ch,
    }],
  });
}

const [, , docxPath, mdPath] = process.argv;
fs.writeFileSync(mdPath, md(), 'utf8');
Packer.toBuffer(docx()).then((buf) => { fs.writeFileSync(docxPath, buf); console.log('ok', A.length, 'A-Fragen,', B.length, 'B-Punkte'); });
