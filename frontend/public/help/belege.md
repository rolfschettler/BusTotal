# Belege {#belege}

Hier erstellen Sie Angebote, Auftragsbestätigungen und Rechnungen. Der Bereich ist für Administrator, Disponent und Buchhaltung zugänglich. Dieses Thema ist keine Rechts- oder Steuerberatung: Welche Angaben auf eine Rechnung gehören und welcher Steuersatz gilt, klären Sie mit Ihrem Steuerberater.

## Belegliste {#belegliste}

Die Liste zeigt 20 Belege pro Seite. Ein Klick auf eine Spaltenüberschrift sortiert danach, ein zweiter Klick kehrt die Richtung um. Ohne Wahl stehen die neuesten zuerst, Entwürfe oben (ein Entwurf hat noch kein Datum und gilt als der neueste). Leere Werte stehen immer am Ende. Die Belegart sortiert in der Reihenfolge des Geschäftsgangs (Angebot bis Stornorechnung).

- **Suche:** Das Suchfeld durchsucht die Belegnummer, den Betreff und den Kunden (Name und Kundennummer). Treffer sind gelb markiert. Steckt der Treffer in einem Feld, das die Liste nicht zeigt (die Kundennummer), erscheint unter dem Namen der Hinweis „Treffer in Kundennr.“.
- **Belegart und Stand:** Die Filter zeigen zum Beispiel nur Rechnungen oder nur Entwürfe.
- **Anordnung:** „Je Kunde mit Entstehungskette“ (statt „Nach Spalte sortiert“) stellt die Belege eines Kunden untereinander, alphabetisch nach Kunde. Darin stehen die Belegketten in der Reihenfolge ihrer Entstehung, zum Beispiel Angebot, darunter eingerückt die Auftragsbestätigung, darunter die Rechnung und ein eventueller Storno. So lässt sich nachvollziehen, woraus ein Beleg entstanden ist. Unter der Belegart steht außerdem immer „aus Angebot AN-…“, sobald ein Beleg aus einem anderen entstanden ist. Die Wahl bleibt beim Wechsel in einen Beleg und zurück erhalten.
- **Stand:** *Entwurf* ist noch änderbar und hat keine Nummer. *Festgeschrieben* hat eine Nummer und ist gesperrt. *Storniert* ist ein festgeschriebener Beleg, der durch einen Storno-Beleg ausgeglichen wurde.

## Beleg anlegen {#beleg-anlegen}

Ein **Angebot** entsteht auf zwei Wegen: **mit Strecke** über die [Streckenplanung](/hilfe/strecken#angebot-aus-tour) (Knopf **Angebot erstellen**, die Positionen schlägt die Software aus Ihren Preisen vor) oder **ohne Strecke** hier unter **Belege › Neuer Beleg** (Sie tragen die Positionen selbst ein). Beide Wege ergeben denselben Beleg. Der Weg im Einzelnen: [Angebot erstellen](/hilfe/fahrten#angebot).

Wählen Sie **Neuer Beleg**, dann die Belegart und den Kunden. Die Belegarten:

- **Angebot** und **Auftragsbestätigung**
- **Anzahlungsrechnung**: für eine Anzahlung vor der Fahrt
- **Rechnung**
- **Stornorechnung** legen Sie nicht selbst an, sie entsteht beim [Stornieren](#stornieren). Sie heißt bewusst nicht „Gutschrift“: Im Steuerrecht ist eine Gutschrift die Abrechnung des Leistungsempfängers, eine Stornierung ist eine Stornorechnung.

Die Belegart lässt sich nach dem Anlegen nicht mehr ändern. Jeder Beleg besteht aus Kopfdaten, Positionen und Texten:

- **Positionen:** Bezeichnung, Menge, Einheit, Einzelpreis (netto) und Steuersatz. Die Summen darunter sind eine Vorschau, maßgeblich ist die Berechnung beim Speichern. Die Steuer wird je Steuersatz aus der Nettosumme gerechnet.
- **Fahrtart und Steuersatz:** Wählen Sie je Position die **Fahrtart**, dann stellt sich der Steuersatz passend ein. Mehr unter [Fahrtart und Steuersatz](#fahrtart).
- **Steuersatz:** Zur Auswahl stehen die Sätze aus den Einstellungen. Bei 0 % verlangt eine Rechnung einen Grund im **Steuerhinweis**, zum Beispiel „steuerfrei“ oder „Ausland“.
- **Leistung von / bis:** Das Fahrtdatum. Bei Rechnungen ist **Leistung von** Pflicht, bei Anzahlungsrechnungen nicht.
- **Belegdatum:** Bleibt es leer, gilt der Tag des Festschreibens.
- **Zahlungsziel:** Bleibt es leer, gilt das Ziel des Kunden, sonst das der Firma (siehe [Einstellungen](/hilfe/einstellungen)).

## Fahrtart und Steuersatz {#fahrtart}

Jede Position kann eine **Fahrtart** tragen. Sie bestimmt den Steuersatz der Beförderung:

| Fahrtart | Steuersatz | Wann |
|---|---|---|
| **Gelegenheitsverkehr** (Charter, Ausflug, Gruppenfahrt, Mietbus mit Fahrer) | Regelsatz | immer, **auch bei kurzen Strecken** |
| **Genehmigter Linienverkehr** | ermäßigt | nur innerhalb einer Gemeinde oder bis **50 km** Beförderungsstrecke |
| **Schüler- und Schulträgerfahrt** | ermäßigt | wie Linienverkehr: Wohnung–Schule und Fahrten für Schulträger, nicht Klassenfahrten |
| **Sonstiges** | von Hand | Maut, Zuschläge und alles, was keine Fahrt ist |
| **Keine Angabe** | von Hand | bei älteren Belegen und Positionen ohne Fahrt |

- **Der Satz stellt sich ein:** Wählen Sie die Fahrtart, setzt die Software den Satz aus den [Einstellungen](/hilfe/einstellungen#preise-steuersatz). Sie können ihn danach von Hand ändern.
- **Hinweise statt Sperren:** Passt ein Satz nicht zur Fahrtart, zeigt der Beleg oben **„Bitte prüfen“**. Zum Beispiel bei Gelegenheitsverkehr mit ermäßigtem Satz, oder bei ermäßigtem Satz auf Linienverkehr mit dem Hinweis auf die 50 km. Die Software kennt die Strecke einer Position nicht und sperrt deshalb nichts. Der Hinweis hindert das Festschreiben nicht.
- **Bei Fahrten ins Ausland** zählt für die 50 km nur die Strecke im Inland.
- **Angebot aus der Streckenplanung:** Dort wählen Sie die Fahrtart vor dem Erstellen. Alle Positionen des Angebots tragen sie, auch die Zuschläge. Siehe [Angebot aus der Tour](/hilfe/strecken#angebot-aus-tour).
- **Folgebelege und Storno** übernehmen die Fahrtart.
- **Rechtsgrundlage:** § 12 Abs. 2 Nr. 10 UStG, Anwendungserlass Abschnitte 12.13 und 12.14. Die Texte sind ein Entwurf zur Prüfung durch Ihren Steuerberater.

## Entwurf und Festschreiben {#entwurf-und-festschreiben}

Ein **Entwurf** ist frei änderbar und hat noch keine Nummer. Sie können ihn jederzeit löschen. Dabei geht keine Nummer verloren.

**Festschreiben** vergibt die Nummer und sperrt den Beleg. Das dürfen Administrator und Buchhaltung. Beim Festschreiben passiert Folgendes:

- Die Pflichtangaben werden geprüft. Bei Rechnungen sind das unter anderem die Anschrift des Kunden, die Steuernummer oder USt-IdNr. Ihrer Firma, der Leistungszeitpunkt und mindestens eine Position. Fehlt etwas, nennt die Meldung jede Angabe einzeln, und es wird keine Nummer verbraucht.
- Absender und Empfänger werden **als Kopie** im Beleg gespeichert. Ändern Sie später den Kunden oder Ihre Firmendaten, bleibt der Beleg, wie er war.
- Die Nummer kommt aus dem [Nummernkreis](/hilfe/einstellungen#nummernkreise) der Belegart. Rechnungen und Anzahlungsrechnungen teilen sich einen Kreis, damit die Rechnungsnummern fortlaufend bleiben.

Ein festgeschriebener Beleg lässt sich nicht mehr ändern oder löschen, weder in der Oberfläche noch im Hintergrund. Das verlangen die Grundsätze ordnungsmäßiger Buchführung (GoBD). Hat sich ein Fehler eingeschlichen, [stornieren](#stornieren) Sie den Beleg und erstellen einen neuen.

## Stornieren {#stornieren}

Rechnungen und Anzahlungsrechnungen lassen sich stornieren (Administrator und Buchhaltung). Die Software erzeugt dabei eine eigene, sofort festgeschriebene **Stornorechnung** mit negativen Beträgen, eigener Nummer und dem Verweis auf die stornierte Rechnung. Der ursprüngliche Beleg bleibt unverändert erhalten und zeigt nur noch den Hinweis „Storniert“.

Ein Beleg lässt sich nur einmal stornieren. Entwürfe werden nicht storniert, sondern gelöscht. Angebote und Auftragsbestätigungen werden nicht storniert.

## PDF {#pdf}

Mit **Drucken** (in der Tastenleiste des Belegs und als Druckersymbol in der Liste) wird der Beleg gedruckt, ohne dass ein neuer Tab aufgeht. Was Sie sehen, hängt vom Gerät ab:

- **Am PC (mit Maus):** Es erscheint kurz der Hinweis „Druck wird vorbereitet …“, dann öffnet sich gleich die **Druckvorschau Ihres Browsers** mit allen Seiten des Belegs. Von dort drucken Sie oder speichern als PDF. Eine eigene Vorschau in {{app}} erscheint nur, wenn der Aufbau nicht gelingt: Dann sehen Sie eine Meldung und können das PDF über **Herunterladen** speichern.
- **Auf Tablets und Handys (Android, iPad):** Die **Druckvorschau öffnet direkt in {{app}}** und zeigt alle Seiten des Belegs. Mit **Drucken** darin öffnet sich der Druckdialog des Geräts (auf Android der Druckdienst, auf dem iPad AirPrint). **Herunterladen** speichert das PDF als Datei, **Schließen** (oder die Esc-Taste) beendet die Vorschau. Gedruckt wird ein Bild der Seiten; wer das PDF selbst braucht (zum Beispiel für eine E-Mail), nutzt „Herunterladen“.

- Das PDF enthält Briefkopf mit Logo, Empfängeranschrift, Belegnummer, Datum, Leistungszeitpunkt, die Positionen, die Summen (mit Steuer je Satz, bei einer Schlussrechnung mit abgezogenen Anzahlungen und „Zu zahlen“), Steuerhinweise als Fußnoten, bei Rechnungen den Zahlungshinweis mit Fälligkeit und Bankverbindung und die Fußzeile mit den Firmenangaben. Briefkopf-Zusatz und Fußzeile stellen Sie unter [Firmendaten](/hilfe/einstellungen#firmendaten) ein.
- Ein **Entwurf** trägt oben den Hinweis „ENTWURF“ und hat keine Belegnummer. Er ist keine gültige Rechnung.
- Ein **festgeschriebener** Beleg zeigt Absender und Empfänger so, wie sie beim Festschreiben gespeichert wurden. Spätere Änderungen an Kunde oder Firmendaten erscheinen nicht.
- Das PDF zeigt immer den **gespeicherten** Stand. Speichern Sie Änderungen zuerst.
- Ein **Entwurf** wird bei jedem Öffnen frisch erzeugt (mit den heutigen Einstellungen für Logo und Markenfarbe).
- Beim **Festschreiben** (und beim Storno) entsteht das PDF **einmal** und wird sicher abgelegt. Jedes spätere Öffnen zeigt genau diese Datei, nie eine neu erzeugte. Änderungen an Logo, Markenfarbe, Firmendaten oder Kunde ändern sie nicht. Sie wird nie gelöscht, auch nicht, wenn der Kunde später anonymisiert wird.
- Eine **Prüfsumme** erkennt, wenn die abgelegte Datei beschädigt oder verändert wurde. Dann erscheint eine Fehlermeldung statt eines PDFs. Bitte melden Sie das der Verwaltung, die Sicherung enthält die Datei.
- Scheitert die PDF-Erzeugung beim Festschreiben, bleibt der Beleg ein Entwurf und es wird **keine Nummer verbraucht**. Versuchen Sie es später erneut.
- Belege, die vor Einführung des Archivs festgeschrieben wurden, werden beim ersten Öffnen einmalig erzeugt und dann ebenfalls abgelegt. Logo und Markenfarbe stammen bei ihnen aus den Einstellungen dieses Tages.
- Jedes Öffnen steht im Änderungsprotokoll.

> Das Archiv-PDF liegt auf dem Server dieser Firma. Nehmen Sie es in Ihre Datensicherung auf. Ob Ihre Rechnung alle Pflichtangaben trägt, klären Sie mit Ihrem Steuerberater.

## Zahlungen {#zahlungen}

Zu jeder **festgeschriebenen Rechnung** und **Anzahlungsrechnung** gehört der Zahlstand. Im Beleg steht dafür unter dem Formular der Abschnitt **Zahlungen**; in der Liste zeigt die Spalte **Stand** unter „Festgeschrieben“, ob der Beleg bezahlt, teilweise bezahlt, offen oder überfällig ist.

- **Zahlung erfassen:** **Zahlung erfassen**, dann Datum, Betrag, Zahlungsart und Verwendungszweck eintragen. Der Betrag ist mit dem offenen Betrag vorbelegt. Mehr als der offene Betrag lässt sich nicht buchen, ein Datum in der Zukunft auch nicht. Ist der Beleg vollständig bezahlt, steht er auf **Bezahlt**.
- **Teilzahlungen** sind möglich: Der Beleg steht dann auf **Teilweise bezahlt**, der Rest bleibt offen.
- **Rückzahlung:** Wählen Sie bei **Art** „Rückzahlung“, wenn Sie Geld zurückgeben (Kulanz, Storno). Es lässt sich nie mehr zurückzahlen, als bezahlt wurde. Bei einem stornierten Beleg sind nur noch Rückzahlungen möglich.
- **Überfällig** heißt: Es ist noch etwas offen und das Fälligkeitsdatum liegt vor heute.
- **Falsch gebucht?** Eine Buchung wird nie gelöscht, sondern **storniert**, mit einem Grund. Sie bleibt grau durchgestrichen stehen und zählt nicht mehr. So ist jeder Zahlungsverlauf nachvollziehbar.
- **Der Beleg selbst ändert sich nie.** Er bleibt festgeschrieben, auch das Archiv-PDF. Der Zahlstand ergibt sich nur aus den Buchungen.
- **Schlussrechnung:** Gezahlt wird der Betrag **nach Abzug der Anzahlungen** („Zu zahlen“). Die Anzahlungsrechnung hat ihren eigenen Zahlstand.
- **Wer darf was:** Ansehen dürfen Administrator, Disponent und Buchhaltung. **Buchen und Stornieren** dürfen nur Administrator und Buchhaltung.
- **Warum das wichtig ist:** Die Umsatzsteuer auf Anzahlungen und ihre Berichtigung hängen am tatsächlichen Zahlungsfluss (§ 13 UStG, Anwendungserlass 13.5 und 17.1). Eine spätere Auswertung für die Umsatzsteuer-Voranmeldung baut darauf auf.

## E-Rechnung {#e-rechnung}

Für Kunden mit dem Kennzeichen **E-Rechnung statt PDF** ([Kunde](/hilfe/kunden#e-rechnung)) entsteht beim **Festschreiben** einer Rechnung, Anzahlungsrechnung oder Stornorechnung zusätzlich die **XRechnung**: eine XML-Datei nach der europäischen Norm EN 16931 (Format XRechnung 3.0).

- **Download:** In der festgeschriebenen Rechnung steht in der Tastenleiste **E-Rechnung (XML)**. Neben dem Status zeigt die Marke **E-Rechnung**, dass es eine Datei gibt. Sie schicken die XML selbst an den Kunden (E-Mail oder Rechnungsportal). Das PDF bleibt daneben als Archiv-PDF und Ansicht bestehen.
- **Einmal erzeugt, nie geändert:** Die XML wird beim Festschreiben erzeugt, mit einer Prüfsumme abgelegt und nie neu erzeugt. Spätere Änderungen an Kunde oder Firmendaten ändern sie nicht. Stimmt die Prüfsumme nicht mehr, meldet das Programm es, statt still eine neue zu erzeugen.
- **Prüfung vor dem Festschreiben:** Fehlt etwas, das die XRechnung verlangt, wird **nicht festgeschrieben** und keine Nummer verbraucht. Die Meldung nennt jede fehlende Angabe, zum Beispiel „Beim Kunden fehlt die E-Mail-Adresse oder eine Leitweg-ID“ oder „In den Firmendaten fehlt die IBAN“. Tragen Sie sie nach und schreiben Sie erneut fest.
- **Ältere Rechnungen:** Klicken Sie bei einer festgeschriebenen Rechnung ohne XML auf **E-Rechnung (XML)**, entsteht sie jetzt einmal aus den Daten, die beim Festschreiben gespeichert wurden. Eine Leitweg-ID, die erst später beim Kunden eingetragen wurde, wird dafür übernommen.
- **Stornorechnung:** Sie geht als **Gutschriftsanzeige** mit **positiven** Beträgen hinaus und verweist auf die stornierte Rechnung. Im Beleg selbst stehen die Beträge negativ.
- **Schlussrechnung:** Die Anzahlungen stehen als **vorausgezahlter Betrag**, die Anzahlungsrechnungen werden namentlich genannt.
- **Steuerfrei und nicht steuerbar:** Positionen mit 0 % Steuer übernehmen den **Steuerhinweis** als Grund. Steht dort „nicht steuerbar“, „Ausland“ oder „außerhalb“, gilt die Position als nicht steuerbar, sonst als steuerbefreit.
- **Einheiten:** km, Tag, Nacht, Std und alles andere (Fahrt, pauschal, Stück) werden in die Einheiten der Norm umgesetzt.
- **Leitweg-ID:** Beim Kunden eingetragen, geht sie als Käuferreferenz und elektronische Adresse in die Datei, sonst die Kundennummer und die E-Mail-Adresse.
- **Prüfung der Datei:** {{app}} prüft die erzeugte XML gegen das Schema der Norm. Die fachlichen Regeln der Rechnungsportale (zum Beispiel des Bundes) prüft ein eigenes Werkzeug, der KoSIT-Validator. Prüfen Sie eine erste Beispieldatei damit, bevor Sie sie an einen Kunden schicken.
- **Wer darf:** Alle, die Belege sehen (Administrator, Disponent, Buchhaltung). Festschreiben dürfen Administrator und Buchhaltung.

> **Noch nicht:** ZUGFeRD (PDF mit eingebetteter XML), Anlagen wie die Fahrtenliste einer Sammelrechnung und der Versand per E-Mail aus der Software.

## Folgebelege {#folgebelege}

Aus einem **festgeschriebenen** Beleg erstellen Sie den nächsten Schritt der Belegkette:

- Aus einem Angebot: Auftragsbestätigung, Anzahlungsrechnung oder Rechnung
- Aus einer Auftragsbestätigung: Anzahlungsrechnung oder Rechnung

Die Software legt einen neuen **Entwurf** an und übernimmt Kunde, Texte und Positionen. Sie prüfen und ändern ihn und schreiben ihn dann fest. Der Verweis auf den Ursprungsbeleg bleibt sichtbar.

Bei der **Anzahlungsrechnung** schlägt die Software 30 % des Auftrags vor, je Steuersatz eine Position („Anzahlung 30 % auf Angebot …“). Den Betrag ändern Sie im Entwurf wie jede Position.

## Schlussrechnung und Anzahlungen {#schlussrechnung}

Die **Schlussrechnung** ist die Rechnung, die aus demselben Angebot (oder derselben Auftragsbestätigung) entsteht wie die Anzahlungsrechnungen. Sie zeigt die **volle Leistung** und zieht die erhaltenen Anzahlungen **getrennt** ab, nicht als negative Position:

- Gesamtbetrag brutto (volle Leistung, mit Steuer je Satz)
- abzüglich Anzahlung *Nummer, Datum* (mit Netto und Steuer der Anzahlung)
- **Zu zahlen**: der Restbetrag

Welche Anzahlungen abgezogen werden:

- nur **festgeschriebene** Anzahlungsrechnungen aus demselben Ursprungsbeleg, **nicht** Entwürfe und nicht stornierte;
- jede Anzahlung nur von **einer** gültigen Schlussrechnung. Wird die Schlussrechnung storniert, ist die Anzahlung wieder frei;
- Anzahlungen, die erst nach dem Anlegen des Entwurfs festgeschrieben werden, zählen beim Festschreiben mit.

Übersteigen die Anzahlungen den Rechnungsbetrag, lässt sich die Schlussrechnung nicht festschreiben: Prüfen Sie dann die Positionen. Storniert man eine Schlussrechnung, spiegelt der Storno-Beleg auch den Abzug.

> Die Steuer auf eine Anzahlung entsteht steuerlich mit dem Zahlungseingang. Die Software kennt den Zahlungseingang noch nicht und behandelt jede festgeschriebene Anzahlungsrechnung als erhalten. Klären Sie mit Ihrem Steuerberater, wie Sie Anzahlungen verbuchen.

Noch nicht möglich sind Teilgutschriften und neue Versionen eines Angebots. PDF, E-Mail-Versand, DATEV-Export und die E-Rechnung folgen später.
