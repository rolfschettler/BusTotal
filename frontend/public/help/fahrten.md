# Fahrten anlegen, durchführen und abrechnen {#fahrten}

Diese Anleitung führt Sie in der Reihenfolge durch den Alltag: **von der Streckenplanung bis zur bezahlten Rechnung**. Zu jedem Schritt steht, **wo** Sie ihn in {{app}} finden, **was** Sie tun und **was Sie danach sehen**. Jeder Schritt trägt einen Stand:

- **Verfügbar:** Sie können ihn heute so ausführen.
- **In Planung:** Der Schritt kommt mit einer späteren Ausbaustufe. Bis dahin steht dabei, wie Sie sich heute behelfen.

Die Anleitung wächst mit jeder neuen Funktion. Alle Funktionen im Überblick: [Funktionen und Vorteile](/hilfe/funktionen).

## So ist {{app}} aufgebaut {#konzept}

Bevor es an die Schritte geht, hilft ein Bild vom Ganzen. {{app}} kennt **fünf Dinge**, die aufeinander aufbauen:

```
REISE  (ein Vorgang für einen Kunden)
 │
 ├── Strecke 1: Hinfahrt           = Tour aus dem Archiv + Fahrzeuge (Typ × Anzahl)
 ├── Strecke 2: Fahrt vor Ort      = eigene Angaben (km, Fahrzeit) + Fahrzeuge
 ├── Strecke 3: Rückfahrt          = Tour aus dem Archiv + Fahrzeuge
 │
 └── Belege: Angebot → Auftragsbestätigung → (Anzahlung) → Rechnung

TOUR-ARCHIV  (Vorlagen, in beliebig vielen Reisen verwendbar)
 └── „München – Wien“, „Wien Rundfahrt“, „Wien – München“ …
```

**Tour, Strecke und Reise unterscheiden sich so:**

- Eine **Tour** ist eine **Vorlage**: ein geplanter Weg von A nach B (mit Halten, Kilometern, Fahrzeit und Karte). Sie gehört niemandem und hat keinen Kunden, keinen Termin und keinen Preis. Sie liegt im **Tour-Archiv**, damit Sie sie immer wieder verwenden können.
- Eine **Strecke** ist die **Verwendung einer Tour in einer Reise**: Sie sagt, dass diese Tour als *Hinfahrt*, *Fahrt vor Ort* oder *Rückfahrt* gefahren wird, mit **welchen Fahrzeugen** (Typ und Anzahl) und zu **welchem Preis** (die Positionen im Angebot). Dieselbe Tour kann in vielen Reisen als Strecke vorkommen.
- Eine **Reise** ist der **Vorgang für einen Kunden**: Sie fasst alle Strecken und alle Belege (Angebot, Auftrag, Rechnung) zu einer Fahrt zusammen. Sie hat einen Kunden und einen Zeitraum.

Anders gesagt: Die Tour beantwortet **„Wie fahren wir?“**, die Strecke **„Wie, womit und für wie viel fahren wir es in dieser Reise?“** und die Reise **„Für wen, wann und mit welchen Belegen?“**.

**Ein Beispiel:** Eine Schule bucht eine Klassenfahrt nach Wien.

| | |
|---|---|
| **Reise** | für die Realschule, 10. bis 12. Mai. Sie heißt zunächst wie die erste Tour („München – Wien“); Sie nennen sie um in „Klassenfahrt Realschule Mai“. |
| **Strecke 1: Hinfahrt** | Tour „München – Wien“ aus dem Archiv, **1 Doppeldecker und 2 Kleinbusse** |
| **Strecke 2: Fahrt vor Ort** | eigene Angaben „Stadtfahrt Wien“, 40 km, **1 Kleinbus** |
| **Strecke 3: Rückfahrt** | Tour „Wien – München“, 1 Doppeldecker und 2 Kleinbusse |
| **Belege** | Angebot, daraus die Auftragsbestätigung, später die Rechnung. Alle hängen an dieser einen Reise. |

Die Touren „München – Wien“ und „Wien – München“ liegen im Archiv und stehen auch der nächsten Klassenfahrt zur Verfügung. Ändern Sie später eine Tour im Archiv, ändert das **keinen** schon festgeschriebenen Beleg.

| Begriff | Was es ist | Wo Sie es sehen |
|---|---|---|
| **Tour** | Vorlage: ein geplanter Weg mit Halten, Kilometern, Zeitplan und Karte. | Streckenplanung, **Tour-Archiv** |
| **Strecke** | Eine Tour **in einer Reise**, als Hinfahrt, Fahrt vor Ort oder Rückfahrt, mit Fahrzeugen und Preis. | Gruppen im Beleg, Detailseite der Reise |
| **Reise** | Der Vorgang für einen Kunden mit allen Strecken und Belegen. Sie **entsteht von selbst** mit dem ersten Angebot, Sie legen sie nie an. Ihr Name ist zunächst der Name der Tour; **umbenennen** können Sie sie auf der Detailseite (Stift am Titel). | Seitenleiste **Reisen**, Reise-Leiste oben im Beleg |
| **Fahrzeuge** | Was auf einer Strecke fährt: **Typ und Anzahl**. Das konkrete Fahrzeug und die Fahrer wählt später der Disponent. | Streckenplanung, Beleg |
| **Beleg** | Angebot, Auftragsbestätigung, Anzahlungsrechnung, Rechnung (und Storno). Jeder Beleg hängt an der Reise; ein festgeschriebener ist **unveränderlich** und trägt die **Fahrtbeschreibung**, wie sie beim Festschreiben galt. | **Belege**, Reise-Leiste |

**Der rote Faden:** Sie planen eine **Tour** und speichern sie. Daraus machen Sie ein **Angebot**; dabei entsteht die **Reise** mit der ersten **Strecke**. Weitere Strecken und Fahrzeugtypen fügen Sie im Entwurf des Angebots hinzu. Das Angebot wird zur Auftragsbestätigung und zur Rechnung, alles bleibt in **einer** Reise. Die Seite **Reisen** zeigt zu jedem Zeitpunkt, wo die Reise steht und was als Nächstes zu tun ist.

**Gut zu wissen:**
- Eine Änderung an einer Tour im Archiv ändert **keinen** festgeschriebenen Beleg. Beim Festschreiben wird der Stand im Beleg festgehalten.
- Belege ohne Strecke (Pauschalpreise, Zusatzleistungen) legen Sie von Hand an. Sie gehören zu keiner Reise.
- **Noch nicht gebaut:** der **Fahrtauftrag** je Bus (Fahrzeug und Fahrer zuweisen, Fahrer-PDF) und die **Rückerfassung** der Fahrt. Sie kommen später zwischen Auftrag und Rechnung dazu, siehe die Schritte 5 und 6.

## Vorbereitung: einmal einrichten {#einrichten}

Bevor Sie das erste Angebot schreiben, richtet der **Administrator** diese Dinge einmal ein. Danach brauchen Sie sie im Alltag nicht mehr anzufassen.

1. **Firmendaten** (Einstellungen › Firmendaten): Name, Anschrift, **Steuernummer oder USt-IdNr.**, Bankverbindung und Logo. Ohne Steuernummer lässt sich keine Rechnung festschreiben. Siehe [Einstellungen](/hilfe/einstellungen#firmendaten).
2. **Preise** (Einstellungen › Preise): Preis je km, Leerfahrt in Prozent, Stundensatz, Tagespauschale, Übernachtung und Mindestpreis. Daraus schlägt {{app}} beim Angebot die Positionen vor. Ein Preis von 0 schaltet den Bestandteil aus. Siehe [Preise](/hilfe/einstellungen#preise).
3. **Steuersatz je Fahrtart** (Einstellungen › Preise, Abschnitt „Steuersatz je Fahrtart“):
   - **Regelsatz** (Standard 19 %) für Charter, Ausflug, Gruppenfahrt, Mietbus und Sonstiges,
   - **Linienverkehr** (Standard 7 %) nur für genehmigten Linienverkehr,
   - **Schulfahrten** (Standard 7 %) nur für Schüler- und Schulträgerfahrten.

   Die beiden ermäßigten Sätze gelten nur innerhalb einer Gemeinde oder bis 50 km. Die Sätze sind die **Vorbelegung**: Wählt später jemand im Beleg die Fahrtart, stellt sich der passende Satz ein. Siehe [Steuersatz je Fahrtart](/hilfe/einstellungen#preise-steuersatz) und [Fahrtart und Steuersatz](/hilfe/belege#fahrtart).
4. **Zuschläge** für Samstag, Sonntag und Feiertag (ebenfalls unter Preise) und bei Bedarf **Preise je Fahrzeugtyp**.
5. **Betriebshof** (Einstellungen › Betriebshöfe), damit die Streckenplanung Leerfahrten rechnet.
6. **Fahrzeuge** mit Maßen und Gewicht, **Fahrer** und **Kunden** anlegen. Bei Kunden, die E-Rechnungen erhalten, setzen Sie **E-Rechnung statt PDF** und tragen bei öffentlichen Auftraggebern die **Leitweg-ID** ein ([Kunde](/hilfe/kunden#e-rechnung)). Für die E-Rechnung braucht die Firma außerdem **Telefon, E-Mail und IBAN** in den Firmendaten.
7. **Nummernkreise** prüfen (Einstellungen › Nummernkreise): So sehen die Nummern der Rechnungen aus.

**Wie Sie sehen, ob es passt:** Die Einrichtungs-Checkliste auf der Startseite zeigt, was noch fehlt. Sie verschwindet, wenn alles erledigt ist.

## Der Weg im Überblick {#ueberblick}

| Schritt | Was | Wo | Stand |
|---|---|---|---|
| 0 | Einmal einrichten | **Einstellungen**, Fuhrpark, Fahrer, Kunden | Verfügbar |
| 1 | Tour planen und im Tour-Archiv speichern | Seitenleiste **Fahrten und Abrechnung › Streckenplanung** (Archiv: **Tour-Archiv**, Überblick: **Reisen**) | Verfügbar |
| 2 | Angebot erstellen, **zwei Wege** | **A:** Streckenplanung › **Angebot erstellen**. **B:** Belege › **Neuer Beleg** (ohne Strecke) | Verfügbar |
| 3 | Auftrag bestätigen | Im festgeschriebenen Angebot: **Auftragsbestätigung erstellen** | Verfügbar |
| 4 | Anzahlung verlangen (optional) | **Anzahlungsrechnung erstellen** | Verfügbar |
| 5 | Fahrt durchführen | Fahrtauftrag für Disponent und Fahrer | In Planung |
| 6 | Fahrt zurückerfassen | Zeiten, Kilometerstände, Zusatzleistungen, Kosten | In Planung |
| 7 | Rechnung schreiben | **Rechnung erstellen** aus dem Auftrag | Verfügbar (aus dem Auftrag) |
| 8 | Zahlung erfassen | Abschnitt **Zahlungen** in der Rechnung | Verfügbar |
| 9 | Korrigieren | **Stornieren** und neu erstellen | Verfügbar |

## Schritt 1: Tour planen {#tour-planen}

**Wo:** Seitenleiste, Gruppe **Fahrten und Abrechnung**, Eintrag **Streckenplanung** (Administrator und Disponent).

1. Tragen Sie **Von** und **Nach** ein, bei Bedarf **Über**-Orte. Wählen Sie **Fahrzeugtyp** und **Anzahl** der Busse und die **Abfahrt**.
2. Klicken Sie **Strecke berechnen**.
3. Sie sehen Strecke, Leerfahrt, Lenkzeit, Gesamtdauer, Fahrtage und den Zeitplan mit Pausen und Ruhezeiten. Warnungen weisen auf Probleme hin.
4. **Tour speichern** (empfohlen): Unter dem Ergebnis legt die Schaltfläche die Tour im Tour-Archiv ab. Der Name ist mit „Von – Nach“ vorgeschlagen. Eine Tour, die Sie schon einmal geplant haben, laden Sie oben rechts mit **Aus Archiv laden**, ohne neu zu berechnen, oder öffnen sie in der Seitenleiste unter **Tour-Archiv**. Alle Reisen mit Stand und Betrag stehen unter **Reisen**.
5. **Nächster Schritt:** **Angebot erstellen** (siehe Schritt 2).

**Tipp:** Orte suchen Sie wie in OpenStreetMap („Ulm Hbf“, nicht „Ulm Hauptbahnhof“). Mehr unter [Streckenplanung](/hilfe/strecken).

## Schritt 2: Angebot erstellen {#angebot}

Ein Angebot entsteht auf **zwei Wegen**. Beide führen in dasselbe Beleg-Formular, das Ergebnis ist ein gleichwertiger Beleg:

| | **Weg A: mit Strecke** | **Weg B: ohne Strecke** |
|---|---|---|
| **Wann** | Fahrt mit bekannter Strecke, Sie wollen einen Preisvorschlag | Pauschalpreis, Zusatzleistung oder eine Fahrt, die Sie nicht berechnen müssen |
| **Wo** | **Fahrten und Abrechnung › Streckenplanung**, dann **Angebot erstellen** | **Fahrten und Abrechnung › Belege**, dann **Neuer Beleg** |
| **Wer** | Administrator und Disponent | Administrator, Disponent und Buchhaltung |
| **Positionen** | automatisch aus Ihren Preisen (Fahrstrecke, Tagespauschale, Übernachtung, Zuschläge, Mindestpreis) | leer, Sie tragen sie selbst ein |
| **Fahrtart und Steuersatz** | einmal gewählt, gilt für alle Positionen | je Position wählen |
| **Betreff und Fahrtdatum** | werden aus Strecke und Abfahrt gefüllt | leer |
| **Kunde** | im Beleg wählen | im Beleg wählen |

### Weg A: mit Strecke {#angebot-strecke}

**Wo:** Seitenleiste **Fahrten und Abrechnung › Streckenplanung**, nach der Berechnung unter dem Ergebnis, dann im Beleg.

1. Wählen Sie unter dem Ergebnis die **Fahrtart**. **Gelegenheitsverkehr** (Charter, Ausflug, Gruppenfahrt) ist vorbelegt, das ist der Normalfall. **Linienverkehr** und **Schülerfahrt** gelten nur für genehmigte Linien und Schulträger.
2. Wählen Sie bei Bedarf einen **Zuschlag** und das Bundesland der **Feiertage**.
3. Klicken Sie **Angebot erstellen**. {{app}} legt das Angebot als **Entwurf** an und öffnet es. Dabei entsteht automatisch die **Reise** (die Tour wird bei Bedarf im Archiv gespeichert). Oben im Beleg zeigt die Reise-Leiste die Belege der Reise und den nächsten Schritt, siehe [Reise](/hilfe/belege#reise).
4. Wählen Sie im Beleg den **Kunden** (Suchfeld: Name, Nummer oder Ort).
5. Prüfen Sie die **Positionen**: Preis, Menge, **Fahrtart** und **Steuersatz** lassen sich je Position ändern. Steht oben **„Bitte prüfen“**, passt ein Satz nicht zur Fahrtart. Das ist ein Hinweis und sperrt nichts.
6. **Mehrere Strecken oder Busse?** Mit **Weitere Strecke hinzufügen** nehmen Sie Hinfahrt, Fahrt vor Ort und Rückfahrt in dasselbe Angebot auf, je Strecke mit einem oder mehreren Fahrzeugtypen. Siehe [Mehrere Strecken und Fahrzeugtypen](/hilfe/belege#mehrere-strecken).
7. **Speichern**, dann **Festschreiben** (nur Administrator und Buchhaltung). Das Angebot bekommt seine Nummer und ist gesperrt. Dabei hält {{app}} die **Fahrtbeschreibung** der Strecken (Halte, Kilometer, Zeiten, Fahrzeuge) unveränderlich im Beleg fest, siehe [Fahrtbeschreibung](/hilfe/belege#fahrtbeschreibung).
8. **Drucken** öffnet die Druckvorschau. Von dort drucken Sie oder speichern als PDF.

### Weg B: ohne Strecke {#angebot-hand}

**Wo:** Seitenleiste **Fahrten und Abrechnung › Belege**, oben rechts **Neuer Beleg**.

1. Lassen Sie die **Belegart** auf *Angebot*. Wählen Sie den **Kunden** (Suchfeld: Name, Nummer oder Ort, mindestens zwei Zeichen).
2. Tragen Sie **Betreff** und **Leistung von (bis)** ein, das Fahrtdatum.
3. Legen Sie die **Positionen** an: **Bezeichnung**, **Menge**, **Einheit**, **Einzelpreis (netto)**. Mit **Position hinzufügen** kommen weitere dazu.
4. Wählen Sie je Position die **Fahrtart**. Der **Steuersatz** stellt sich passend ein ([Fahrtart und Steuersatz](/hilfe/belege#fahrtart)). Für Positionen, die keine Fahrt sind (Maut, Übernachtung), wählen Sie *Sonstiges* und den Satz von Hand.
5. **Beleg anlegen**, dann wie bei Weg A: prüfen, **Speichern**, **Festschreiben**, **Drucken**.

Das neue Angebot weist oben darauf hin, dass Sie es von Hand anlegen, und verlinkt auf die Streckenplanung.

### Nach beiden Wegen {#angebot-danach}

**Was Sie danach sehen:** In der Belegliste steht das Angebot mit Nummer und dem Stand „Festgeschrieben“. Bei Weg A steht es außerdem unter **Fahrten und Abrechnung › Reisen**: Dort sehen Sie die Reise mit ihren Strecken, allen Belegen, dem Stand und dem **nächsten Schritt**, siehe [Die Liste der Reisen](/hilfe/belege#reisen). Die gespeicherte Tour finden Sie unter **Tour-Archiv**. Mehr unter [Belege](/hilfe/belege) und [Fahrtart und Steuersatz](/hilfe/belege#fahrtart).

## Schritt 3: Auftrag bestätigen {#auftrag}

**Wo:** im **festgeschriebenen** Angebot, rechts unten in der Tastenleiste.

1. Klicken Sie **Auftragsbestätigung erstellen**. Es entsteht ein **Entwurf** mit denselben Positionen, Kunde und Fahrtdatum werden übernommen. Der Beleg bleibt in derselben **Reise** und übernimmt die Strecken samt Fahrtbeschreibung des Angebots.
2. Prüfen Sie ihn, **speichern** und **festschreiben** Sie ihn.

**Wichtig:** Der Preis ist ein **Vorschlag**. Ihr Ablauf kann einen vorläufigen Preis vorsehen, der nach der Fahrt durch die tatsächlichen Werte ersetzt wird.

## Schritt 4: Anzahlung verlangen (optional) {#anzahlung}

**Wo:** im festgeschriebenen Angebot oder der Auftragsbestätigung.

1. **Anzahlungsrechnung erstellen**. Vorgeschlagen sind 30 % des Auftrags, je Steuersatz eine Position. Den Prozentsatz ändern Sie im Entwurf.
2. **Festschreiben** und an den Kunden schicken (Drucken).
3. Wenn die Anzahlung eingeht: In der Anzahlungsrechnung im Abschnitt **Zahlungen** **Zahlung erfassen** (siehe [Schritt 8](#zahlung)).

## Schritt 5: Fahrt durchführen {#fahrt}

**In Planung.** Hier entsteht der **Fahrtauftrag**: je Bus einer, mit Ablauf, Zeiten, Fahrzeug und Fahrer. Der Fahrer hat ihn im Fahrzeug und notiert **Zusatzleistungen** (zum Beispiel Sonderfahrten vor Ort), **unvorhergesehene Kosten** (Maut, Parken) und die **Kilometerstände bei Grenzübertritt**.

**Bis dahin:** Fahrtdatum und Strecke stehen im Angebot und in der Auftragsbestätigung. Notieren Sie Besonderheiten der Fahrt wie bisher außerhalb von {{app}} und tragen Sie sie in die Rechnung ein.

## Schritt 6: Fahrt zurückerfassen {#rueckerfassung}

**In Planung.** Das Büro übernimmt die Angaben des Fahrers aus dem Fahrtauftrag. {{app}} prüft sie auf Plausibilität (lückenlose Kilometerstände, Zeiten). Daraus entsteht die Rechnung.

**Bis dahin:** Zusatzleistungen und Kosten fügen Sie in der Rechnung als eigene **Positionen** hinzu.

## Schritt 7: Rechnung schreiben {#rechnung}

**Wo:** in der festgeschriebenen Auftragsbestätigung oder im Angebot, Knopf **Rechnung erstellen**.

1. Es entsteht ein Entwurf mit den Positionen des Auftrags. Ersetzen Sie den vorläufigen Preis durch die tatsächlichen Werte und fügen Sie Zusatzleistungen hinzu.
2. **Leistung von (bis)** ist Pflicht: das Fahrtdatum.
3. Gab es eine **Anzahlung**, zieht die Schlussrechnung sie **automatisch ab**: Sie zeigt die volle Leistung, die Anzahlung mit Steuer und **„Zu zahlen“**.
4. **Festschreiben:** Die Software prüft die Pflichtangaben und nennt, was fehlt. Danach bekommt die Rechnung ihre Nummer. Fehlt etwas, wird keine Nummer verbraucht.
5. **Drucken** oder als PDF an den Kunden.
6. **Hat der Kunde E-Rechnung statt PDF**, steht nach dem Festschreiben in der Tastenleiste **E-Rechnung (XML)**: Das ist die Datei, die Sie dem Kunden schicken (E-Mail oder Rechnungsportal). Was dafür beim Kunden und in den Firmendaten stehen muss, steht unter [E-Rechnung](/hilfe/belege#e-rechnung). Fehlt etwas, wird nicht festgeschrieben und die Meldung nennt es.

**Bald:** Die Rechnung entsteht dann aus den zurückerfassten Fahrtaufträgen, als **Gesamtrechnung je Reise** oder als **Sammelrechnung** über mehrere Fahrten (zum Beispiel Werksverkehr am Monatsende).

## Schritt 8: Zahlung erfassen {#zahlung}

**Wo:** in der festgeschriebenen Rechnung oder Anzahlungsrechnung, Abschnitt **Zahlungen** (Administrator und Buchhaltung).

1. Klicken Sie **Zahlung erfassen**.
2. **Datum** des Zahlungseingangs auf dem Konto, **Betrag** (vorbelegt mit dem offenen Betrag), **Zahlungsart** und **Verwendungszweck** eintragen, **Buchen**.
3. Der Zahlstand springt auf **Bezahlt** oder **Teilweise bezahlt**. In der Belegliste steht er unter „Festgeschrieben“.

**Offene Posten finden:** Die Belegliste zeigt je Rechnung **offen**, **teilweise bezahlt** oder **überfällig** mit dem offenen Betrag. Sortieren Sie nach **Datum**, um die ältesten Rechnungen zu sehen.

**Falsch gebucht?** Buchung **stornieren** (mit Grund) und neu buchen. Details unter [Zahlungen](/hilfe/belege#zahlungen).

## Schritt 9: Korrigieren {#korrigieren}

- **Entwurf falsch:** einfach ändern oder **Entwurf löschen**.
- **Festgeschriebene Rechnung falsch:** **Stornieren**. Es entsteht eine **Stornorechnung** mit negativen Beträgen und dem Verweis auf die Rechnung. Danach schreiben Sie die richtige Rechnung neu. Eine festgeschriebene Rechnung lässt sich nie ändern (GoBD).
- **Schlussrechnung storniert:** Die Anzahlungen werden dabei wieder frei und lassen sich in der neuen Schlussrechnung erneut abziehen.
- **Bereits Geld erhalten:** Nach dem Storno **Rückzahlung** buchen, wenn Sie das Geld zurückgeben.

## Wenn etwas nicht klappt {#probleme}

| Problem | Ursache und Lösung |
|---|---|
| **„In den Einstellungen sind noch keine Preise hinterlegt“** beim Angebot aus der Tour | Der Administrator trägt unter **Einstellungen › Preise** mindestens einen Preis ein. |
| **„Bitte prüfen“ oben im Beleg** | Satz und Fahrtart passen nicht zusammen, zum Beispiel ermäßigt bei Charter. Satz oder Fahrtart ändern, oder bewusst lassen. Es sperrt nichts. |
| **Festschreiben geht nicht** | Die Meldung nennt, was fehlt: Kunde, Anschrift, Steuernummer der Firma, Leistungsdatum. Unter **Einstellungen › Firmendaten** ergänzen. |
| **Zahlung höher als offen** | Es lässt sich nicht mehr buchen, als offen ist. Prüfen Sie, ob schon eine Zahlung gebucht ist, und ob Sie die Schlussrechnung (nach Abzug) oder die Anzahlungsrechnung meinen. |
| **Zahlung nicht erfassbar** | Nur Administrator und Buchhaltung dürfen buchen, und nur bei festgeschriebenen Rechnungen und Anzahlungsrechnungen. |

## Was noch kommt {#kommt}

Diese Anleitung wird mit jeder Ausbaustufe ergänzt. Geplant sind in dieser Reihenfolge:

1. **Tour speichern und wiederverwenden**, Angebot mit **mehreren Strecken und Fahrzeugtypen**, das **Reisevorhaben** (Hinfahrt, Fahrt vor Ort, Rückfahrt).
2. **Fahrtauftrag** je Bus mit Fahrer-PDF, **Rückerfassung** mit Kilometerständen an der Grenze.
3. **Rechnung aus Fahrtaufträgen**, **Sammelrechnung** (Werksverkehr), **Auslandsanteil** nach Streckenlänge.
4. **Reiseleistungen nach § 25 UStG** (Marge).
5. **Voranmeldungsbericht**, Datenexport, DATEV, Aufbewahrung.

**E-Rechnung** läuft als eigene Spur und wird eingeplant, sobald die Pflicht für Ihr Unternehmen feststeht.
