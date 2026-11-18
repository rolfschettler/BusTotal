# Demo-Daten für Tests, Schulungen und Messen

Mit einem Befehl entstehen realistische, **erfundene** Kunden (und ein Demo-Fuhrpark, siehe unten). Sie werden über denselben Weg angelegt wie echte
Kunden (richtige Kunden- und Debitorennummern) und sind als **Demo** markiert, damit man sie später gezielt entfernen kann.

## Befehle

Im Ordner `backend` ausführen:

```bash
php artisan bustotal:demo-daten
```
legt **200** Demo-Kunden an.

```bash
php artisan bustotal:demo-daten --anzahl=500
```
legt insgesamt 500 an. Die ersten 200 bleiben unverändert, es kommen nur 300 dazu.

```bash
php artisan bustotal:demo-daten --entfernen
```
entfernt alle Demo-Kunden samt Ansprechpartnern und Adressen. Echte Kunden werden **nie** angefasst.

## Was angelegt wird

| Art | Anteil | Beispiele |
|---|---|---|
| Privatperson | 30 % | Sebastian Albrecht, Potsdam |
| Verein | 24 % | TSV Passau 1893 e.V., Freiwillige Feuerwehr Mainz |
| Firma | 22 % | Albrecht Elektro GmbH, Autohaus Koch Erfurt |
| Schule | 12 % | Albert-Schweitzer-Gymnasium Stuttgart |
| Reisebüro | 12 % | Reisewelt Hamburg |

- Ort, Postleitzahl und Vorwahl passen zusammen (über 40 Städte in ganz Deutschland).
- Organisationen haben 1 bis 3 **Ansprechpartner** mit passender Funktion (Schulleitung, Kassenwart, Geschäftsführung …),
  genau einen davon als Hauptansprechpartner. Schulen und Vereine haben oft **Abholadressen**.
- Etwa 3 % der Kunden sind **archiviert** (zum Vorführen des Archivs), manche haben ein eigenes Zahlungsziel,
  USt-IdNr. oder eine Notiz.
- Die Kunden sind über die letzten zwei Jahre verteilt: Ältere haben kleinere Nummern, die Startseite zeigt
  unterschiedliche „zuletzt bearbeitete“ Kunden.
- Alle Namen sind eindeutig, es erscheint also keine Dublettenwarnung.
- Alle E-Mail-Adressen enden auf `.example` (eine reservierte Endung, die es im Internet nie gibt). Es kann
  also nie versehentlich eine echte Person angeschrieben werden. Die Telefonnummern sind Zufallsziffern.

## Wiederholbar

- **Gleiche Daten bei jedem Lauf:** Kunde Nr. 17 sieht immer gleich aus. Für Schulungen und Messen ist so alles
  vorhersehbar (dieselben Beispielkunden zum Zeigen).
- **Ein zweiter Lauf legt nichts doppelt an** und verbraucht keine Nummern.

## Kundennummern zurücksetzen

Nach `--entfernen` beginnen Kunden- und Debitorennummern **wieder am Anfang** (`K-00001`, 10000), aber nur, wenn
danach **kein einziger Kunde** mehr vorhanden ist. Gibt es noch echte Kunden, bleiben die Zähler unverändert
(Nummern dürfen nie zurückgesetzt werden, wenn echte Belege daran hängen könnten).

Für eine Schulung, die jedes Mal bei `K-00001` beginnen soll: `--entfernen`, dann wieder anlegen.

## Sicherheit

- In einer **produktiven Umgebung** (`APP_ENV=production`) verweigert der Befehl die Arbeit, weil Demo-Kunden echte
  Nummern verbrauchen. Für eine **eigene Demo-Firma** (z. B. für Messen) nutzt man bewusst `--force`:
  `php artisan bustotal:demo-daten --force`.
- Im Änderungsprotokoll erscheint **ein** Eintrag je Lauf („Demo-Daten angelegt“), nicht einer je Kunde.
- In der Oberfläche trägt ein Demo-Kunde neben der Nummer das Kennzeichen **Demo**.
- Im Formular lässt sich die Demo-Markierung nicht setzen. Ein von Hand angelegter Kunde ist nie ein Demo-Kunde.

## Für Entwickler

- Erzeugung der Daten: `app/Support/DemoDataGenerator.php` (Namenslisten, Städte, Regeln).
- Speichern und Entfernen: `app/Services/DemoDataService.php`.
- Der Befehl: `app/Console/Commands/DemoData.php`.
- Die Tests (`tests/Feature/DemoDataTest.php`) prüfen unter anderem, dass **jeder** Demo-Kunde die Eingaberegeln der
  Anwendung besteht, Nummern lückenlos sind und Entfernen nur Demo-Kunden trifft.
- Neue Kundenarten oder Felder: Der Generator muss mitwachsen, sonst schlägt der Test „alle Kunden bestehen die
  Prüfregeln“ an.

## Demo-Fuhrpark (Fahrzeuge, Fahrer, Abwesenheiten)

Ohne weitere Angabe legt `php artisan bustotal:demo-daten` neben den Kunden auch einen **Fuhrpark** an:
**14 Fahrzeuge**, **20 Fahrer** und deren **Abwesenheiten**. Mit `--art` wählt man gezielt:

```bash
php artisan bustotal:demo-daten --art=fuhrpark                      # nur Fahrzeuge und Fahrer
php artisan bustotal:demo-daten --art=kunden                        # nur Kunden
php artisan bustotal:demo-daten --art=fuhrpark --fahrzeuge=30 --fahrer=50
php artisan bustotal:demo-daten --art=fuhrpark --entfernen          # entfernt nur den Demo-Fuhrpark
```

Grenzen: `--fahrzeuge` 0 bis 200, `--fahrer` 0 bis 500. `--entfernen` ohne `--art` entfernt Kunden **und** Fuhrpark.

- **Fahrzeuge:** Reisebusse, ein Doppeldecker, Midi- und Minibusse (Setra, Mercedes-Benz, MAN, Neoplan, Iveco, VW) mit
  passenden Sitzplätzen, Maßen, Gewicht, Ausstattung und Kostensatz je km; Fuhrparknummer `B-01` …, Kennzeichen `AN-…`.
- **Fahrer:** Name, Adresse (Raum Ansbach/Nürnberg), Beschäftigungsart, Stundensatz, Führerscheinklassen, Nummern und
  fünf Fristen. E-Mail-Adressen enden auf `.example`.
- **Fristen und Abwesenheiten sind relativ zu heute** und gezielt gesetzt, damit es immer etwas zum Vorführen gibt:
  je 10 Fahrzeuge eine abgelaufene HU, eine bald fällige SP, eine bald fällige Wartung und ein bald fälliger
  Fahrtenschreiber; je 8 Fahrer eine abgelaufene Fahrerkarte, ein bald fälliges Code 95 und eine bald fällige
  ärztliche Untersuchung. Fahrer Nr. 3, 11, 19 haben **heute Urlaub**, Fahrer Nr. 5, 13 sind **heute krank**.
  Alle übrigen Fristen liegen weit in der Zukunft (die Kacheln „Fällige Fristen“ zeigen also nur die gewollten).
- Jedes 12. Fahrzeug und jeder 15. Fahrer ist **archiviert** (verkauft bzw. ausgetreten).
- Wiederholbar wie bei den Kunden (feste UUIDs, fester Zufall je Nummer): zweiter Lauf legt nichts doppelt an,
  eine größere Menge behält die ersten Datensätze. Nur die Datumswerte wandern mit dem heutigen Tag mit.
- **Schutz:** Alles ist mit `is_demo` markiert, Entfernen trifft nie echte Fahrzeuge oder Fahrer. Ist eine
  Fuhrpark- oder Personalnummer oder ein Kennzeichen schon an einen **echten** Datensatz vergeben, wird der
  Demo-Datensatz übersprungen (Hinweis in der Ausgabe). Ein Protokolleintrag je Lauf (`demo_fleet_created` bzw. `demo_fleet_removed`).
- Für Entwickler: `app/Support/DemoFleetGenerator.php` (Daten), `app/Services/DemoFleetService.php` (Speichern/Entfernen),
  Tests in `tests/Feature/DemoFleetTest.php`. Neue Felder an Fahrzeug oder Fahrer: den Generator ergänzen, sonst schlägt der
  Test „alle bestehen die Prüfregeln“ an.
- In der Firmen-Datenbank muss vorher die neue Spalte da sein: `php artisan tenant:migrate`.
