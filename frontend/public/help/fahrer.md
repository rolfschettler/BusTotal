# Fahrer und Personal {#fahrer}

Hier verwalten Sie Ihre Fahrerinnen und Fahrer mit Führerschein, Fahrerkarte, Prüffristen und Abwesenheiten. Weil es sich um **Personendaten** handelt, sehen und ändern nur **Administrator und Disponent** diesen Bereich. Buchhaltung und Fahrer haben keinen Zugriff.

## Fahrerliste {#fahrerliste}

Die Liste zeigt 20 Fahrer pro Seite, sortiert nach Nachname.

- **Suche:** Das Suchfeld durchsucht Name, Personalnummer, Ort, E-Mail, Führerscheinnummer und Fahrerkartennummer. Groß- und Kleinschreibung spielen keine Rolle. Treffer sind gelb markiert. Steckt der Treffer in einem Feld, das die Liste nicht zeigt (E-Mail, Führerschein- oder Fahrerkartennummer), erscheint unter dem Namen der Hinweis „Treffer in …“.
- **Beschäftigung:** Der Filter zeigt nur Vollzeit, Teilzeit, Aushilfen oder Externe.
- **Fristen:** Der Filter zeigt Fahrer mit **abgelaufenen** oder **bald fälligen** [Fristen](#fristen).
- **Archivierte:** Mit diesem Häkchen sehen Sie statt der aktiven die [archivierten](#archivieren) Fahrer.
- **Heute:** „Abwesend“ steht bei Fahrern, die heute Urlaub haben, krank oder in Schulung sind (siehe [Abwesenheiten](#abwesenheiten)).
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
| Führerschein | Ablauf der Fahrerlaubnis |
| P-Schein | Führerschein zur Fahrgastbeförderung |
| Fahrerkarte | Karte für den digitalen Tacho |
| Code 95 | Berufskraftfahrer-Weiterbildung |
| Untersuchung | ärztliche Untersuchung |

Unter jedem Datum zeigt die Software, wie viel Zeit bleibt („in 12 Tagen“). Eine Frist gilt als **abgelaufen**, wenn das Datum vor heute liegt, als **bald fällig** in den nächsten **30 Tagen** und sonst als **in Ordnung**.

Auf der [Startseite](/hilfe/start#startseite) zeigt die Kachel „Fällige Fristen beim Personal“ alle abgelaufenen und bald fälligen Fristen, die dringendsten zuerst. Ohne fällige Fristen erscheint sie nicht. Nach einer erneuerten Karte oder bestandenen Prüfung tragen Sie einfach das neue Datum ein.

> Die Software **erinnert** an Fristen, ersetzt aber nicht Ihre eigene Überwachung. Welche Nachweise und Fristen für Ihre Fahrer tatsächlich gelten, klären Sie bitte fachlich ab (Entwurf zur Prüfung durch den Auftraggeber).

## Abwesenheiten {#abwesenheiten}

Im Reiter **Abwesenheiten** tragen Sie Urlaub, Krankheit, Schulung oder Sonstiges ein, jeweils mit **erstem und letztem Tag** (der letzte Tag zählt mit; ein einzelner Tag hat denselben Beginn und dasselbe Ende).

- Zwei Abwesenheiten **desselben Fahrers dürfen sich nicht überschneiden**. Eine Abwesenheit, die direkt am Folgetag beginnt, ist erlaubt.
- Hat ein Fahrer heute eine Abwesenheit, steht in der Liste „Abwesend“ und im Kopf seiner Seite „Heute abwesend“.
- Später prüft die Disposition anhand dieser Einträge, ob ein Fahrer verfügbar ist.

## Fahrer archivieren {#archivieren}

Fahrer werden nie gelöscht, sondern **archiviert**, zum Beispiel bei Austritt: Sie verschwinden aus der Liste und aus den Fristen der Startseite, bleiben aber für vergangene Fahrten und Belege erhalten. Die Abwesenheiten bleiben dabei erhalten. Den Archivbestand zeigt das Häkchen **Archivierte**. Nur der **Administrator** kann einen Fahrer **wiederherstellen**.

Jede Änderung wird im Änderungsprotokoll festgehalten.

> **Datenschutz:** Ob und wie lange Personendaten ehemaliger Mitarbeiter aufbewahrt werden dürfen, ist noch nicht entschieden. Eine Anonymisierung wie bei Kunden gibt es für Fahrer noch nicht.
