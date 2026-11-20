# Fuhrpark {#fuhrpark}

Hier verwalten Sie Ihre Fahrzeuge samt Ausstattung, Maßen und Prüffristen. Den Bereich sehen Administrator, Disponent und Buchhaltung. **Ändern** dürfen nur Administrator und Disponent, die Buchhaltung kann die Fahrzeuge ansehen (zum Beispiel für den Kostensatz). Fahrer haben hier keinen Zugriff.

## Fahrzeugliste {#fahrzeugliste}

Die Liste zeigt 20 Fahrzeuge pro Seite, sortiert nach Fuhrparknummer.

- **Suche:** Das Suchfeld durchsucht Fuhrparknummer, Kennzeichen, Hersteller, Modell und Fahrgestellnummer. Groß- und Kleinschreibung spielen keine Rolle. Treffer sind gelb markiert. Steckt der Treffer in der Fahrgestellnummer, die die Liste nicht zeigt, erscheint unter dem Kennzeichen der Hinweis „Treffer in Fahrgestellnr.“.
- **Art:** Der Filter zeigt nur eine Fahrzeugart, zum Beispiel nur Reisebusse.
- **Fristen:** Der Filter zeigt Fahrzeuge mit **abgelaufenen** oder **bald fälligen** [Fristen](#fristen).
- **Archivierte:** Mit diesem Häkchen sehen Sie statt der aktiven die [archivierten](#archivieren) Fahrzeuge.
- **Sortieren:** Ein Klick auf eine Spaltenüberschrift sortiert die ganze Liste, ein zweiter Klick dreht die Richtung um. Bei „Nächste Frist“ stehen die dringendsten Fahrzeuge oben, Fahrzeuge ohne Frist am Ende.
- **Aktionsspalte:** Der **Stift** öffnet das Fahrzeug (die Buchhaltung sieht ein Auge: nur ansehen), das **Archiv-Symbol** [archiviert](#archivieren) es nach einer Rückfrage.

## Fahrzeug anlegen {#fahrzeug-anlegen}

Wählen Sie **Neues Fahrzeug**. Pflichtfelder sind:

- **Fuhrparknummer** – Ihre eigene Nummer, zum Beispiel `B-01`. Sie darf nur einmal vorkommen.
- **Kennzeichen** – wird automatisch in Großbuchstaben geschrieben (`m-ab 123` wird `M-AB 123`) und darf nur einmal vorkommen. Das gilt auch für archivierte Fahrzeuge.
- **Sitzplätze** – ohne Fahrer.

Alles andere ist freiwillig, sollte aber für die spätere Planung gepflegt werden.

## Stammdaten {#stammdaten}

- **Art:** Reisebus, Doppeldeckerbus, Midibus, Minibus / Kleinbus oder Sonstiges.
- **Fahrgestellnummer (VIN):** genau 17 Zeichen aus Ziffern und Buchstaben, **ohne I, O und Q**. Die Software prüft das.
- **Kostensatz je km:** Ihre Kosten pro gefahrenem Kilometer in Euro. Er dient später als Grundlage der Kalkulation.
- **Ausstattung:** Häkchen für Klimaanlage, WC, Bordküche, WLAN, Steckdosen, Video, Rollstuhllift und Anhängerkupplung. Sie sind später für die Disposition wichtig, wenn ein Kunde bestimmte Ausstattung wünscht.
- **Maße und Gewicht:** Länge, Breite, Höhe in Metern und das zulässige Gesamtgewicht in kg. Die Streckenplanung braucht sie später, um Brücken und Gewichtsbeschränkungen zu beachten.

## Fristen {#fristen}

Für jedes Fahrzeug tragen Sie ein, **bis wann** die nächste Prüfung fällig ist:

| Feld | Bedeutung |
|---|---|
| HU | Hauptuntersuchung nach § 29 StVZO. Die Abstände je Fahrzeugart regelt Anlage VIII (für Kraftomnibusse dort nachlesen). Das Datum steht im Prüfbericht bzw. auf der Plakette. |
| SP | Sicherheitsprüfung, ebenfalls nach Anlage VIII StVZO. |
| Tacho | Prüfung des Fahrtenschreibers nach § 57b StVZO: mindestens einmal innerhalb von **zwei Jahren**. Die Frist endet erst mit Ablauf des Monats, in dem die letzte Prüfung zwei Jahre zurückliegt. Eine Nachprüfung ist außerdem nach jeder Reparatur der Anlage und bei Änderung des Kennzeichens nötig. |
| Auslesen | Fahrzeugeinheit des Fahrtenschreibers auslesen, [alle 90 Tage](#tacho-auslesen). |
| Wartung | nächste Inspektion. Dafür gibt es **keine gesetzliche Frist**, es gelten die Vorgaben des Herstellers und Ihrer Werkstatt. |

Unter jedem Datum zeigt die Software, wie viel Zeit bleibt („in 12 Tagen“). Eine Frist gilt als

- **abgelaufen**, wenn das Datum vor heute liegt,
- **bald fällig**, wenn sie heute oder in den nächsten **30 Tagen** abläuft (beim [Auslesen](#tacho-auslesen) in den nächsten 14 Tagen),
- **in Ordnung** sonst.

Auf der [Startseite](/hilfe/start#startseite) zeigt die Kachel „Fällige Fristen im Fuhrpark“ für Administrator und Disponent alle abgelaufenen und bald fälligen Fristen, die dringendsten zuerst. Ohne fällige Fristen erscheint die Kachel nicht. Nach einer bestandenen Prüfung tragen Sie einfach das neue Datum ein.

> Die Software **erinnert** an Fristen, ersetzt aber nicht Ihre eigene Überwachung. Prüfen Sie die Angaben fachlich mit Ihrer Werkstatt. Die Software **berechnet keine Fristen**: Sie tragen das Datum aus dem Prüfbericht ein.

**Quellen** (gelesen am 28.11.2026, keine Rechtsberatung, Gesetze ändern sich):

- [§ 29 StVZO, Untersuchung der Fahrzeuge](https://www.gesetze-im-internet.de/stvzo_2012/__29.html), [Anlage VIII (Hauptuntersuchung, Sicherheitsprüfung)](https://www.gesetze-im-internet.de/stvzo_2012/anlage_viii.html) und [Anlage VIIIa](https://www.gesetze-im-internet.de/stvzo_2012/anlage_viiia.html) auf gesetze-im-internet.de
- [§ 57b StVZO, Prüfung der Fahrtenschreiber](https://www.gesetze-im-internet.de/stvzo_2012/__57b.html) auf gesetze-im-internet.de

## Fahrzeugeinheit auslesen {#tacho-auslesen}

Der Massenspeicher der Fahrzeugeinheit des digitalen Fahrtenschreibers muss nach den Sozialvorschriften **spätestens alle 90 Tage** ausgelesen werden, die Daten sind im Betrieb ein Jahr aufzubewahren. Die Software **liest den Fahrtenschreiber nicht aus**, sie erinnert nur:

- Tragen Sie im Feld **Auslesen bis** ein, bis wann spätestens ausgelesen sein muss.
- Mit **Einheit ausgelesen** (Tastenleiste, rechts) tragen Sie **heute** als Tag des Auslesens ein. Die nächste Frist liegt dann 90 Tage später. Die Taste ist erst nach dem Speichern aktiv, damit keine Eingaben verloren gehen.
- Die Frist erscheint auf der Startseite, sobald nur noch **14 Tage** bleiben.

> Welche Fahrzeuge einen Fahrtenschreiber brauchen und wie lange Daten aufzubewahren sind, klären Sie mit Ihrem Verband oder der Gewerbeaufsicht.

**Quellen** (gelesen am 28.11.2026, Merkblätter und Ratgeber, keine Gesetzestexte; die genaue Fassung der Verordnung prüfen):

- [IHK Stuttgart: Auslesen von Fahrerkarten und Fahrzeugeinheit](https://www.ihk.de/stuttgart/branchen/verkehrswirtschaft/sozialvorschriften-beschaeftigung/sozialvorschriften/auslesen-von-fahrerkarten-682606): Fahrzeugeinheit spätestens alle 90 Tage, Aufbewahrung im Betrieb ein Jahr
- [Bußgeldkatalog.org: Fahrerkarte auslesen](https://www.bussgeldkatalog.org/lkw-fahrerkarte-auslesen/): Fristen 28 Tage (Karte) und 90 Tage (Fahrzeugeinheit)
- [EUR-Lex: Lenk- und Ruhezeiten im Straßenverkehr (Zusammenfassung der Verordnung 561/2006)](https://eur-lex.europa.eu/DE/legal-content/summary/driving-time-and-rest-periods-in-the-road-transport-sector.html)

## Fahrzeug archivieren {#archivieren}

Fahrzeuge werden nie gelöscht, sondern **archiviert**: Sie verschwinden aus der Liste und aus den Fristen der Startseite, bleiben aber für spätere Aufträge und Belege erhalten. Zum Beispiel, wenn Sie ein Fahrzeug verkauft haben. Den Archivbestand zeigt das Häkchen **Archivierte** in der Liste. Nur der **Administrator** kann ein Fahrzeug **wiederherstellen**.

Jede Änderung an einem Fahrzeug wird im Änderungsprotokoll festgehalten.
