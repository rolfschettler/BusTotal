# Demo-Daten für Tests, Schulungen und Messen

Mit einem Befehl entstehen realistische, **erfundene** Kunden. Sie werden über denselben Weg angelegt wie echte
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
