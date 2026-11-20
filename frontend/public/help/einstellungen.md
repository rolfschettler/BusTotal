# Einstellungen {#einstellungen}

Die Einstellungen gelten für Ihre ganze Firma. Administrator, Disponent und Buchhaltung dürfen sie ansehen, **ändern darf sie nur der Administrator**. Für Disponent und Buchhaltung sind die Felder grau und es gibt keine Schaltflächen zum Speichern. Fahrer sehen die Einstellungen nicht.

## Firmendaten {#firmendaten}

Auf dem Reiter **Firmendaten** pflegen Sie Name, Rechtsform, Geschäftsführer, Handelsregister, Adresse, Kontakt, Steuernummer, USt-IdNr. und Bankverbindung. Diese Angaben erscheinen später auf Ihren Belegen.

Weitere Angaben:

- **Zahlungsziel (Tage):** Der Standard für alle Kunden, die kein eigenes Zahlungsziel haben (Voreinstellung 14 Tage).
- **Briefkopf und Fußzeile:** Freie Texte für Belege, zum Beispiel Hinweise oder Geschäftsbedingungen.
- **USt-IdNr.:** zwei Großbuchstaben als Länderkürzel, danach Ziffern oder Buchstaben, zum Beispiel `DE123456789`.

Speichern Sie mit **Speichern** am Ende der Seite. Bei einem Fehler markiert die Seite das betroffene Feld rot.

### IBAN und BIC {#iban}

- Die **IBAN** darf mit oder ohne Leerzeichen eingegeben werden. Die Software prüft die Prüfziffer und lehnt Tippfehler ab. Gespeichert wird sie ohne Leerzeichen und in Großbuchstaben.
- Die **BIC** hat 8 oder 11 Stellen und wird in Großbuchstaben eingegeben, zum Beispiel `COBADEFFXXX`.

## Erscheinungsbild {#erscheinungsbild}

Hier gestalten Sie die Oberfläche in den Farben und mit dem Logo Ihrer Firma. Die Änderung gilt nach dem Speichern für alle Ihre Benutzer, auch auf der Anmeldeseite.

### Markenfarbe {#markenfarbe}

Wählen Sie die Farbe über den Farbwähler oder tragen Sie den Farbcode ein, zum Beispiel `#1d4e89`. Die **Vorschau** zeigt, wie Knöpfe und Links aussehen werden. Die Farbe färbt die Kopfleiste, den aktiven Menüpunkt, Knöpfe und Links.

Die Schrift auf der Markenfarbe wird **automatisch schwarz oder weiß**, je nachdem, was besser lesbar ist. Sie können also auch helle Farben wie Gelb wählen.

### Logo {#logo}

Mit **Logo hochladen …** wählen Sie eine Bilddatei. Das Logo erscheint oben auf der Startseite und auf der Anmeldeseite.

- Erlaubt sind **PNG, JPG und WebP** bis **1 MB**.
- SVG-Dateien sind aus Sicherheitsgründen nicht erlaubt.
- Das Dateiformat wird am Inhalt geprüft, nicht am Dateinamen. Eine umbenannte Textdatei wird abgelehnt.
- Ein neues Logo ersetzt das alte. Mit **Entfernen** nehmen Sie es wieder heraus.

Das Logo wird sofort gespeichert, Sie müssen nicht zusätzlich auf **Speichern** klicken.

## Steuersätze {#steuersaetze}

Ihre Firma startet mit **19 %** (Regelsteuersatz), **7 %** (ermäßigt) und **0 %** (steuerfrei).

- Mit **Speichern** in der Zeile ändern Sie Bezeichnung, Satz oder Aktiv-Kennzeichen.
- Neue Sätze legen Sie in der letzten Zeile mit **Anlegen** an.
- Sätze werden nicht gelöscht, sondern auf **inaktiv** gestellt.

Wenn später Rechnungen erstellt werden, behalten sie den Steuersatz, der bei der Rechnung galt. Eine spätere Änderung hier verändert bereits geschriebene Rechnungen nicht.

## Nummernkreise {#nummernkreise}

Ein Nummernkreis legt fest, wie fortlaufende Nummern aussehen, zum Beispiel für Rechnungen. Es gibt Kreise für Kundennummer, Debitorennummer, Angebot, Auftrag, Auftragsbestätigung, Rechnung und Gutschrift. Für jeden Kreis stellen Sie ein:

| Feld | Bedeutung |
|---|---|
| Präfix | Kürzel vor der Nummer, zum Beispiel `RE`. Erlaubt sind Buchstaben, Ziffern und Bindestrich. |
| Jahr | Ob das Jahr in der Nummer steht. |
| Stellen | Mit wie vielen Stellen die Zahl aufgefüllt wird (`00001` hat 5 Stellen). |
| Neu im Jahr | Ob die Zählung zum Jahreswechsel wieder bei 1 beginnt. |
| Nächste Nr. | Die Zahl, die als Nächstes vergeben wird. |
| Vorschau | So sieht die nächste Nummer aus, zum Beispiel `RE-2026-00001`. |

### Regeln für lückenlose Nummern {#nummern-regeln}

Belegnummern müssen nach den Grundsätzen ordnungsmäßiger Buchführung (GoBD) fortlaufend und ohne Lücken vergeben werden. Deshalb gilt:

- Die **nächste Nummer kann nur erhöht, nie verkleinert werden.** Sonst könnten Nummern doppelt vergeben werden. Versuchen Sie es trotzdem, erscheint ein Hinweis mit der kleinstmöglichen Zahl.
- Eine Nummer wird **nur dann verbraucht, wenn der Beleg wirklich gespeichert wurde.** Scheitert das Speichern, bleibt die Nummer frei.
- Zwei Benutzer, die gleichzeitig einen Beleg anlegen, erhalten nie dieselbe Nummer.

> Ändern Sie Präfix, Jahr oder Stellen nur zu Beginn eines Geschäftsjahres und bevor Belege dieser Art existieren. Bereits vergebene Nummern ändern sich dadurch nicht.

## Preise {#preise}

Der Reiter **Preise** legt fest, wie die Software beim [Angebot aus der Streckenplanung](/hilfe/strecken#angebot-aus-tour) die Positionen vorschlägt. Alle Preise sind netto, ein Preis von **0** schaltet den Bestandteil aus. Nur der Administrator kann sie ändern.

- **Preis je km:** wird mit den Kilometern der Tour multipliziert.
- **Leerfahrt (%):** Zu diesem Anteil werden Leerkilometer (Anfahrt und Rückfahrt zum Betriebshof) berechnet. 100 heißt: voll, 0: gar nicht.
- **Stundensatz:** je Stunde Lenkzeit laut Planung.
- **Tagespauschale:** je Einsatztag.
- **Übernachtung:** je Nacht des Fahrers (Einsatztage minus eins).
- **Mindestpreis:** Liegt die Summe darunter, kommt eine Position „Mindestpreis-Ausgleich“ dazu.
- **Steuersatz:** Vorbelegung der Positionen. Im Beleg lässt er sich je Position ändern.

### Preise je Fahrzeugtyp {#preise-fahrzeugtyp}

Ein Doppeldecker kostet mehr als ein Minibus. Darum können Sie unter **Preise je Fahrzeugtyp** für jeden Typ (Reisebus, Doppeldeckerbus, Midibus, Minibus, Sonstiges) eigene Werte für Preis je km, Stundensatz, Tagespauschale, Übernachtung und Mindestpreis eintragen.

- **Leeres Feld:** Es gilt der Standardpreis darüber. Er steht grau im Feld („Standard: 1.80“).
- **0:** schaltet den Bestandteil für diesen Typ aus, auch wenn der Standardpreis etwas anderes sagt.
- **Leerfahrt (%) und Steuersatz** gelten für alle Typen gleich.
- Welcher Typ gilt, richtet sich nach dem Fahrzeug, das in der Streckenplanung gewählt ist. **Ohne Fahrzeug** gelten die Standardpreise.
- Ein Typ, bei dem alle Felder leer sind, hat keine eigenen Preise. Das Kennzeichen „eigene Preise“ an der Überschrift zeigt, welche Typen abweichen.

> Das ist eine Vorlage zum Ausprobieren, **nicht das endgültige Kalkulationsschema**. Welche Bestandteile Ihr Betrieb abrechnet und mit welchen Sätzen, stimmen Sie mit Ihrer Kalkulation und Ihrem Steuerberater ab. Ein Preis, der zu doppelten Positionen führt (zum Beispiel Preis je km und Stundensatz gleichzeitig), ist Ihre Entscheidung.
