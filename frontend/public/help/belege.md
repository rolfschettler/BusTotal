# Belege {#belege}

Hier erstellen Sie Angebote, Auftragsbestätigungen und Rechnungen. Der Bereich ist für Administrator, Disponent und Buchhaltung zugänglich. Dieses Thema ist keine Rechts- oder Steuerberatung: Welche Angaben auf eine Rechnung gehören und welcher Steuersatz gilt, klären Sie mit Ihrem Steuerberater.

## Belegliste {#belegliste}

Die Liste zeigt 20 Belege pro Seite, die neuesten zuerst. Entwürfe stehen oben.

- **Suche:** Das Suchfeld durchsucht die Belegnummer, den Betreff und den Kunden (Name und Kundennummer). Treffer sind gelb markiert. Steckt der Treffer in einem Feld, das die Liste nicht zeigt (die Kundennummer), erscheint unter dem Namen der Hinweis „Treffer in Kundennr.“.
- **Belegart und Stand:** Die Filter zeigen zum Beispiel nur Rechnungen oder nur Entwürfe.
- **Stand:** *Entwurf* ist noch änderbar und hat keine Nummer. *Festgeschrieben* hat eine Nummer und ist gesperrt. *Storniert* ist ein festgeschriebener Beleg, der durch einen Storno-Beleg ausgeglichen wurde.

## Beleg anlegen {#beleg-anlegen}

Wählen Sie **Neuer Beleg**, dann die Belegart und den Kunden. Die Belegarten:

- **Angebot** und **Auftragsbestätigung**
- **Anzahlungsrechnung**: für eine Anzahlung vor der Fahrt
- **Rechnung**
- **Gutschrift / Storno** legen Sie nicht selbst an, sie entsteht beim [Stornieren](#stornieren).

Die Belegart lässt sich nach dem Anlegen nicht mehr ändern. Jeder Beleg besteht aus Kopfdaten, Positionen und Texten:

- **Positionen:** Bezeichnung, Menge, Einheit, Einzelpreis (netto) und Steuersatz. Die Summen darunter sind eine Vorschau, maßgeblich ist die Berechnung beim Speichern. Die Steuer wird je Steuersatz aus der Nettosumme gerechnet.
- **Steuersatz:** Zur Auswahl stehen die Sätze aus den Einstellungen. Bei 0 % verlangt eine Rechnung einen Grund im **Steuerhinweis**, zum Beispiel „steuerfrei“ oder „Ausland“. Welcher Satz für welche Fahrt gilt, ist eine Frage an den Steuerberater: Er hängt von der Art der Fahrt und der Strecke ab.
- **Leistung von / bis:** Das Fahrtdatum. Bei Rechnungen ist **Leistung von** Pflicht, bei Anzahlungsrechnungen nicht.
- **Belegdatum:** Bleibt es leer, gilt der Tag des Festschreibens.
- **Zahlungsziel:** Bleibt es leer, gilt das Ziel des Kunden, sonst das der Firma (siehe [Einstellungen](/hilfe/einstellungen)).

## Entwurf und Festschreiben {#entwurf-und-festschreiben}

Ein **Entwurf** ist frei änderbar und hat noch keine Nummer. Sie können ihn jederzeit löschen. Dabei geht keine Nummer verloren.

**Festschreiben** vergibt die Nummer und sperrt den Beleg. Das dürfen Administrator und Buchhaltung. Beim Festschreiben passiert Folgendes:

- Die Pflichtangaben werden geprüft. Bei Rechnungen sind das unter anderem die Anschrift des Kunden, die Steuernummer oder USt-IdNr. Ihrer Firma, der Leistungszeitpunkt und mindestens eine Position. Fehlt etwas, nennt die Meldung jede Angabe einzeln, und es wird keine Nummer verbraucht.
- Absender und Empfänger werden **als Kopie** im Beleg gespeichert. Ändern Sie später den Kunden oder Ihre Firmendaten, bleibt der Beleg, wie er war.
- Die Nummer kommt aus dem [Nummernkreis](/hilfe/einstellungen#nummernkreise) der Belegart. Rechnungen und Anzahlungsrechnungen teilen sich einen Kreis, damit die Rechnungsnummern fortlaufend bleiben.

Ein festgeschriebener Beleg lässt sich nicht mehr ändern oder löschen, weder in der Oberfläche noch im Hintergrund. Das verlangen die Grundsätze ordnungsmäßiger Buchführung (GoBD). Hat sich ein Fehler eingeschlichen, [stornieren](#stornieren) Sie den Beleg und erstellen einen neuen.

## Stornieren {#stornieren}

Rechnungen und Anzahlungsrechnungen lassen sich stornieren (Administrator und Buchhaltung). Die Software erzeugt dabei einen eigenen, sofort festgeschriebenen **Storno-Beleg** (Gutschrift) mit negativen Beträgen und eigener Nummer. Der ursprüngliche Beleg bleibt unverändert erhalten und zeigt nur noch den Hinweis „Storniert“.

Ein Beleg lässt sich nur einmal stornieren. Entwürfe werden nicht storniert, sondern gelöscht. Angebote und Auftragsbestätigungen werden nicht storniert.

## PDF {#pdf}

Mit **PDF** (in der Tastenleiste des Belegs und als Symbol in der Liste) öffnet sich der Beleg als PDF in einem neuen Tab. Von dort drucken oder speichern Sie ihn.

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
