# Einstellungen {#einstellungen}

Die Einstellungen gelten für Ihre ganze Firma. Administrator, Disponent und Buchhaltung dürfen sie ansehen, **ändern darf sie nur der Administrator**. Für Disponent und Buchhaltung sind die Felder grau und es gibt keine Schaltflächen zum Speichern. Fahrer sehen die Einstellungen nicht.

## Firmendaten {#firmendaten}

Auf dem Reiter **Firmendaten** pflegen Sie Name, Rechtsform, Geschäftsführer, Handelsregister, Adresse, Kontakt, Steuernummer, USt-IdNr. und Bankverbindung. Diese Angaben erscheinen später auf Ihren Belegen.

Weitere Angaben:

- **Bundesland:** Das Land Ihres Betriebssitzes. Es bestimmt, welche Feiertage die [Preisfindung](/hilfe/einstellungen#preise-zuschlaege) erkennt, denn Feiertage gelten nicht in jedem Land. Ohne Angabe werden nur Samstag und Sonntag erkannt.
- **Zahlungsziel (Tage):** Der Standard für alle Kunden, die kein eigenes Zahlungsziel haben (Voreinstellung 14 Tage).
- **Briefkopf und Fußzeile:** Freie Texte für Belege, zum Beispiel Hinweise oder Geschäftsbedingungen.
- **USt-IdNr.:** zwei Großbuchstaben als Länderkürzel, danach Ziffern oder Buchstaben, zum Beispiel `DE123456789`.
- **Für die E-Rechnung** brauchen Sie in den Firmendaten zusätzlich **Telefon**, **E-Mail**, **IBAN** und entweder die USt-IdNr. oder die Steuernummer. Die XRechnung nennt Telefon und E-Mail als Kontakt des Rechnungsstellers. Fehlt eines davon, wird eine Rechnung an einen E-Rechnungs-Kunden nicht festgeschrieben, die Meldung nennt das Fehlende. **Handelsregister** (zum Beispiel „Amtsgericht Hamburg, HRB 12345“) und **Geschäftsführung** sind für die Prüfung keine Pflicht, gehören bei einer GmbH aber auf jede Rechnung (§ 35a GmbHG). {{app}} druckt sie in die Fußzeile des PDFs und nennt sie in der E-Rechnung als rechtliche Angabe. Prüfdienste warnen, wenn die Registernummer fehlt. Siehe [E-Rechnung](/hilfe/belege#e-rechnung).

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

## Betriebshöfe {#betriebshoefe}

Der Reiter **Betriebshöfe** enthält die Standorte Ihrer Firma, von denen aus Fahrten starten und enden. Eine Firma kann mehrere haben. Nur der Administrator kann sie ändern.

- **Anlegen:** Name und Adresse eintragen (Straße, Postleitzahl, Ort) und **Anlegen** wählen. Die Software sucht die Adresse einmal und speichert den Ort mit seinen Koordinaten. Darunter steht „Gespeichert: …“. Wird kein Ort gefunden, erscheint eine Meldung und nichts wird gespeichert.
- **Standard:** Genau ein Betriebshof ist der Standard. Er ist in der [Streckenplanung](/hilfe/strecken#betriebshof) vorgewählt. Der erste angelegte Betriebshof ist automatisch der Standard. Mit **Als Standard** wechseln Sie. Wird der Standard gelöscht, übernimmt der nächste.
- **Ändern:** Ändern Sie nur den Namen, wird nichts neu gesucht. Ändern Sie die Adresse, wird der neue Ort gesucht.
- **Dieselbe Adresse nie zweimal:** Eine Adresse, die schon einmal gesucht wurde, wird nicht erneut beim Adressdienst angefragt, auch nicht bei einem anderen Betriebshof.

## Preise {#preise}

Der Reiter **Preise** legt fest, wie die Software beim [Angebot aus der Streckenplanung](/hilfe/strecken#angebot-aus-tour) die Positionen vorschlägt. Alle Preise sind netto, ein Preis von **0** schaltet den Bestandteil aus. Nur der Administrator kann sie ändern.

- **Preis je km:** wird mit den Kilometern der Tour multipliziert.
- **Leerfahrt (%):** Zu diesem Anteil werden Leerkilometer (Anfahrt und Rückfahrt zum Betriebshof) berechnet. 100 heißt: voll, 0: gar nicht.
- **Stundensatz:** je Stunde Lenkzeit laut Planung.
- **Tagespauschale:** je Einsatztag.
- **Übernachtung:** je Nacht des Fahrers (Einsatztage minus eins).
- **Mindestpreis:** Liegt die Summe darunter, kommt eine Position „Mindestpreis-Ausgleich“ dazu.
- **Steuersatz je Fahrtart:** siehe unten.

### Steuersatz je Fahrtart {#preise-steuersatz}

Unter **Steuersatz je Fahrtart** stehen drei Sätze. Welche Fahrtart welchen Satz bekommt, erklärt [Fahrtart und Steuersatz](/hilfe/belege#fahrtart).

- **Regelsatz (%):** für Gelegenheitsverkehr (Charter, Ausflug, Gruppenfahrt, Mietbus) und Sonstiges. Standard 19.
- **Linienverkehr (%):** für genehmigten Linienverkehr, nur bis 50 km. Standard 7.
- **Schulfahrten (%):** für Schüler- und Schulträgerfahrten, nur bis 50 km. Standard 7.

Die Sätze sind die **Vorbelegung**: Wählt jemand im Beleg die Fahrtart, stellt sich der Satz ein. Im Beleg lässt er sich je Position ändern. Gerechnet wird mit dem Satz der Position, nicht mit dieser Einstellung. Rechnungen behalten den Satz, der bei der Rechnung galt.

### Zuschläge für Wochenende und Feiertage {#preise-zuschlaege}

Unter **Zuschläge für Wochenende und Feiertage** tragen Sie je einen Prozentsatz für **Samstag**, **Sonntag** und **Feiertag** ein. Beim [Angebot aus der Streckenplanung](/hilfe/strecken#angebot-aus-tour) setzt die Software die Zuschläge selbst, sobald die Tour einen Starttermin hat.

- **So wird gerechnet:** Die Summe der Positionen wird gleichmäßig auf die Kalendertage der Tour verteilt (vom Starttag bis zum Endtag). Jeder Samstag, Sonntag oder Feiertag bekommt seinen Satz auf diesen Tagesanteil. Beispiel: 1.000 € auf drei Tage, ein Sonntag mit 50 % ergibt 166,67 € Zuschlag.
- **Je Art eine Position:** „Zuschlag Samstag“, „Zuschlag Sonntag“ und „Zuschlag Feiertag“ mit den betroffenen Tagen in der Beschreibung.
- **Nie doppelt:** Trifft auf einen Tag mehr als ein Satz zu (ein Feiertag auf einem Sonntag), gilt nur der höchste.
- **Feiertage nach Bundesland:** Sie gelten je Land verschieden (zum Beispiel Fronleichnam, Allerheiligen oder der Reformationstag). Maßgeblich ist das Bundesland aus den [Firmendaten](/hilfe/einstellungen#firmendaten). In der Streckenplanung können Sie es für eine Tour ändern, etwa bei einer Fahrt, die in einem anderen Land liegt.
- **Nicht erkannt:** Feiertage, die nur in Teilen eines Landes gelten (zum Beispiel Mariä Himmelfahrt in Bayern oder das Augsburger Friedensfest). Tragen Sie dafür den Zuschlag von Hand ein.
- **Zusammen mit dem Zuschlag von Hand:** Der Prozentzuschlag aus der Streckenplanung rechnet auf die Summe vor den Tageszuschlägen und kommt zusätzlich.
- **Tag in deutscher Zeit:** Maßgeblich ist der Kalendertag in Deutschland, nicht der in UTC.

### Preise je Fahrzeugtyp {#preise-fahrzeugtyp}

Ein Doppeldecker kostet mehr als ein Minibus. Darum können Sie unter **Preise je Fahrzeugtyp** für jeden Typ (Reisebus, Doppeldeckerbus, Midibus, Minibus, Sonstiges) eigene Werte für Preis je km, Stundensatz, Tagespauschale, Übernachtung und Mindestpreis eintragen.

- **Leeres Feld:** Es gilt der Standardpreis darüber. Er steht grau im Feld („Standard: 1.80“).
- **0:** schaltet den Bestandteil für diesen Typ aus, auch wenn der Standardpreis etwas anderes sagt.
- **Leerfahrt (%) und die Steuersätze je Fahrtart** gelten für alle Typen gleich.
- Welcher Typ gilt, richtet sich nach dem Fahrzeug, das in der Streckenplanung gewählt ist. **Ohne Fahrzeug** gelten die Standardpreise.
- Ein Typ, bei dem alle Felder leer sind, hat keine eigenen Preise. Das Kennzeichen „eigene Preise“ an der Überschrift zeigt, welche Typen abweichen.

> Das ist eine Vorlage zum Ausprobieren, **nicht das endgültige Kalkulationsschema**. Welche Bestandteile Ihr Betrieb abrechnet und mit welchen Sätzen, stimmen Sie mit Ihrer Kalkulation und Ihrem Steuerberater ab. Ein Preis, der zu doppelten Positionen führt (zum Beispiel Preis je km und Stundensatz gleichzeitig), ist Ihre Entscheidung.
