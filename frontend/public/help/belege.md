# Belege {#belege}

Hier erstellen Sie Angebote, Auftragsbestätigungen und Rechnungen. Der Bereich ist für Administrator, Disponent und Buchhaltung zugänglich. Dieses Thema ist keine Rechts- oder Steuerberatung: Welche Angaben auf eine Rechnung gehören und welcher Steuersatz gilt, klären Sie mit Ihrem Steuerberater.

## Belegliste {#belegliste}

Die Liste zeigt 20 Belege pro Seite. Ein Klick auf eine Spaltenüberschrift sortiert danach, ein zweiter Klick kehrt die Richtung um. Ohne Wahl stehen die neuesten zuerst, Entwürfe oben (ein Entwurf hat noch kein Datum und gilt als der neueste). Leere Werte stehen immer am Ende. Die Belegart sortiert in der Reihenfolge des Geschäftsgangs (Angebot bis Stornorechnung).

- **Suche:** Das Suchfeld durchsucht die Belegnummer, den Betreff und den Kunden (Name und Kundennummer). Treffer sind gelb markiert. Steckt der Treffer in einem Feld, das die Liste nicht zeigt (die Kundennummer), erscheint unter dem Namen der Hinweis „Treffer in Kundennr.“.
- **Belegart und Stand:** Die Filter zeigen zum Beispiel nur Rechnungen oder nur Entwürfe.
- **Anordnung:** „Je Kunde mit Entstehungskette“ (statt „Nach Spalte sortiert“) stellt die Belege eines Kunden untereinander, alphabetisch nach Kunde. Darin stehen die Belegketten in der Reihenfolge ihrer Entstehung, zum Beispiel Angebot, darunter eingerückt die Auftragsbestätigung, darunter die Rechnung und ein eventueller Storno. So lässt sich nachvollziehen, woraus ein Beleg entstanden ist. Unter der Belegart steht außerdem immer „aus Angebot AN-…“, sobald ein Beleg aus einem anderen entstanden ist. Die Wahl bleibt beim Wechsel in einen Beleg und zurück erhalten.
- **Stand:** *Entwurf* ist noch änderbar und hat keine Nummer. *Festgeschrieben* hat eine Nummer und ist gesperrt. *Storniert* ist ein festgeschriebener Beleg, der durch einen Storno-Beleg ausgeglichen wurde.

## Beleg anlegen {#beleg-anlegen}

Ein **Angebot** entsteht auf drei Wegen: **aus einer Reise** (empfohlen bei Reisen mit mehreren Strecken: Sie planen unter [Reisen](#reisen) Hinfahrt, Fahrten vor Ort und Rückfahrt, dann erstellen Sie das Angebot daraus), **direkt aus einer Tour** über die [Streckenplanung](/hilfe/strecken#angebot-aus-tour) (Knopf **Angebot erstellen**, der Kurzweg für eine einzelne Fahrt) oder **ohne Strecke** hier unter **Belege › Neuer Beleg** (Sie tragen die Positionen selbst ein). Alle Wege ergeben denselben Beleg. Der Weg im Einzelnen: [Angebot erstellen](/hilfe/fahrten#angebot).

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

## Reise {#reise}

Eine **Reise** fasst Kunde, Strecken und alle Belege einer Fahrt zusammen. Sie legen sie unter **Reisen › Neue Reise planen** an (Name und Kunde) und planen dort **Strecke für Strecke**, siehe [Mehrere Strecken](#mehrere-strecken). Das Angebot erstellen Sie danach auf der Reise. Eine Reise entsteht auch von selbst, wenn Sie in der Streckenplanung die Taste **Angebot erstellen** nehmen. Im Beleg steht oben die **Reise-Leiste**:

- Sie zeigt alle Belege der Reise in der Reihenfolge ihrer Anlage: Angebot, Auftragsbestätigung, Anzahlung, Rechnung. Der aktuelle Beleg ist hervorgehoben, ein stornierter durchgestrichen. Ein Klick öffnet den Beleg. **Reise öffnen: Strecken ändern** führt zur Reise.
- Darunter steht der **nächste Schritt**: bei einem Entwurf „Kunden wählen, Positionen prüfen und den Beleg festschreiben“, bei einem festgeschriebenen Beleg zum Beispiel „Auftragsbestätigung erstellen“ (Tasten unten im Formular).
- Wählen Sie im ersten Beleg den **Kunden** oder ändern das Fahrtdatum, gilt das auch für die Reise. Folgebelege und der Storno bleiben in derselben Reise.
- Ist die **Auftragsbestätigung** festgeschrieben, zeigt die Leiste die **Fahrtaufträge** der Reise (zu disponieren, disponiert, ausgegeben) mit einem Link dorthin. Weicht die Zahl der Busse in der Auftragsbestätigung von den Fahrtaufträgen ab, steht „Fahrtaufträge prüfen“. Mehr unter [Der Fahrtauftrag](/hilfe/fahrten#fahrtauftrag).

Belege, die Sie von Hand anlegen („Neuer Beleg“), haben keine Reise und keine Leiste. Löschen Sie den ersten Entwurf einer Reise des Kurzwegs, verschwindet auch sie, wenn sonst kein Beleg zu ihr gehört. Eine von Hand geplante Reise bleibt bestehen. Sie löschen sie auf der Reise-Seite mit **Reise löschen** (Administrator und Disponent): Dabei verschwinden die Strecken und ein vorhandener Entwurf, nach einer Rückfrage, die nennt, was gelöscht wird. Gibt es **festgeschriebene** Belege, geht das nicht. Findet die Reise nicht statt, nehmen Sie [Reise absagen](#reise-absagen). Die Touren bleiben im Tour-Archiv.

## Die Liste der Reisen {#reisen}

Unter **Fahrten und Abrechnung › Reisen** sehen Sie alle [Reisen](#reise) auf einen Blick. Mit **Neue Reise planen** (Administrator und Disponent) legen Sie eine an: Name und, wenn Sie ihn schon kennen, der Kunde.

- **Ansichten:** Die Tasten **Laufende** (Vorgabe), **Abgeschlossen**, **Abgesagt** und **Alle** bestimmen, welche Reisen Sie sehen. **Abgeschlossen** ist eine Reise von selbst, wenn ihr Reisedatum vorbei ist, eine Rechnung der Reise festgeschrieben ist und keine ihrer Rechnungen mehr offen ist (kein Entwurf, vollständig bezahlt). Anzahlungen zählen dabei nicht. Eine Reise ohne Reisedatum bleibt unter „Laufende“. **Zurzeit** entsteht aus einer Reise noch keine Schlussrechnung (sie folgt mit der Rechnung aus Fahrtaufträgen), deshalb bleiben auch durchgeführte Reisen vorerst unter „Laufende“. Findet die Suche Reisen außerhalb der gewählten Ansicht, steht das über der Liste, mit **Alle anzeigen**.
- **Die Liste** zeigt je Reise den Namen mit Zahl der Strecken und Belege, den Kunden, den Zeitraum, den **Stand** und den **Betrag brutto**. Die neueste Reise steht oben. Die Suche trifft den Namen der Reise und den Kunden am Wortanfang („Wien“, „Sonnenschein“, „Mustermann,Max“).
- **Stand und Betrag** sind aus den Belegen abgeleitet und nirgends gespeichert: Es zählt der weitestgehende gültige Beleg (Rechnung vor Auftragsbestätigung vor Angebot, Stornierte zählen nicht). Anzahlungen bestimmen den Stand nicht. Ein Klick auf den Stand öffnet diesen Beleg.
- **Die Reise öffnen** (Auge-Symbol oder Name) ist der Ort, an dem Sie planen: Name und Kunde ändern, **Strecken** hinzufügen, ändern und entfernen, das **Angebot** erstellen und aktualisieren. Dazu stehen dort alle **Belege** mit Betrag und Stand und oben der **nächste Schritt**. Bei Strecken in einem festgeschriebenen Beleg zeigt die Tabelle den dort festgehaltenen Stand („Stand aus …“).
- **Umbenennen:** Mit dem **Stift** neben dem Namen auf der Reise geben Sie ihr einen eigenen Namen, zum Beispiel „Klassenfahrt Realschule Mai“ (Enter oder **Speichern**, Esc oder **Abbrechen**). Der Name gilt sofort in der Liste, in der Suche und in der Reise-Leiste im Beleg. Er steht **nicht** auf Belegen und PDFs, deshalb ändert ein neuer Name keinen festgeschriebenen Beleg. Umbenennen dürfen Administrator, Disponent und Buchhaltung.
- **Kunde:** Auf der Reise wählen Sie den Kunden wie im Beleg (Suchfeld, Trefferliste, **Ändern**). Das Angebot übernimmt ihn beim Erstellen; ein schon bestehender Entwurf behält seinen Kunden.
- Die Buchhaltung sieht Reisen und kann sie umbenennen, aber nicht planen. Die Touren stehen im [Tour-Archiv](/hilfe/strecken#tour-archiv-liste).

## Reise absagen {#reise-absagen}

Findet eine Reise nicht statt, öffnen Sie sie und klicken unten auf **Reise absagen** (Administrator und Disponent). Ein Fenster zeigt vorher, was geschieht:

- Alle **Fahrtaufträge**, die noch nicht erledigt sind, werden mit abgesagt, auch schon ausgegebene. **Informieren Sie die Fahrer und Fremdunternehmer selbst**, die Software verschickt nichts. Das Fahrer-PDF trägt den Hinweis „ABGESAGT“.
- Ist schon **Geld eingegangen** (Anzahlung oder Rechnung), stehen die Beträge im Fenster. Sie werden nicht automatisch zurückgezahlt oder verrechnet: Eine Rückzahlung buchen Sie bei den [Zahlungen](#zahlungen) des Belegs. Eine Stornogebühr stellen Sie über **Neuer Beleg** als Rechnung.
- Sie geben an, **wann** abgesagt wurde, und den **Grund**.

Die Reise und alle Belege bleiben erhalten. Die Reise steht danach unter **Abgesagt**. Strecken lassen sich nicht mehr ändern, und Angebot, Auftragsbestätigung und Anzahlungsrechnung lassen sich nicht mehr erstellen oder festschreiben. Rechnungen und Stornorechnungen bleiben möglich.

**Absage zurücknehmen:** Die Taste steht oben auf der abgesagten Reise. Die Fahrtaufträge gelten dann wieder wie vorher (informieren Sie die Fahrer erneut). Eine abgeschlossene Reise lässt sich nicht absagen.

## Mehrere Strecken und Fahrzeugtypen {#mehrere-strecken}

Eine Reise besteht oft aus mehreren Strecken: Hinfahrt, Fahrten vor Ort, Rückfahrt. Auf einer Strecke fahren manchmal verschiedene Busse (zum Beispiel ein Doppeldecker und ein Kleinbus nach Wien). Beides planen Sie **auf der Reise**, Schritt für Schritt, und erstellen danach das Angebot:

1. Öffnen Sie die Reise und klicken Sie oben bei **Nächster Schritt** auf **Strecke hinzufügen** (nach dem Festschreiben steht die Taste unter der Tabelle der Strecken). Die Streckenplanung öffnet sich.
2. Wählen Sie oben die **Art**: Hinfahrt, Fahrt vor Ort oder Rückfahrt (vorgeschlagen wird die passende nächste).
3. Legen Sie fest, **woher die Strecke kommt**:
   - **Route berechnen:** Orte eingeben, **Strecke berechnen**. Bei der Rückfahrt sind Von und Nach schon mit den vertauschten Orten der Hinfahrt belegt.
   - **Vorlage laden:** eine gespeicherte Tour aus dem Archiv übernehmen.
   - **Ohne Route:** bei **Werte für den Preis** Kilometer, Fahrzeit und Tage selbst eintragen. Siehe [Fahrt vor Ort](#fahrt-vor-ort).
4. Prüfen Sie bei **Angaben zur Strecke** Bezeichnung, **Datum der Fahrt** (freiwillig, dann rechnet {{app}} die Zuschläge für Wochenende und Feiertag), **Zuschlag** (freiwillig) und die **Fahrzeuge**: Typ und Anzahl, vorgeschlagen sind die der ersten Strecke. Mit **Weiteren Fahrzeugtyp** kommt eine Zeile dazu. Jeder Typ erscheint nur einmal.
5. Klicken Sie **Speichern**. Sie sind wieder auf der Reise und fügen dort die nächste Strecke hinzu.

Jede Strecke **merkt sich ihre Tour selbst**. Spätere Änderungen an der Tour im Archiv ändern die Strecke nicht.

### Das Angebot aus den Strecken

- **Angebot erstellen** (Karte „Nächster Schritt“ auf der Reise) legt einen Entwurf mit **allen Strecken** an: Für jeden Fahrzeugtyp entstehen Positionen aus Ihren [Preisen](/hilfe/einstellungen#preise), mit den **Preisen des Typs**, wo welche hinterlegt sind. Bei mehreren gleichen Bussen sind die Mengen entsprechend höher. Im Beleg stehen die Positionen **gruppiert** unter einer Überschrift mit Strecke und Fahrzeugen („Rückfahrt: Wien – München · 2 × Doppeldeckerbus“). Alle Positionen lassen sich danach ändern.
- In der Tabelle der Strecken zeigt die Spalte **Angebot**, wie jede Strecke zum Angebot steht (zum Entwurf, sonst zum festgeschriebenen): **neu** (noch keine Positionen), **geändert** (Datum, Fahrzeuge, Zuschlag oder Strecke haben sich seit der Berechnung geändert) oder **aktuell**.
- **Angebot aktualisieren** rechnet nur die Positionen der **neuen und geänderten Strecken** neu. Positionen **unveränderter Strecken bleiben mit allen Ihren Änderungen** unberührt. Bei geänderten Strecken fragt {{app}} vorher nach, weil von Hand geänderte Preise oder Texte genau dieser Positionen verloren gehen. Das gilt nur, solange das Angebot ein **Entwurf** ist: Nach dem Festschreiben lässt es sich nicht mehr abgleichen.
- Mit dem **Stift** [ändern Sie eine Strecke](#strecke-aendern). **Entfernen** (Papierkorb) nimmt die Strecke und ihre Positionen aus dem Entwurf. Steht die Strecke schon in einem festgeschriebenen Beleg, geht das nicht mehr.
- Fehlen Preise (Einstellungen › Preise), kommt eine Meldung und es wird nichts angelegt.
- Auftragsbestätigung, Anzahlung, Rechnung und Storno übernehmen die Gruppen. Das Fahrzeug wählt später der Disponent. Hier geht es nur um Typ und Anzahl.

### Eine Strecke ändern {#strecke-aendern}

Alles, was zu einer Reise gehört, ändern Sie **von der Reise aus**. Das [Tour-Archiv](/hilfe/strecken#tour-archiv-liste) enthält nur Vorlagen: Änderungen dort wirken nicht auf Ihre Reise.

Klicken Sie auf der Reise bei der Strecke auf den **Stift** (oder auf **Datum eintragen**). Die Streckenplanung öffnet sich mit dieser Strecke:

- **Route:** Betriebshof, Von, Zwischenhalte und Nach ändern, dann **Strecke berechnen**. Mit **Vorlage laden** übernehmen Sie stattdessen eine Vorlage.
- **Angaben zur Strecke:** Bezeichnung, Datum der Fahrt, Abfahrtszeit, Zuschlag und Fahrzeuge (auch mehrere Typen). Außer bei der Abfahrtszeit ist dafür keine neue Berechnung nötig.
- **Werte für den Preis:** Kilometer mit Gästen, Leerkilometer, Fahrzeit und Tage können Sie **überschreiben**. Geänderte Felder sind markiert, darunter steht der berechnete Wert. **Berechnete Werte nehmen** stellt ihn wieder her. Berechnen Sie die Strecke später neu, fragt {{app}}, ob Ihre Werte bleiben sollen.
- **Speichern** gilt für die Reise und führt dorthin zurück. **Abbrechen** verwirft die Änderungen. **Als Vorlage speichern** legt die Route zusätzlich im Tour-Archiv ab.

Danach steht die Strecke als **geändert**, bis Sie **Angebot aktualisieren** klicken. Ein festgeschriebener Beleg behält seinen Stand.

Bei Strecken, die Sie **vor dieser Funktion** angelegt haben, ist keine Karte gespeichert. Datum, Fahrzeuge und Werte ändern Sie ohne Berechnung. Wollen Sie die Route ändern, prüfen Sie Orte und Betriebshof und berechnen neu.

Eine **Strecke ohne Route** (von Hand eingetragen) öffnet sich in derselben Maske. Eine Route können Sie dort ergänzen, müssen es aber nicht.

### Fahrt vor Ort {#fahrt-vor-ort}

Am Reiseziel bieten Sie oft noch eine Fahrt an, zum Beispiel einen Ausflug. Dafür brauchen Sie **keine Tour** im Archiv:

**Beispiel:** Die Reise fährt von Mietingen nach Villach. Dort bieten Sie einen Ausflug zum Ossiacher See an.

1. Auf der Reise **Strecke hinzufügen** klicken.
2. Art **Fahrt vor Ort** wählen.
3. Ohne Orte einzugeben, eintragen: Bezeichnung „Ausflug Ossiacher See“, **60** Kilometer, Fahrzeit **2** Std. **30** Minuten, **1** Tag, den Fahrzeugtyp.
4. **Speichern** klicken, danach auf der Reise **Angebot aktualisieren**.

{{app}} legt die Positionen mit Ihren Preisen an, wie bei einer Tour. Eine **Leerfahrt** wird nicht berechnet, denn der Bus ist schon vor Ort. Im Beleg, in der Reise und im PDF steht „Fahrt vor Ort: Ausflug Ossiacher See“ mit Kilometern, Fahrzeit und Tagen. Brauchen Sie die genaue Route mit Halten im Beleg, geben Sie die Orte ein und berechnen die Strecke.

## Fahrtbeschreibung {#fahrtbeschreibung}

Beim **Festschreiben** hält {{app}} die Fahrtbeschreibung der Strecken im Beleg fest: je Strecke die **Halte** (mit Aufenthalt), die **Kilometer** mit Fahrgästen und als Leerfahrt, die **Lenkzeit**, die **Tage**, die einzelnen **Teilstrecken** und die **Fahrzeuge** (Typ und Anzahl). Bei einer Strecke mit eigenen Angaben (etwa einer [Fahrt vor Ort](#fahrt-vor-ort)) stehen dort Kilometer, Fahrzeit und Tage. Sie steht im Beleg unter den Positionen und im PDF nach der Summe.

- **Unveränderlich:** Der Stand gilt, wie er beim Festschreiben war. Ändern Sie später die Tour im Tour-Archiv, ändert sich der Beleg nicht, und das PDF zeigt weiter, was Sie dem Kunden angeboten haben.
- **Folgebelege:** Die Auftragsbestätigung und die Rechnung übernehmen den Stand des Angebots. Nur eine Strecke, die erst im Folgebeleg dazukommt, wird zu diesem Zeitpunkt aus dem Archiv gelesen.
- **Wo:** Angebot, Auftragsbestätigung und Rechnung. Anzahlungsrechnung und Storno haben keine Fahrtbeschreibung.
- **Nicht dabei:** Abfahrtszeiten und die Linie auf der Karte. Belege ohne Strecke (von Hand angelegt) und ältere Belege haben keine Fahrtbeschreibung.
- Im Entwurf sehen Sie einen Hinweis; die Fahrtbeschreibung selbst entsteht erst beim Festschreiben.

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

- **ZUGFeRD (PDF):** Die Taste **ZUGFeRD (PDF)** liefert das archivierte Beleg-PDF mit der eingebetteten XRechnung, also eine Datei, die ein Mensch lesen und ein Programm einlesen kann. Sie wird bei jedem Abruf aus dem archivierten PDF und der archivierten XML zusammengesetzt, beide nach Prüfsumme. Nutzen Sie sie für Empfänger, die ein lesbares PDF mit Daten wünschen. Für Behörden und Rechnungsportale ist die reine **XML** der sichere Weg.

> **Noch nicht:** Anlagen wie die Fahrtenliste einer Sammelrechnung und der Versand per E-Mail aus der Software. Ob das ZUGFeRD-PDF die strenge PDF/A-3-Prüfung besteht, ist noch nicht mit einem Prüfdienst bestätigt.

## Folgebelege {#folgebelege}

Aus einem **festgeschriebenen** Beleg erstellen Sie den nächsten Schritt der Belegkette:

- Aus einem Angebot: die Auftragsbestätigung
- Aus einer Auftragsbestätigung: die Anzahlungsrechnung

Die Software legt einen neuen **Entwurf** an und übernimmt Kunde, Texte und Positionen. Sie prüfen und ändern ihn und schreiben ihn dann fest. Der Verweis auf den Ursprungsbeleg bleibt sichtbar.

**Aus einem Angebot gibt es nur eine Auftragsbestätigung.** Sie ist die eine Zusage des Kunden, auf ihr bauen die Fahrtaufträge auf. Gibt es schon eine, zeigt das Angebot statt der Taste „Auftragsbestätigung erstellen“ nur einen Verweis auf die vorhandene. Ein Entwurf zählt mit: Festschreiben oder löschen Sie ihn, wenn Sie neu beginnen wollen.

### Warum gibt es keine Taste „Rechnung erstellen“? {#rechnung-nach-ab}

Das ist Absicht. **Die Rechnung entsteht nicht aus Angebot oder Auftragsbestätigung**, sondern aus den gefahrenen Fahrten (den Fahrtaufträgen mit ihrer Rückerfassung). Die Auftragsbestätigung nennt nur den vorläufigen Preis, die Rechnung soll zeigen, was wirklich gefahren wurde. Deshalb gibt es:

| Aus | entsteht |
|---|---|
| Angebot | die Auftragsbestätigung (nur eine) |
| Auftragsbestätigung | die Anzahlungsrechnung (eine) |
| Fahrtaufträge | die Rechnung (**folgt in einer späteren Etappe**) |

**Anzahlung fordern:** Öffnen Sie die Auftragsbestätigung und wählen Sie **Anzahlungsrechnung erstellen**. Die Anzahlungsrechnung bleibt mit der Auftragsbestätigung verbunden. Die Rechnung aus den Fahrtaufträgen wird sie berücksichtigen.

> **Bis diese Etappe da ist:** Eine Rechnung ohne Anzahlung legen Sie über **Belege › Neuer Beleg** an und tragen die Positionen selbst ein. Für eine Reise **mit Anzahlung** ist dieser Weg nicht geeignet, denn eine so angelegte Rechnung ist nicht mit der Auftragsbestätigung verbunden.

Eine **Anzahlungsrechnung** gibt es nur zur Auftragsbestätigung, und zwar **eine** je Auftrag (auch ein Entwurf zählt). Gibt es sie schon, steht statt der Taste ein Verweis darauf. Erst nach ihrem Storno oder dem Löschen des Entwurfs lässt sich eine neue erstellen. Die Software schlägt 30 % des Auftrags vor, je Steuersatz eine Position („Anzahlung 30 % auf Auftragsbestätigung …“). Den Betrag ändern Sie im Entwurf wie jede Position.

## Schlussrechnung und Anzahlungen {#schlussrechnung}

Die Schlussrechnung **folgt in einer späteren Etappe**, zusammen mit der [Rechnung aus den Fahrtaufträgen](#rechnung-nach-ab). So wird sie arbeiten:

Die **Schlussrechnung** ist die Rechnung, die zu derselben Auftragsbestätigung gehört wie die Anzahlungsrechnungen. Sie zeigt die **volle Leistung** und zieht die erhaltenen Anzahlungen **getrennt** ab, nicht als negative Position:

- Gesamtbetrag brutto (volle Leistung, mit Steuer je Satz)
- abzüglich Anzahlung *Nummer, Datum* (mit Netto und Steuer der Anzahlung)
- **Zu zahlen**: der Restbetrag

Welche Anzahlungen abgezogen werden:

- nur **festgeschriebene** Anzahlungsrechnungen aus demselben Ursprungsbeleg, **nicht** Entwürfe und nicht stornierte;
- jede Anzahlung nur von **einer** gültigen Schlussrechnung. Wird die Schlussrechnung storniert, ist die Anzahlung wieder frei;
- Anzahlungen, die erst nach dem Anlegen des Entwurfs festgeschrieben werden, zählen beim Festschreiben mit.

Übersteigen die Anzahlungen den Rechnungsbetrag, lässt sich die Schlussrechnung nicht festschreiben: Prüfen Sie dann die Positionen. Storniert man eine Schlussrechnung, spiegelt der Storno-Beleg auch den Abzug.

> Die Steuer auf eine Anzahlung entsteht steuerlich mit dem Zahlungseingang. Den Zahlungseingang erfassen Sie an der Anzahlungsrechnung. Klären Sie mit Ihrem Steuerberater, wie Sie Anzahlungen verbuchen.

Noch nicht möglich sind Teilgutschriften und neue Versionen eines Angebots. PDF, E-Mail-Versand, DATEV-Export und die E-Rechnung folgen später.
