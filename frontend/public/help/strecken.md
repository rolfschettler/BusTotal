# Streckenplanung {#strecken}

Hier berechnen Sie eine Tour: **Strecke**, **Fahrzeit** und den **zeitlichen Ablauf mit den gesetzlichen Pausen und Ruhezeiten**. Den Bereich sehen Administrator und Disponent. Die Berechnung ist eine **Planungshilfe** und ersetzt keine Auswertung des Fahrtenschreibers. Eine berechnete Tour können Sie im [Tour-Archiv speichern](#tour-archiv) und später wieder laden.

## Eine Tour berechnen {#streckenplanung}

1. **Von:** Schreiben Sie den Startort, zum Beispiel „88400 Biberach“ oder „Ulm Hbf“. Ihr Text bleibt so stehen, wie Sie ihn eingegeben haben, und er ist auch der **Name des Halts** im Tour-Archiv, im Angebot und im PDF. Gesucht wird erst beim Berechnen, vom Ortsdienst kommt nur die Lage. Nennt der Ortsdienst den gefundenen Ort anders als Sie, steht das zur Kontrolle unter dem Feld („Verwendet: …“). Passt der Ort nicht, schreiben Sie ihn genauer und berechnen neu. Liegt der Punkt auf der Karte trotzdem falsch (bei einem Dorf nimmt die Suche manchmal den Mittelpunkt der Gemeinde, der im Wald liegen kann), klicken Sie unter dem Feld auf **Anderen Ort wählen**. Die Software zeigt dann die Treffer des Ortsdienstes, zum Beispiel „Bahnhof“ oder „Strandbad“ des Ortes, und Sie wählen den richtigen. Der Name im Feld bleibt Ihr Text, nur die Lage wird die des gewählten Treffers. Die Wahl gilt künftig für denselben Text.
2. **Nach:** Genauso das Ziel.
3. **Über** (freiwillig): Mit **Zwischenhalt (über)** kommt ein weiteres Ortsfeld dazu, so oft Sie wollen. Mit den Pfeilen ändern Sie die Reihenfolge, mit dem Kreuz entfernen Sie einen Halt. **Tauschen** vertauscht Von und Nach. Mit **Rückfahrt zum Start (Von)** fährt die Tour am Ende zum Start zurück. Ist ein **Betriebshof** angegeben, fährt der Bus von dort anschließend weiter zum Betriebshof (Nach → Von → Betriebshof, als Leerfahrt). Ab **zwei Zwischenhalten** können Sie **Reihenfolge der Zwischenhalte optimieren** anhaken: Die Software ordnet die Über-Orte so, dass die Fahrzeit am kürzesten ist (Von und Nach bleiben stehen), und zeigt die neue Reihenfolge in den Feldern. Das Kästchen ist immer zu sehen, bei weniger als zwei Zwischenhalten ausgegraut. Haben Sie zwei oder mehr Zwischenhalte und nicht optimiert, zeigt die Seite nach **Ihrer eigenen Berechnung** (nicht bei einer geladenen Tour) unter dem Ergebnis einen **Tipp** („Sollte die Route nicht Ihren Vorstellungen entsprechen, versuchen Sie eine Optimierung der Zwischenstopps“) mit der Taste **Optimieren und neu berechnen**. Danach steht dort, ob die Reihenfolge geändert wurde oder schon die kürzeste war.
4. **Fahrzeugtyp und Anzahl** (freiwillig): Sie planen mit „Doppeldecker“ oder „Kleinbus“ und der Zahl der Busse. Das konkrete Fahrzeug wählt der Disponent erst kurz vor der Fahrt. Der Typ bestimmt die Preise im Angebot.
5. **Bestimmtes Fahrzeug** (freiwillig): Bei der Route werden Höhe, Breite, Länge und Gewicht des Fahrzeugs beachtet (zum Beispiel niedrige Brücken). Wählen Sie eines, wird sein Typ als Fahrzeugtyp übernommen. Fehlen beim Fahrzeug Maße oder Gewicht, weist die Seite darauf hin; die Route wird dann ohne diese Grenzen berechnet. Mit Fahrzeug erscheinen außerdem die **Fahrzeugkosten** (Kilometer mal Kostensatz des Fahrzeugs).
6. **Abfahrt** (freiwillig): Mit Datum und Uhrzeit zeigt der Zeitplan echte Zeitpunkte, ohne Abfahrt den Versatz seit Beginn („+4:30“).
7. **Strecke berechnen** (oder Enter in einem Ortsfeld). Dafür genügen **Von** und **Nach**.

Ein Ort, der schon einmal gesucht wurde, wird nicht erneut gesucht: Der Browser merkt sich die Orte zu Ihren Eingaben. Das schont das Tageskontingent des Routing-Dienstes. Groß- und Kleinschreibung, doppelte Leerzeichen und ein Komma oder Punkt am Ende spielen dabei keine Rolle.

Die Karte zeigt die Orte (A = Von, Zahlen = Über, Z = Nach) und die berechnete Strecke. Ändern Sie danach etwas an der Eingabe (zum Beispiel einen Halt), werden Zahlen und Zeitplan blass und mit einem Hinweis als **veraltet** gekennzeichnet. Sie berechnen mit **Strecke berechnen** neu. Klicken Sie stattdessen gleich auf **Als Vorlage speichern** oder **Angebot erstellen**, fragt ein Dialog, ob die Strecke zuerst neu berechnet werden soll. Ohne Ihr Ja wird nie neu berechnet und nie ein veraltetes Ergebnis gespeichert.

**Aufbau der Seite:** Oben stehen links die **Route** und rechts die **Karte**. Darunter folgen das Ergebnis, **Fahrt** (Fahrzeugtyp, Anzahl, Fahrtart, Fahrzeug, Abfahrt) und **Angebot** mit **Vorlage**. Über **Von** wählen Sie den [Betriebshof](#betriebshof). **Aufenthalte und lange Tage** sind unten in der Route eingeklappt.

## Ort wählen, wenn die Karte falsch liegt {#ort-waehlen}

Die Ortssuche arbeitet mit dem Text, den Sie eingeben, und nimmt den besten Treffer. Bei einem Dorf ist das manchmal der Mittelpunkt der Gemeinde, der abseits im Wald liegen kann, oder eine Adresse ist dem Dienst unbekannt. Unter jedem Ortsfeld finden Sie deshalb **Anderen Ort wählen** (bzw. **Ort aus Trefferliste wählen**, wenn noch nichts gefunden wurde):

1. Ein Klick zeigt bis zu sechs **Treffer** des Ortsdienstes. Der Ort, der gerade gilt, hat ein Häkchen.
2. Kennt der Dienst Ihre Adresse nicht, ändern Sie im kleinen **Suchfeld** der Auswahl den Text, zum Beispiel auf einen **Bahnhof, See oder Ortsteil in der Nähe**, und klicken **Suchen**.
3. Ein Klick auf den richtigen Treffer übernimmt dessen **Lage**. Der **Name** im Feld bleibt Ihr Text. Danach berechnen Sie neu.

Die Wahl wird für denselben Text gemerkt, die Auswahl fragt dieselbe Suche nicht noch einmal an (das schont das Kontingent des Dienstes). Unter dem Feld steht immer, welcher Ort gefunden wurde („Gefundener Ort“). Ist er gelb markiert, passt er offensichtlich nicht zu Ihrer Eingabe: Dann schreiben Sie genauer oder wählen einen anderen Treffer.

## Betriebshof und Leerfahrten {#betriebshof}

Bei **Betriebshof** (über dem Feld **Von**) legen Sie fest, ob und von welchem Betriebshof gefahren wird. Mit **Kein Betriebshof** beginnt die Tour direkt bei „Von“. Wählen Sie einen, stehen darunter zwei Häkchen:

- **Anfahrt vom Betriebshof zu „Von“**
- **Rückfahrt zum Betriebshof** (vom letzten Ort)

Die Zeile **Fahrtweg** zeigt das Ergebnis, zum Beispiel „Von → Nach → Betriebshof“. Fahrten vom und zum Betriebshof sind **Leerfahrten**, also ohne Fahrgäste. Sie werden getrennt ausgewiesen („davon Leerfahrt“), weil sie in der Kalkulation anders behandelt werden. Auf der Karte trägt der Betriebshof ein „H“.

Bei einer **neuen Strecke einer Reise** ist passend zur Art vorgewählt:

| Art | Fahrtweg |
|---|---|
| Hinfahrt | Betriebshof → Von → Nach |
| Rückfahrt | Von → Nach → Betriebshof |
| Fahrt vor Ort | Von → Nach (kein Betriebshof) |

Sie können jede Vorgabe ändern.

Die Betriebshöfe Ihrer Firma pflegt der Administrator unter [Einstellungen › Betriebshöfe](/hilfe/einstellungen#betriebshoefe). Das Feld bietet sie zur Auswahl an, der **Standard** ist schon vorgewählt. Ihre Koordinaten sind gespeichert, beim Berechnen wird dafür nichts gesucht. Für eine einzelne Tour gibt es **Anderer Ort …**: Der Text wird beim Berechnen gesucht und nicht gespeichert.

## Aufenthalt und „Fahrer frei“ {#aufenthalt}

Unter **Aufenthalte und lange Tage** tragen Sie bei jedem gewählten Ort den **Aufenthalt in Minuten** ein. Beim Start ist das die Arbeit vor der Abfahrt, zum Beispiel das Einsteigen der Gäste.

- Normalerweise zählt der Aufenthalt als **Arbeitszeit** des Fahrers.
- Mit **Fahrer frei** hat der Fahrer in dieser Zeit frei (zum Beispiel, während die Gäste eine Stadtführung haben). Dann zählt ein Aufenthalt **ab 45 Minuten als Lenkpause** und ein Aufenthalt **ab 11 Stunden** (Übernachtung) **als Tagesruhezeit**.

## Pausen und Ruhezeiten {#lenkzeiten}

Die Planung rechnet nach der Verordnung (EG) Nr. 561/2006 mit der einfachen, sicheren Auslegung:

- Nach spätestens **4,5 Stunden Lenkzeit** folgt eine **Pause von 45 Minuten** am Stück. Die erlaubte Teilung in 15 und 30 Minuten wird nicht ausgenutzt; das ist die Reserve für Verzögerungen.
- Pro Tag sind höchstens **9 Stunden** Lenkzeit erlaubt. Mit **Lange Tage** erlauben Sie an den ersten ein oder zwei Tagen 10 Stunden (je Woche höchstens zweimal).
- Danach folgt die **Tagesruhezeit von 11 Stunden**. Ein Arbeitstag mit Lenken, Pausen und Halten dauert höchstens 13 Stunden.

Der **Zeitplan** zeigt jeden Abschnitt: Fahrt, Pause, Halt und Ruhezeit, gegliedert nach Tagen. Die Kennzahl „Fahrtage“ zählt nur Tage, an denen gefahren wird.

> **Nicht geprüft** werden die Wochenlenkzeit (56 Stunden), die Doppelwoche, die Wochenruhezeit, die verkürzte Tagesruhezeit und mehrere Fahrer. Dauert eine Tour länger als 6 Tage oder lenkt der Fahrer mehr als 56 Stunden, erscheint ein Hinweis. Ob eine Tour wirklich zulässig ist, klären Sie fachlich ab (Entwurf zur Prüfung durch den Auftraggeber).

## Vorlagen: Tour speichern und wiederverwenden {#tour-archiv}

Eine berechnete Tour können Sie als **Vorlage** im **Tour-Archiv** ablegen und wiederverwenden. Das spart die Eingabe und die Berechnung. Eine Vorlage ist unabhängig von Reisen und Belegen: Ändern Sie sie, bleibt jede Reise, wie sie ist.

**Speichern:** In der Tastenleiste unten rechts steht **Als Vorlage speichern**. Eine Tour braucht dafür zwei Halte, oder einen Halt und einen Betriebshof der Firma (Betriebshof → Halt → Betriebshof); sonst bleibt die Taste gesperrt, und ein Angebot entsteht ohne Archiv. Der Name ist mit „Von – Nach“ vorgeschlagen, Sie können ihn ändern. Gespeichert werden die Halte (mit Aufenthalt und „Fahrer frei“), die berechnete Strecke mit Zeitplan und **Linie auf der Karte**, die Fahrtart sowie Fahrzeugtyp und Anzahl. **Nicht** gespeichert werden die Abfahrtszeit und ein bestimmtes Fahrzeug, denn beides gehört zur einzelnen Fahrt. Ein „anderer Ort“ als Betriebshof wird ebenfalls nicht mitgespeichert, wohl aber ein Betriebshof Ihrer Firma.

**Laden:** Oben rechts öffnet **Vorlage laden** die Liste. Suchen Sie nach dem Namen oder nach einem Ort (am Wortanfang, die Treffer sind markiert) und klicken Sie **Laden**. Die Planung füllt sich mit allen Angaben und zeigt das gespeicherte Ergebnis sofort, **ohne neue Berechnung**. Die Abfahrt ist danach leer, weil eine Tour keinen Termin hat. Tragen Sie die neue **Abfahrt** ein und berechnen Sie neu, wenn Sie Uhrzeiten und die Zuschläge für Wochenende und Feiertag brauchen.

**Ändern:** Haben Sie eine geladene Vorlage verändert, speichert **Vorlage speichern** sie unter demselben Namen. Der kleine Pfeil daneben öffnet **Unter neuem Namen speichern**: Die geladene Vorlage bleibt unverändert, die Strecke wird als zweite angelegt (heißt sie noch gleich, bekommt sie den Zusatz „(Kopie)“). Ändern Sie Von oder Nach, schlägt die Software unter dem Namen einen neuen Namen vor, den Sie mit **Als Namen übernehmen** annehmen können. Ein selbst gewählter Name wird nie überschrieben. **Verlassen Sie die Seite** mit einer Eingabe, die noch nicht gespeichert ist, fragt die Software vorher nach.

**Alle Tasten zur Strecke** stehen in einer Leiste am unteren Rand, über die ganze Seite. Links geht es weiter: **Angebot erstellen** (wählbar nach der Berechnung; der Pfeil daneben öffnet **Zur Reise hinzufügen**) und **Strecke berechnen**. Rechts stehen **Als Vorlage speichern** und **Neu beginnen** (leert die Seite, gespeicherte Vorlagen bleiben erhalten). Meldungen erscheinen direkt über den Tasten. Die Orte der Halte werden nie erneut gesucht.

## Das Tour-Archiv als Liste {#tour-archiv-liste}

Alle gespeicherten Touren sehen Sie in der Seitenleiste unter **Fahrten und Abrechnung › Tour-Archiv**. Die Liste zeigt je Tour den Namen, die Strecke (Von → Nach, Zahl der Zwischenhalte, Rückfahrt), Kilometer, Lenkzeit und Tage, Fahrzeuge (Anzahl und Typ), die Fahrtart und in wie vielen [Reisen](/hilfe/belege#reisen) die Tour verwendet wird. Die neueste Änderung steht oben.

- **Suchen:** Das Suchfeld trifft den Namen am Wortanfang und die Orte am Anfang des Ortsnamens, die Treffer sind markiert (zum Beispiel „Wien“ oder „Salz“).
- **Öffnen:** Ein Klick auf den Namen oder das Symbol links öffnet die Tour in der Streckenplanung, mit allen Angaben und dem gespeicherten Ergebnis. Dort ändern Sie sie und speichern mit **Vorlage speichern** oder legen mit **Unter neuem Namen speichern** eine zweite an.
- **Löschen** (Administrator und Disponent): Das Papierkorb-Symbol entfernt die Tour nach einer Rückfrage. Eine Tour, die in einer Reise verwendet wird, trägt stattdessen ein Schloss und lässt sich nicht löschen. Belege, die eine Tour schon enthalten, bleiben unverändert, weil sie ihre Fahrtbeschreibung selbst tragen.
- **Neue Tour:** „Neue Tour planen“ führt in die Streckenplanung. Touren entstehen dort, nicht in der Liste.

## Angebot aus der Tour {#angebot-aus-tour}

Dies ist der schnelle Weg für eine einzelne Fahrt. Für eine Reise mit mehreren Strecken planen Sie besser Strecke für Strecke auf der Reise, siehe [Strecke für eine Reise planen](#strecke-fuer-reise). Ein Angebot **ohne** Strecke legen Sie unter **Belege › Neuer Beleg** an, siehe [Angebot erstellen](/hilfe/fahrten#angebot).

Nach der Berechnung legt **Angebot erstellen** (Tastenleiste unten) aus der berechneten Tour ein Angebot als [Entwurf](/hilfe/belege#entwurf-und-festschreiben) an und öffnet es. Die Positionen schlägt die Software aus den [Preisen](/hilfe/einstellungen#preise) vor: Fahrstrecke, Fahrerzeit, Tagespauschale, Übernachtung und, falls nötig, ein Ausgleich auf den Mindestpreis.

- **Fahrtart:** Vor dem Erstellen wählen Sie die **Fahrtart** (bei der Strecke). Zuschlag und Feiertage stehen im Bereich **Angebot**. Vorbelegt ist Gelegenheitsverkehr (Charter, Ausflug, Gruppenfahrt) mit dem Regelsatz. Bei **Linienverkehr** oder **Schülerfahrt** gilt der ermäßigte Satz aus den [Einstellungen](/hilfe/einstellungen#preise-steuersatz), aber nur bis 50 km. Ist die Fahrt länger, erscheint ein Hinweis (bei Fahrten ins Ausland zählt nur der Inlandsteil). Alle Positionen des Angebots tragen die Fahrtart, auch die Zuschläge. Mehr unter [Fahrtart und Steuersatz](/hilfe/belege#fahrtart).
- **Wochenende und Feiertag:** Hat die Tour einen Starttermin, setzt die Software die [Zuschläge](/hilfe/einstellungen#preise-zuschlaege) für Samstag, Sonntag und Feiertag selbst, je Tag nach den Sätzen in den Einstellungen. **Feiertage in** wählt das Bundesland, nach dem die Feiertage gelten. Vorbelegt ist das Bundesland Ihres Betriebssitzes. Ändern Sie es, wenn die Fahrt in einem anderen Land liegt. Ohne Starttermin gibt es keine automatischen Zuschläge.
- **Zuschlag (%):** Hier tragen Sie von Hand einen weiteren Zuschlag auf die Summe ein, zum Beispiel für Besonderheiten. Er erscheint als eigene Position und kommt zu den automatischen Zuschlägen hinzu.
- **Fahrzeugtyp und Anzahl:** Es gelten die [Preise des gewählten Fahrzeugtyps](/hilfe/einstellungen#preise-fahrzeugtyp), soweit für den Typ welche hinterlegt sind, sonst die Standardpreise. Ohne Typ gelten immer die Standardpreise. Wählen Sie ein bestimmtes Fahrzeug, wird dessen Typ vorgeschlagen. Fahren **mehrere gleiche Busse**, enthält das Angebot jede Position entsprechend oft, mit dem Hinweis „für 2 Fahrzeuge“.
- **Reise und Tour:** Mit dem Angebot entsteht eine [Reise](/hilfe/belege#reise) mit der Tour als Hinfahrt. Die Strecke merkt sich die berechnete Tour selbst. **Angebot erstellen** legt im **Tour-Archiv nichts an**, auch nicht, wenn Sie Fahrzeugtyp oder Anzahl umgestellt haben. Ins Archiv kommt eine Tour nur mit **Als Vorlage speichern**.
- **Kunde:** Den Kunden wählen Sie im geöffneten Angebot.
- **Nur Zahlen:** Zum Server gehen nur Kilometer, Lenkzeit, Zahl der Tage, Start- und Endzeit, das Bundesland, Fahrzeugtyp und Anzahl (und ein gewähltes Fahrzeug, daraus nimmt der Server sonst den Typ), nicht die Route und nicht die Orte. Der Betreff nennt Start und Ziel der Fahrt.
- **Maut** und Besonderheiten fehlen: Fügen Sie sie im Angebot als eigene Position hinzu.
- Alle Positionen lassen sich im Entwurf ändern. Das Ergebnis ist ein Vorschlag, kein verbindlicher Preis.
- Wurde die Eingabe nach der Berechnung geändert, fragt **Angebot erstellen**, ob die Strecke zuerst neu berechnet werden soll (der Preis käme sonst von der alten Strecke). Antworten Sie mit „Neu berechnen und Angebot erstellen“, rechnet die Software neu und legt das Angebot an. Mit „Abbrechen“ passiert nichts. Fahrtart, Zuschlag und Feiertage lassen sich auch nach einer Änderung weiter einstellen.

## Strecke für eine Reise planen {#strecke-fuer-reise}

Hat eine Reise mehrere Strecken (Hinfahrt, Fahrten vor Ort, Rückfahrt), planen Sie sie **von der Reise aus**: **Strecke hinzufügen** öffnet die Streckenplanung für eine neue Strecke, der **Stift** eine vorhandene.

- Oben wählen Sie die **Art der Strecke**. Vorgeschlagen ist die passende nächste. Bei der **Fahrt vor Ort** ist kein Betriebshof vorgewählt. Bei der **Rückfahrt** sind Von und Nach mit den vertauschten Orten der Hinfahrt belegt, die Fahrzeuge kommen von der ersten Strecke.
- Darunter geben Sie die Route ein oder holen sie mit **Vorlage laden** aus dem Tour-Archiv. Bei **Angaben zur Strecke** stehen Bezeichnung (wird aus „Von – Nach“ vorgeschlagen), Datum, Abfahrtszeit, Zuschlag und Fahrzeuge, bei **Werte für den Preis** Kilometer, Fahrzeit und Tage.
- **Speichern** legt die Strecke auf der Reise an und führt dorthin zurück. Ist sie noch nicht berechnet, fragt die Software, ob sie das jetzt tun soll. **Abbrechen** verwirft die Eingabe. Ins Tour-Archiv kommt nur etwas mit **Als Vorlage speichern**.
- **Aus der freien Planung:** Der Pfeil neben **Angebot erstellen** öffnet **Zur Reise hinzufügen**. Dort wählen Sie eine bestehende Reise oder **Neue Reise anlegen** und die Art der Strecke.

Siehe [Mehrere Strecken und Fahrzeugtypen](/hilfe/belege#mehrere-strecken).

## Wenn etwas nicht klappt {#fehler}

- **Die Suche findet den falschen Ort oder nichts:** Die Ortssuche arbeitet mit den Namen aus OpenStreetMap. Bahnhöfe finden Sie meist mit der Abkürzung („Ulm Hbf“, „München Hbf“), nicht mit „Hauptbahnhof“. Ein Hotel oder eine Straße suchen Sie mit Ort („Hotel Bayerischer Hof München“, „Münsterplatz, Ulm“). Mit Postleitzahl und vollem Ortsnamen trifft die Suche am sichersten („88400 Biberach an der Riß“). Steht unter dem Feld der falsche Ort, ändern Sie den Text und berechnen neu.
- **„Zwischen den Orten wurde keine Route gefunden“:** Mit den Maßen des gewählten Fahrzeugs ist ein Ort nicht erreichbar, zum Beispiel hinter einer niedrigen Unterführung (so geschehen am Bahnhofsvorplatz München mit 3,65 m Höhe). Wählen Sie einen Ort in der Nähe, etwa einen Busparkplatz, oder ein anderes Fahrzeug.
- **„Für einen der Orte wurde keine befahrbare Straße in der Nähe gefunden“:** Wählen Sie den Ort genauer (zum Beispiel eine Adresse statt eines Gebiets).
- **„Die Streckenplanung ist noch nicht eingerichtet“:** Beim Betreiber fehlt der Zugang zum Routing-Dienst. Bitte den Betreiber informieren.
- **„Das Tageskontingent des Routing-Dienstes ist aufgebraucht“:** Der Dienst hat Obergrenzen je Tag. Sie werden nach Mitternacht (UTC, also um 1 Uhr bzw. im Sommer um 2 Uhr deutscher Zeit) zurückgesetzt. Bis dahin funktionieren Orte, die schon einmal gesucht wurden, weiter.
- **„Der Routing-Dienst ist gerade ausgelastet“** oder **„Zu viele Anfragen“:** Eine Minute warten und noch einmal berechnen.

> Die **Fahrzeit** stammt aus dem Lkw-Profil des Routing-Dienstes und kann von der Fahrt mit einem Reisebus abweichen. Vergleichen Sie die Werte mit echten Fahrten.
