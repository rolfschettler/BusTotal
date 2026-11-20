# Streckenplanung {#strecken}

Hier berechnen Sie eine Tour: **Strecke**, **Fahrzeit** und den **zeitlichen Ablauf mit den gesetzlichen Pausen und Ruhezeiten**. Den Bereich sehen Administrator und Disponent. Die Berechnung ist eine **Planungshilfe**: Es wird nichts gespeichert, und sie ersetzt keine Auswertung des Fahrtenschreibers.

## Eine Tour berechnen {#streckenplanung}

1. **Von:** Schreiben Sie den Startort, zum Beispiel „88400 Biberach“ oder „Ulm Hbf“. Ihr Text bleibt so stehen, wie Sie ihn eingegeben haben. Gesucht wird erst beim Berechnen. Danach steht unter dem Feld, welcher Ort verwendet wurde („Verwendet: …“). Passt er nicht, schreiben Sie den Ort genauer und berechnen neu.
2. **Nach:** Genauso das Ziel.
3. **Über** (freiwillig): Mit **Zwischenhalt (über)** kommt ein weiteres Ortsfeld dazu, so oft Sie wollen. Mit den Pfeilen ändern Sie die Reihenfolge, mit dem Kreuz entfernen Sie einen Halt. **Tauschen** vertauscht Von und Nach. Mit **Rückfahrt zum Start (Von)** fährt die Tour am Ende zum Start zurück.
4. **Fahrzeug wählen** (freiwillig): Bei der Route werden Höhe, Breite, Länge und Gewicht des Fahrzeugs beachtet (zum Beispiel niedrige Brücken). Fehlen beim Fahrzeug Maße oder Gewicht, weist die Seite darauf hin; die Route wird dann ohne diese Grenzen berechnet. Mit Fahrzeug erscheinen außerdem die **Fahrzeugkosten** (Kilometer mal Kostensatz des Fahrzeugs).
5. **Abfahrt** (freiwillig): Mit Datum und Uhrzeit zeigt der Zeitplan echte Zeitpunkte, ohne Abfahrt den Versatz seit Beginn („+4:30“).
6. **Strecke berechnen** (oder Enter in einem Ortsfeld). Dafür genügen **Von** und **Nach**.

Ein Ort, der schon einmal gesucht wurde, wird nicht erneut gesucht: Der Browser merkt sich die Orte zu Ihren Eingaben. Das schont das Tageskontingent des Routing-Dienstes. Groß- und Kleinschreibung, doppelte Leerzeichen und ein Komma oder Punkt am Ende spielen dabei keine Rolle.

Die Karte zeigt die Orte (A = Von, Zahlen = Über, Z = Nach) und die berechnete Strecke. Ändern Sie danach etwas an der Eingabe, wird das Ergebnis blass und mit einem Hinweis als **veraltet** gekennzeichnet, bis Sie neu berechnen.

Betriebshof, Aufenthalte und lange Tage stehen unter **Weitere Angaben (freiwillig)** und sind zunächst eingeklappt.

## Betriebshof und Leerfahrten {#betriebshof}

Unter **Weitere Angaben** tragen Sie den **Betriebshof** ein (Ortsfeld wie bei Von und Nach). Dann rechnet die Seite die Fahrt vom Betriebshof zum Start und (mit Häkchen **Rückfahrt zum Betriebshof**) vom letzten Ort zurück mit. Diese Strecken sind **Leerfahrten**, also ohne Fahrgäste. Sie werden getrennt ausgewiesen („davon Leerfahrt“), weil sie in der Kalkulation anders behandelt werden. Auf der Karte trägt der Betriebshof ein „H“.

Der Betriebshof wird **nur in diesem Browser** gemerkt. Eine feste Adresse für die ganze Firma gibt es noch nicht.

## Aufenthalt und „Fahrer frei“ {#aufenthalt}

Unter **Weitere Angaben** tragen Sie bei jedem gewählten Ort den **Aufenthalt in Minuten** ein. Beim Start ist das die Arbeit vor der Abfahrt, zum Beispiel das Einsteigen der Gäste.

- Normalerweise zählt der Aufenthalt als **Arbeitszeit** des Fahrers.
- Mit **Fahrer frei** hat der Fahrer in dieser Zeit frei (zum Beispiel, während die Gäste eine Stadtführung haben). Dann zählt ein Aufenthalt **ab 45 Minuten als Lenkpause** und ein Aufenthalt **ab 11 Stunden** (Übernachtung) **als Tagesruhezeit**.

## Pausen und Ruhezeiten {#lenkzeiten}

Die Planung rechnet nach der Verordnung (EG) Nr. 561/2006 mit der einfachen, sicheren Auslegung:

- Nach spätestens **4,5 Stunden Lenkzeit** folgt eine **Pause von 45 Minuten** am Stück. Die erlaubte Teilung in 15 und 30 Minuten wird nicht ausgenutzt; das ist die Reserve für Verzögerungen.
- Pro Tag sind höchstens **9 Stunden** Lenkzeit erlaubt. Mit **Lange Tage** erlauben Sie an den ersten ein oder zwei Tagen 10 Stunden (je Woche höchstens zweimal).
- Danach folgt die **Tagesruhezeit von 11 Stunden**. Ein Arbeitstag mit Lenken, Pausen und Halten dauert höchstens 13 Stunden.

Der **Zeitplan** zeigt jeden Abschnitt: Fahrt, Pause, Halt und Ruhezeit, gegliedert nach Tagen. Die Kennzahl „Fahrtage“ zählt nur Tage, an denen gefahren wird.

> **Nicht geprüft** werden die Wochenlenkzeit (56 Stunden), die Doppelwoche, die Wochenruhezeit, die verkürzte Tagesruhezeit und mehrere Fahrer. Dauert eine Tour länger als 6 Tage oder lenkt der Fahrer mehr als 56 Stunden, erscheint ein Hinweis. Ob eine Tour wirklich zulässig ist, klären Sie fachlich ab (Entwurf zur Prüfung durch den Auftraggeber).

## Wenn etwas nicht klappt {#fehler}

- **Die Suche findet den falschen Ort oder nichts:** Die Ortssuche arbeitet mit den Namen aus OpenStreetMap. Bahnhöfe finden Sie meist mit der Abkürzung („Ulm Hbf“, „München Hbf“), nicht mit „Hauptbahnhof“. Ein Hotel oder eine Straße suchen Sie mit Ort („Hotel Bayerischer Hof München“, „Münsterplatz, Ulm“). Mit Postleitzahl und vollem Ortsnamen trifft die Suche am sichersten („88400 Biberach an der Riß“). Steht unter dem Feld der falsche Ort, ändern Sie den Text und berechnen neu.
- **„Zwischen den Orten wurde keine Route gefunden“:** Mit den Maßen des gewählten Fahrzeugs ist ein Ort nicht erreichbar, zum Beispiel hinter einer niedrigen Unterführung (so geschehen am Bahnhofsvorplatz München mit 3,65 m Höhe). Wählen Sie einen Ort in der Nähe, etwa einen Busparkplatz, oder ein anderes Fahrzeug.
- **„Für einen der Orte wurde keine befahrbare Straße in der Nähe gefunden“:** Wählen Sie den Ort genauer (zum Beispiel eine Adresse statt eines Gebiets).
- **„Die Streckenplanung ist noch nicht eingerichtet“:** Beim Betreiber fehlt der Zugang zum Routing-Dienst. Bitte den Betreiber informieren.
- **„Das Tageskontingent des Routing-Dienstes ist aufgebraucht“:** Der Dienst hat Obergrenzen je Tag. Sie werden nach Mitternacht (UTC, also um 1 Uhr bzw. im Sommer um 2 Uhr deutscher Zeit) zurückgesetzt. Bis dahin funktionieren Orte, die schon einmal gesucht wurden, weiter.
- **„Der Routing-Dienst ist gerade ausgelastet“** oder **„Zu viele Anfragen“:** Eine Minute warten und noch einmal berechnen.

> Die **Fahrzeit** stammt aus dem Lkw-Profil des Routing-Dienstes und kann von der Fahrt mit einem Reisebus abweichen. Vergleichen Sie die Werte mit echten Fahrten.
