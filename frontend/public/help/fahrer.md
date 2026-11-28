# Fahrer und Personal {#fahrer}

Hier verwalten Sie Ihre Fahrerinnen und Fahrer mit Führerschein, Fahrerkarte, Prüffristen und Abwesenheiten. Weil es sich um **Personendaten** handelt, sehen und ändern nur **Administrator und Disponent** diesen Bereich. Buchhaltung und Fahrer haben keinen Zugriff.

## Fahrerliste {#fahrerliste}

Die Liste zeigt 20 Fahrer pro Seite, sortiert nach Nachname.

- **Suche:** Das Suchfeld durchsucht Name, Personalnummer, Ort, E-Mail, Führerscheinnummer und Fahrerkartennummer. Groß- und Kleinschreibung spielen keine Rolle. Treffer sind gelb markiert. Steckt der Treffer in einem Feld, das die Liste nicht zeigt (E-Mail, Führerschein- oder Fahrerkartennummer), erscheint unter dem Namen der Hinweis „Treffer in …“.
- **Beschäftigung:** Der Filter zeigt nur Vollzeit, Teilzeit, Aushilfen oder Externe.
- **Fristen:** Der Filter zeigt Fahrer mit **abgelaufenen** oder **bald fälligen** [Fristen](#fristen).
- **Archivierte:** Mit diesem Häkchen sehen Sie statt der aktiven die [archivierten](#archivieren) Fahrer.
- **Heute:** „Abwesend“ steht bei Fahrern, die heute Urlaub haben, krank oder in Schulung sind (siehe [Abwesenheiten](#abwesenheiten)).
- **Öffnen und archivieren:** Ein Klick auf eine Zeile öffnet den Fahrer, das **Archiv-Symbol** rechts archiviert ihn nach einer Rückfrage. Siehe auch [Listen bedienen](/hilfe/start#bedienung).
- **Sortieren:** Ein Klick auf eine Spaltenüberschrift sortiert die ganze Liste, ein zweiter Klick dreht die Richtung um. Bei „Nächste Frist“ stehen die dringendsten Fahrer oben, Fahrer ohne Frist am Ende.

## Fahrer anlegen {#fahrer-anlegen}

Wählen Sie **Neuer Fahrer**. Pflichtfelder sind **Personalnummer** (Ihre eigene, nur einmal vergebbar), **Vorname** und **Nachname**. Alles andere ist freiwillig, sollte aber für die spätere Planung gepflegt werden. Den Reiter **Abwesenheiten** gibt es, sobald der Fahrer gespeichert ist.

## Stammdaten {#stammdaten}

- **Beschäftigung:** Vollzeit, Teilzeit, Aushilfe oder Extern (Freiberufler).
- **Stundensatz:** Die Kosten je Stunde in Euro. Er dient später als Grundlage der Kalkulation.
- **Führerscheinklassen:** Häkchen für die Klassen, die der Fahrer besitzt (zum Beispiel D und DE für Busse). Die Disposition nutzt sie später, um nur passende Fahrer vorzuschlagen.
- **Führerschein- und Fahrerkartennummer:** nur zum Nachschlagen, sie werden durchsucht.

## Fristen {#fristen}

Für jeden Fahrer tragen Sie ein, **bis wann** ein Nachweis gilt:

| Feld | Bedeutung |
|---|---|
| Führerschein | Ablauf der Fahrerlaubnis. Die Klassen für Busse (D, D1) werden **längstens für fünf Jahre** erteilt. Eine Verlängerung ist frühestens sechs Monate vor Ablauf möglich. Wird der Antrag erst nach Ablauf gestellt, gibt es keine Verlängerung mehr, sondern eine Neuerteilung (§§ 23, 24 FeV). |
| P-Schein | Fahrerlaubnis zur Fahrgastbeförderung nach § 48 FeV: **höchstens fünf Jahre**, jeweils auf Antrag um bis zu fünf Jahre verlängerbar. Der Antrag ist schon sechs Monate vor Ablauf möglich. |
| Fahrerkarte | Karte für den digitalen Tacho. Gültigkeit **fünf Jahre** (§ 5 FPersV). Die Folgekarte beantragen Sie frühestens sechs Monate und spätestens 15 Werktage vor Ablauf. |
| Auslesen | Fahrerkarte auslesen, [alle 28 Tage](#karte-auslesen). |
| Code 95 | Berufskraftfahrer-Weiterbildung: **35 Stunden alle fünf Jahre**, in Blöcken von mindestens sieben Stunden, ohne Prüfung. Seit Mai 2021 gibt es statt der Schlüsselzahl 95 im Führerschein den Fahrerqualifizierungsnachweis. |
| Untersuchung | ärztliche Untersuchung. Sie hängt von Alter und Erlaubnis ab: Für die Busklassen ist bei einer Verlängerung über das **50. Lebensjahr** hinaus ein zusätzlicher Eignungsnachweis nach Anlage 5 Nr. 2 FeV nötig, beim P-Schein ein medizinisches Gutachten beim Erstantrag und bei einer Verlängerung über das **60. Lebensjahr** hinaus. Bei jeder Verlängerung kommt ein Sehtest hinzu. Welche Untersuchungen Ihr Betrieb zusätzlich verlangt (z. B. arbeitsmedizinisch), legen Sie selbst fest. |

Unter jedem Datum zeigt die Software, wie viel Zeit bleibt („in 12 Tagen“). Eine Frist gilt als **abgelaufen**, wenn das Datum vor heute liegt, als **bald fällig** in den nächsten **30 Tagen** (beim [Auslesen der Fahrerkarte](#karte-auslesen) in den nächsten 7 Tagen) und sonst als **in Ordnung**.

Auf der [Startseite](/hilfe/start#startseite) zeigt die Kachel „Fällige Fristen beim Personal“ alle abgelaufenen und bald fälligen Fristen, die dringendsten zuerst. Ohne fällige Fristen erscheint sie nicht. Nach einer erneuerten Karte oder bestandenen Prüfung tragen Sie einfach das neue Datum ein.

> Die Software **erinnert** an Fristen, ersetzt aber nicht Ihre eigene Überwachung. Welche Nachweise und Fristen für Ihre Fahrer tatsächlich gelten, klären Sie bitte fachlich ab (Entwurf zur Prüfung durch den Auftraggeber). Die Software **berechnet keine Fristen**: Sie tragen das Datum aus dem Dokument ein.

**Quellen** (gelesen am 28.11.2026, keine Rechtsberatung, Vorschriften und Verfahren ändern sich, im Einzelfall entscheidet die zuständige Behörde):

- Führerschein: [§ 23 FeV, Geltungsdauer](https://www.gesetze-im-internet.de/fev_2010/__23.html) und [§ 24 FeV, Verlängerung](https://www.gesetze-im-internet.de/fev_2010/__24.html) auf gesetze-im-internet.de
- P-Schein: [§ 48 FeV, Fahrerlaubnis zur Fahrgastbeförderung](https://www.gesetze-im-internet.de/fev_2010/__48.html), dazu das [Merkblatt der Stadt Münster zur Verlängerung](https://www.stadt-muenster.de/kfz/berufliches-fahren/verlaengerung-von-fahrerlaubnissen-zur-fahrgastbefoerderung) und die [Ausarbeitung des Bundestags (WD 7 061/19)](https://bundestag.de/resource/blob/647626/a88bb64f2109250709c152403e2dd0c6/WD-7-061-19-pdf-data.pdf) zu den ärztlichen Nachweisen
- Fahrerkarte: [§ 5 FPersV](https://www.gesetze-im-internet.de/fpersv/__5.html) auf gesetze-im-internet.de, [TÜV SÜD: Fahrerkarte](https://www.tuvsud.com/de-de/branchen/mobilitaet-und-automotive/fahrtenschreiberkarten/fahrerkarte) und [Stadt Köln: Fahrerkarte](https://www.stadt-koeln.de/service/produkt/fahrerkarte) zur Antragsfrist (eine Behörde nennt abweichend vier Wochen: im Zweifel bei der zuständigen Stelle nachfragen)
- Code 95: [IHK Gießen-Friedberg: Berufskraftfahrerqualifikation](https://www.ihk.de/giessen-friedberg/geschaeftsbereiche/standortpolitik/berufszugang-verkehr/berufskraftfahrer-qualifikation), [IHK-Merkblatt zur Weiterbildung](https://www.ihk.de/blueprint/servlet/resource/blob/2272306/412ce44ba4fdb586fd748477e404a491/merkblatt-weiterbildung-berufskraftfahrer-data.pdf) und [Berufskraftfahrerqualifikations-Verordnung](https://www.gesetze-im-internet.de/bkrfqv_2020/BJNR290510020.html)

## Fahrerkarte auslesen {#karte-auslesen}

Die Fahrerkarte muss nach den Sozialvorschriften **spätestens alle 28 Tage** ausgelesen werden, sonst können ältere Daten überschrieben werden. Die Software **liest die Karte nicht aus**, sie erinnert nur:

- Tragen Sie im Feld **Auslesen bis** ein, bis wann spätestens ausgelesen sein muss.
- Mit **Karte ausgelesen** (oben rechts) tragen Sie **heute** als Tag des Auslesens ein. Die nächste Frist liegt dann 28 Tage später. Die Taste ist erst nach dem Speichern aktiv.
- Die Frist erscheint auf der Startseite, sobald nur noch **7 Tage** bleiben.

> Ob die Pflicht für Ihre Fahrer und Einsatzarten gilt und wie lange die Daten aufzubewahren sind, klären Sie mit Ihrem Verband oder der Gewerbeaufsicht.

**Quellen** (gelesen am 28.11.2026, Merkblätter und Ratgeber, keine Gesetzestexte; die genaue Fassung der Verordnung prüfen):

- [IHK Stuttgart: Auslesen von Fahrerkarten und Fahrzeugeinheit](https://www.ihk.de/stuttgart/branchen/verkehrswirtschaft/sozialvorschriften-beschaeftigung/sozialvorschriften/auslesen-von-fahrerkarten-682606): Fahrerkarte spätestens alle 28 Tage, Aufbewahrung im Betrieb ein Jahr
- [Bußgeldkatalog.org: Fahrerkarte auslesen](https://www.bussgeldkatalog.org/lkw-fahrerkarte-auslesen/)
- [EUR-Lex: Lenk- und Ruhezeiten im Straßenverkehr (Zusammenfassung der Verordnung 561/2006)](https://eur-lex.europa.eu/DE/legal-content/summary/driving-time-and-rest-periods-in-the-road-transport-sector.html)

## Abwesenheiten {#abwesenheiten}

Im Reiter **Abwesenheiten** tragen Sie Urlaub, Krankheit, Schulung oder Sonstiges ein, jeweils mit **erstem und letztem Tag** (der letzte Tag zählt mit; ein einzelner Tag hat denselben Beginn und dasselbe Ende).

- Zwei Abwesenheiten **desselben Fahrers dürfen sich nicht überschneiden**. Eine Abwesenheit, die direkt am Folgetag beginnt, ist erlaubt.
- Hat ein Fahrer heute eine Abwesenheit, steht in der Liste „Abwesend“ und im Kopf seiner Seite „Heute abwesend“.
- Später prüft die Disposition anhand dieser Einträge, ob ein Fahrer verfügbar ist.

## Fahrer archivieren {#archivieren}

Fahrer werden nie gelöscht, sondern **archiviert**, zum Beispiel bei Austritt: Sie verschwinden aus der Liste und aus den Fristen der Startseite, bleiben aber für vergangene Fahrten und Belege erhalten. Die Abwesenheiten bleiben dabei erhalten. Den Archivbestand zeigt das Häkchen **Archivierte**. Nur der **Administrator** kann einen Fahrer **wiederherstellen**.

Jede Änderung wird im Änderungsprotokoll festgehalten.

> **Datenschutz:** Ob und wie lange Personendaten ehemaliger Mitarbeiter aufbewahrt werden dürfen, ist noch nicht entschieden. Eine Anonymisierung wie bei Kunden gibt es für Fahrer noch nicht.
