# Kundenverwaltung {#kundenverwaltung}

Hier verwalten Sie Firmen, Schulen, Vereine, Reisebüros und Privatpersonen, für die Sie fahren. Der Bereich ist für Administrator, Disponent und Buchhaltung zugänglich.

## Kundenliste {#kundenliste}

Die Liste zeigt 20 Kunden pro Seite, sortiert nach Name.

- **Suche:** Das Suchfeld durchsucht Firmenname, Vor- und Nachname, Kundennummer, Debitorennummer, E-Mail, Ort und Postleitzahl. Die Suche startet kurz nach der letzten Eingabe. Groß- und Kleinschreibung sowie Umlaute spielen keine Rolle: „muller“ findet auch „Müller“.
- **Treffer sind gelb markiert**, direkt in der Liste. Steckt der Treffer in einem Feld, das die Liste nicht zeigt (zum Beispiel die Debitorennummer), erscheint unter dem Namen der Hinweis „Treffer in Debitorennr.“, ebenfalls markiert.
- **Art:** Der Filter zeigt nur eine Kundenart, zum Beispiel nur Schulen.
- **Archivierte:** Mit diesem Häkchen sehen Sie statt der aktiven die [archivierten](#archivieren) Kunden.

- **Sortieren:** Ein Klick auf eine Spaltenüberschrift sortiert die ganze Liste danach, ein zweiter Klick dreht die Richtung um. Die Sortierung gilt für alle Seiten. Die sortierte Spalte ist farbig hervorgehoben und trägt einen Pfeil. Leere Felder stehen immer am Ende.
- **Aktionsspalte:** Links in jeder Zeile öffnet der **Stift** den Kunden, das **Archiv-Symbol** [archiviert](#archivieren) ihn nach einer Rückfrage. In der Ansicht „Archivierte“ steht für den Administrator stattdessen ein Pfeil zum **Wiederherstellen**.
- **Zeile markieren:** Ein Klick auf eine Zeile hebt sie hervor, damit Sie sie beim Blättern im Blick behalten.
- **Kopfzeile fest:** Beim Scrollen bleiben Titel, Suchleiste und Spaltenüberschriften sichtbar.

Ein Klick auf den Namen öffnet den Kunden.

## Kunde anlegen {#kunde-anlegen}

Wählen Sie **Neuer Kunde** und füllen Sie die Stammdaten aus. Pflicht ist je nach Art:

- **Firma, Schule, Verein, Reisebüro:** der Firmen- bzw. Organisationsname
- **Privatperson:** der Nachname

Alles andere ist freiwillig. Beim Speichern vergibt das System automatisch die [Kunden- und Debitorennummer](#nummern).

Während Sie tippen, prüft die Software, ob es den Kunden schon gibt, und zeigt gegebenenfalls eine [Warnung](#dubletten).

## Kunden- und Debitorennummer {#nummern}

Jeder Kunde bekommt zwei Nummern, die Sie nicht selbst vergeben oder ändern können:

- **Kundennummer**, zum Beispiel `K-00001`: fortlaufend für alle Kunden.
- **Debitorennummer**, zum Beispiel `10000`: das Konto, unter dem der Kunde später in der Buchhaltung (DATEV) geführt wird. Die Zählung beginnt bei 10000.

Beide Nummern bleiben dauerhaft bestehen, auch wenn ein Kunde archiviert oder anonymisiert wird. Wie die Nummern aufgebaut sind, stellt der Administrator unter [Nummernkreise](/hilfe/einstellungen#nummernkreise) ein.

## Mögliche Dubletten {#dubletten}

Die Software warnt, wenn es einen ähnlichen Kunden schon gibt. Als ähnlich gilt:

- die **gleiche E-Mail-Adresse**,
- der **gleiche Firmenname** (Groß- und Kleinschreibung spielt keine Rolle) oder
- der **gleiche Vor- und Nachname mit gleicher Postleitzahl**.

Die Warnung ist ein Hinweis, kein Verbot: Sie können trotzdem speichern, wenn es wirklich ein anderer Kunde ist. Ein Klick auf den Namen in der Warnung öffnet den vorhandenen Kunden in einem neuen Tab.

## Stammdaten {#stammdaten}

Auf dem Reiter **Stammdaten** pflegen Sie:

- **Kunde:** Art, Name, Anrede.
- **Kontakt:** E-Mail, Telefon, Mobil, Webseite.
- **Rechnungsadresse:** Straße, PLZ, Ort, Land (zweistelliger Code, zum Beispiel `DE`).
- **Rechnung:** USt-IdNr. (Format `DE123456789`) und das **Zahlungsziel in Tagen**. Lassen Sie das Zahlungsziel leer, gilt der Standard Ihrer Firma aus den [Einstellungen](/hilfe/einstellungen#firmendaten).
- **Notizen:** interne Hinweise, die nur Ihre Mitarbeiter sehen.

Speichern Sie mit **Speichern**. Änderungen werden im [Änderungsprotokoll](/hilfe/datenschutz#protokoll) mit altem und neuem Wert festgehalten.

## Ansprechpartner {#ansprechpartner}

Beim Kunden können Sie mehrere Ansprechpartner hinterlegen, zum Beispiel die Sekretärin und den Reiseleiter eines Vereins.

- Pflicht ist nur der **Nachname**.
- Ein Ansprechpartner kann als **Hauptansprechpartner** markiert werden (Stern in der Liste). Es gibt immer höchstens einen: Markieren Sie einen anderen, verliert der bisherige die Markierung.
- Mit **Bearbeiten** und **Löschen** ändern Sie Einträge. Das Löschen müssen Sie bestätigen.

## Adressen {#adressen}

Zusätzlich zur Rechnungsadresse können Sie weitere Adressen hinterlegen, vor allem **Abholpunkte** für Busreisen.

- **Art:** Abholung, Lieferung oder Sonstige.
- **Pflichtfelder:** Straße, PLZ und Ort.
- Die Bezeichnung (zum Beispiel „Schulhof“) hilft, mehrere Adressen auseinanderzuhalten.

## Archivieren und Wiederherstellen {#archivieren}

Kunden werden nicht gelöscht, sondern **archiviert**. So bleiben sie für spätere Belege erhalten.

- **Archivieren:** Auf der Kundenseite unter **Weitere Aktionen**. Der Kunde verschwindet aus der Liste.
- **Archivierte ansehen:** In der Liste das Häkchen **Archivierte** setzen. Archivierte Kunden sind nur lesbar.
- **Wiederherstellen:** Nur der Administrator kann einen archivierten Kunden wieder aktivieren.

Sollen die persönlichen Daten eines Kunden wirklich entfernt werden, lesen Sie [Löschung: Anonymisieren](/hilfe/datenschutz#anonymisieren).
