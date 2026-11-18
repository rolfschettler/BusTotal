> Stand: 2026-11-21 – Phasen 1 bis 4 erledigt (Fahrzeuge und Fahrer), nächste Phase: 5 Streckenplanung. Planung vom 2026-11-16. Erledigte Schritte mit [x] abhaken.

# BusTotal – Grober Entwicklungsfahrplan (Bustouristik)

## Kontext
Neues, leeres Projekt (`D:\angular\BusTotal`). Ziel: mandantenfähige Branchensoftware für Busunternehmen mit
Kunden-, Fuhrpark-, Dispositions-, Beleg- (Angebot → Auftrag → AB → Rechnung, DATEV) und Streckenplanungsmodul (OSM).
Eine Codebase, beliebig viele Mandanten, **je Mandant eigene Datenbank**, Backend PHP, Frontend Angular.
Entwickler hat noch keine Erfahrung mit Laravel und Docker → Claude begleitet Schritt für Schritt mit Erklärungen.

Getroffene Entscheidungen:
- Mandantenzugriff über **Subdomain** (`firma.bustotal.de`) → Mandant wird aus dem Host-Header ermittelt
- Hosting: vorhandener **Mittwald Agentur-Server (altes Kundencenter)** → klassisches PHP/MySQL-Hosting, **kein Docker**
- DATEV: **Buchungsstapel-Export (EXTF-CSV)** + GoBD-konforme Rechnungen
- PHP-Framework: **Laravel (Empfehlung)** – bitte bestätigen

### Folgen aus dem Mittwald-Hosting (wichtig)
| Thema | Konsequenz |
|---|---|
| Datenbank | Produktiv **MySQL**. Code bleibt trotzdem DB-neutral (Eloquent), Postgres bleibt später möglich. |
| DB je Mandant | Datenbanken werden im Kundencenter **manuell angelegt**; Zugangsdaten trägt der Superadmin in BusTotal ein, danach migriert BusTotal die DB automatisch. (Automatisches `CREATE DATABASE` ist auf Shared-Hosting i. d. R. nicht erlaubt.) |
| Routing / OSM | Kein eigener Routing-Server möglich → **externer Routing-Dienst** über eine Schnittstelle im Code (z. B. openrouteservice, GraphHopper; Bus-/LKW-Profil, Kosten/Kontingente prüfen). Austauschbar, damit später eigener Server möglich ist. |
| Hintergrundjobs | Keine dauerhaft laufenden Worker → Laravel-Queue mit Datenbank-Treiber, abgearbeitet per **Cronjob** (`schedule:run` minütlich). |
| Wildcard-Subdomain | **Bei Mittwald klären**: `*.bustotal.de` auf das Laravel-Verzeichnis + SSL-Zertifikat. Fallback: Subdomains je Kunde einzeln anlegen. |
| Deployment | Per SSH/Git + `composer install` + `php artisan migrate` (für alle Mandanten); Angular als statische Dateien hochladen. Bei Mittwald klären: SSH, Composer, PHP-Version ≥ 8.3, Cron. |

---

## [ ] Phase 0 – Voraussetzungen schaffen

### Fachlich
- **Glossar & Prozesse**: Anfrage → Kalkulation → Angebot → Auftrag → AB → Disposition → Durchführung → Rechnung → DATEV
- **Kalkulationsschema**: km-Satz, Stundensatz Fahrer, Pauschalen, Maut, Übernachtung, Zuschläge, MwSt
  (Personenbeförderung 7 %/19 % je nach Strecke bzw. Ausland – Steuerberater einbinden)
- **Rechtliches**: GoBD (Unveränderbarkeit, lückenlose Nummernkreise, Storno statt Löschen), DSGVO, Lenk-/Ruhezeiten (VO 561/2006)
- **E-Rechnung** (XRechnung/ZUGFeRD) ab 2027/2028 Pflicht → Architektur jetzt vorsehen
- **DATEV-Daten** vom Steuerberater: SKR03/04, Erlöskonten, Berater-/Mandantennummer, Debitorenkreis

### Technisch
- **Lokale Entwicklungsumgebung ohne Docker** (passt zum Mittwald-Ziel, weniger Lernaufwand):
  vorhandenes PHP 8.4 + Node 24, dazu Composer, MySQL (lokal), Git, VS Code/PhpStorm, Angular CLI.
  Alternativ Laravel Herd für Windows. Docker kann später für den Routing-Test ergänzt werden.
- Monorepo: `/backend` (Laravel), `/frontend` (Angular), `/docs`
- REST-API mit OpenAPI-Beschreibung → Angular-Client generieren
- Auth: Laravel Sanctum, Rollen je Mandant (Admin, Disponent, Buchhaltung, Fahrer)
- Frontend: Angular, Angular Material oder PrimeNG, Leaflet für Karten
- Kartenkacheln: nicht die openstreetmap.org-Tiles produktiv nutzen → Tile-Anbieter wählen
- PDF: serverseitig mit dompdf (läuft auf Shared-Hosting)
- Git-Hosting (GitHub/GitLab) + CI (Tests bei jedem Push), Backup-Konzept je Mandanten-DB

---

## [x] Phase 1 – Fundament
1. Repo, lokale Umgebung, Laravel- und Angular-Grundgerüst
2. Auth, Benutzer, Rollen/Rechte, Audit-Log (GoBD)
3. Mandanten-Einstellungen (Firmendaten, Briefkopf, Bank, Steuersätze, Nummernkreise)
4. Angular-Shell: Login, Layout, Navigation, Guards, API-Client

## [x] Phase 2 – Kundenverwaltung (gleichzeitig Laravel-Lernmodul)
- Kunden (Firma/Privat/Schule/Verein/Reisebüro), Ansprechpartner, Adressen, Zahlungsbedingungen, Debitorennummer
- Suche, Dublettenprüfung, Notizen, DSGVO-Export/Löschung

## [x] Phase 3 – Mandantenfähigkeit
- Zentrale DB (Mandanten, Domains, DB-Zugangsdaten verschlüsselt) + DB je Mandant
- Middleware: Subdomain → Mandant → DB-Verbindung; Migrationen für alle Mandanten-DBs
- Superadmin-Oberfläche: Mandant anlegen (DB-Zugangsdaten eintragen → migrieren → Admin-User)

## [x] Phase 4 – Fuhrparkverwaltung (Fahrzeuge und Fahrer erledigt)
- [x] Fahrzeuge (Typ, Sitzplätze, Ausstattung, Maße/Gewicht, km-Kostensatz), Fristen (HU, SP, Tacho, Wartung)
- [x] Fahrer/Personal (Führerschein, Fahrerkarte, Code 95, Verfügbarkeit, Urlaub)

## [ ] Phase 5 – Streckenplanung (OSM) (Backend-Grundlage fertig, Oberfläche offen)
- [x] Routing-Schnittstelle + Adapter für externen Dienst (openrouteservice), Geocoding – Details: `docs/STRECKENPLANUNG.md`
- [x] Route mit Zwischenstopps, Leerfahrten Depot↔Start/Ziel, Fahrzeit inkl. Pflichtpausen und Lenkzeitprüfung (Berechnung, `POST /api/routes/calculate`)
- [ ] Oberfläche: Ortssuche, Halteliste, Karte (Leaflet), Ablauf mit Warnungen; Betriebshof als Firmeneinstellung
- [ ] Ergebnis als Kalkulationsbasis speichern

## [ ] Phase 6 – Belegwesen
- Kalkulationsengine; Belegkette Angebot → Auftrag → AB → Rechnung
- GoBD: festgeschriebene Rechnungen, Storno/Gutschrift, lückenlose Nummern, §14 UStG
- PDF, E-Mail-Versand, **DATEV-EXTF-Export** + Debitoren; später E-Rechnung

## [ ] Phase 7 – Disposition
- Plantafel Fahrzeuge × Fahrer, Drag & Drop, Konfliktprüfung (Doppelbelegung, Fristen, Lenkzeiten, Sitzplätze)
- Fahrauftrag als PDF

## [ ] Phase 8 – Betrieb auf Mittwald
- Deployment-Ablauf, Cronjobs, Backups, Monitoring; Sicherheitsprüfung
- Performance im Produktivbetrieb: Checkliste in `PRODUKTIV-PERFORMANCE.md` (OPcache, Caches, Produktions-Build)

## Verifikation
- Automatisierte Tests je Modul (PHPUnit/Pest, Angular-Tests, Playwright für Belegkette)
- Mandantentrennung explizit testen
- DATEV-Export vom Steuerberater testweise importieren lassen
- Routing-Ergebnisse mit realen Fahrten vergleichen
- Früh einen Test-Deploy auf Mittwald (nach Phase 1), um Hosting-Grenzen rechtzeitig zu erkennen

## Offene Klärungen mit Mittwald
- [ ] Wildcard-Subdomain `*.bustotal.de` + SSL möglich?
- [ ] SSH, Composer, PHP ≥ 8.3, Cronjobs verfügbar?
- [ ] Anzahl MySQL-Datenbanken im Tarif (begrenzt die Mandantenzahl)?

## Nächster konkreter Schritt
**Erledigt (2026-11-17):**
- Phase 1.1: Lokale Umgebung, Git-Repo, Laravel (`/backend`, MySQL `bustotal_central`), Angular (`/frontend`).
- Phase 1.2 teilweise: Sanctum-Login (`/api/login`, `/api/logout`, `/api/me`), Rollen (admin/disponent/buchhaltung/fahrer), Tests laufen gegen MySQL `bustotal_test`.

- Phase 1.2 Audit-Log: Tabelle `audit_logs` (nur anfügen, unveränderlich), Trait `Auditable` (am User aktiv, Passwörter ausgeschlossen), Login/Logout/Fehlversuche protokolliert. 12 Tests grün.

- Phase 1.4 teilweise: Angular-Login (`/login`), Token-Interceptor, Auth-Guard, Startseite mit Abmelden; Proxy `/api` → Laravel (`frontend/proxy.conf.json`). Im Browser geprüft.

- Phase 1.3: Mandanten-Einstellungen – Firmendaten (inkl. IBAN-Prüfung), Steuersätze, lückenlose Nummernkreise (`NumberRangeService`, nächste Nummer nur erhöhbar); nur Admin darf ändern (`role:admin`-Middleware). Angular-Seite `/einstellungen` mit Layout/Navigation. 21 Backend-Tests grün, im Browser geprüft.

**Erledigt (2026-11-18):**
- Phase 2 Kundenverwaltung: Kunden (Firma/Privat/Schule/Verein/Reisebüro), Ansprechpartner (ein Hauptansprechpartner), Zusatzadressen, Zahlungsziel, automatische Kunden- und Debitorennummer (ab 10000), Suche/Filter/Seiten, Dublettenprüfung, Notizen, Archivieren statt Löschen, DSGVO-Export und Anonymisierung (nur Admin). Rollen: Fahrer ohne Zugriff. 38 Backend-Tests grün, im Browser geprüft (Anlegen, Dublette, Ansprechpartner, Fahrer-Sperre).
- Offen/Hinweis: Alte Audit-Log-Einträge enthalten weiterhin frühere Feldwerte eines Kunden (Protokoll ist unveränderlich). Mit Steuerberater/Datenschutz klären, ob das für die DSGVO-Löschung ausreicht (Aufbewahrungspflicht).

**Erledigt (2026-11-18, Oberfläche):**
- Layout: feste Kopfleiste, Seitenleiste links, nur der Inhalt scrollt. Umstellung auf Bootstrap 5.3 + ng-bootstrap (Login, Rahmen, Kunden, Einstellungen), eigene Bestätigungs-Dialoge statt Browser-`confirm`.
- Branding je Firma: Markenfarbe (mit Kontrastprüfung gegen weiße Schrift) und Logo (PNG/JPG/WebP, max. 1 MB, privat gespeichert), öffentliche Schnittstelle `/api/branding` für die Login-Seite. 50 Backend-Tests grün, im Browser geprüft.
- Entscheidungen: PC-first, nüchtern; Mobil nur auf Zuruf; Hilfesystem mit Sprungmarken wird separat besprochen (noch nicht gebaut).
- Technik-Hinweis: Das Logo liegt auf dem privaten Speicher und wird über die API ausgeliefert (kein `storage:link` nötig, passt zu Mittwald). Bei der Mandantenfähigkeit (Phase 3) muss `/api/branding` den Mandanten aus der Subdomain bestimmen.

**Erledigt (2026-11-18, Hilfesystem):**
- Hilfetexte als Markdown-Dateien in `frontend/public/help/` (`index.json` listet die Themen). Überschriften tragen feste Sprungmarken: `## Titel {#anker}`.
- Aufruf: Fragezeichen in der Kopfleiste oder F1 öffnet ein Seitenfenster mit dem Thema der aktuellen Seite (Seite bleibt bedienbar, Fenster folgt Seitenwechsel und Reitern); kleine Fragezeichen bei schwierigen Feldern (`<app-help-link>`); Hilfeseite `/hilfe/<thema>#<anker>` mit Themenliste und Suche.
- Zuordnung Seite → Hilfethema: `data: { help: 'thema#anker' }` in `app.routes.ts`; Seiten mit Reitern rufen `HelpService.setAnchor()` auf.
- 5 Themen geschrieben (Erste Schritte, Anmeldung und Rollen, Kundenverwaltung, Einstellungen, Datenschutz); Texte sind ein Entwurf zur fachlichen Prüfung durch den Auftraggeber.
- **Regel für alle künftigen Seiten:** Jede neue Seite bekommt ein Hilfethema (oder einen Abschnitt) und einen `data.help`-Eintrag; Hilfetexte nur zu Funktionen schreiben, die es wirklich gibt.

**Entscheidungen aus der Textprüfung (2026-11-18):**
- Fahrer sehen die Einstellungen nicht (Menü ausgeblendet, Route gesperrt, Server liefert 403). Name, Farbe und Logo bleiben über `/api/branding` für alle sichtbar.
- Kunden anonymisieren dürfen Administrator **und Buchhaltung**; Wiederherstellen bleibt beim Administrator. 54 Backend-Tests grün, im Browser mit allen Rollen geprüft.
- **Offen für Phase 0 (Steuerberater):** Debitorenkreis klären. Aktuell vergibt die Software Debitorennummern ab 10000 aufwärts. Einschränkung: Im Nummernkreis kann man die nächste Nummer nur erhöhen, nie senken. Verlangt der Steuerberater einen Bereich unter 10000 (oder Debitornummer = Kundennummer), muss das vor dem ersten echten Kunden angepasst werden, sonst ist der Startwert nicht mehr nach unten korrigierbar.

**Erledigt (2026-11-18, Startseite):**
- Startseite als Kachel-Raster, feste Anordnung je Rolle (keine Benutzeranpassung). Kachel-Verzeichnis: `frontend/src/app/pages/dashboard/tiles/tile-registry.ts`; jede Kachel lädt ihre Daten über eine eigene Schnittstelle `/api/dashboard/<kachel>` mit eigener Rollenprüfung.
- Heute: Schnellzugriff (Suche → Kundenliste mit `?q=`, Neuer Kunde), Einrichtungs-Checkliste (nur Admin, erkennt Erledigtes selbst, verschwindet bei 5/5), zuletzt bearbeitete Kunden, Platzhalter „Ihre Einsätze“ für Fahrer. 66 Backend-Tests grün, im Browser mit allen Rollen geprüft.
- **Regel für künftige Module:** Jedes Modul trägt seine Kacheln im Kachel-Verzeichnis ein (z. B. „Anstehende Fahrten“ für Disponenten, „Fällige Fristen“ für den Fuhrpark) und ergänzt den Hilfetext „Die Startseite“ in `help/start.md`.

**Erledigt (2026-11-19, Benutzerverwaltung – Lücke aus Phase 1.2 geschlossen):**
- Vorher gab es keine Oberfläche für Benutzer; die vier Testbenutzer kamen nur aus dem Seeder.
- Seite `/benutzer` (nur Admin): Liste mit Rolle, Status, letzter Anmeldung; anlegen, bearbeiten, sperren/entsperren, Passwort zurücksetzen. **Kein Löschen** (Protokoll verweist auf Benutzer, GoBD).
- Passwort-Konzept (Nutzer entschieden): Der Admin vergibt kein Passwort, das System erzeugt ein **vorläufiges** (12 Zeichen), das **einmalig** angezeigt und nirgends gespeichert wird. Der Benutzer **muss** es beim ersten Login ändern (`must_change_password`); bis dahin ist serverseitig nur `/me`, `/me/password` und `/logout` erlaubt (Middleware `account.ok`).
- Eigenes Passwort: `/konto` (Benutzermenü → Passwort ändern), mind. 10 Zeichen mit Buchstabe und Ziffer, beendet alle anderen Anmeldungen. Nach Sperren und Zurücksetzen sind alle Anmeldungen des Benutzers sofort ungültig.
- Schutz: Admin kann sich nicht selbst sperren oder herabstufen oder sein Passwort zurücksetzen; letzter aktiver Admin bleibt erhalten (mit Zeilensperre gegen gleichzeitige Anfragen).
- Sicherheit: Der Seeder legt Testbenutzer mit bekanntem Passwort **nur noch lokal** an. Auf einem echten Server legt man den ersten Admin mit `php artisan bustotal:admin-anlegen <email> [name]` an (druckt vorläufiges Passwort einmalig).
- Das Modell `User` hat jetzt dieselben Standardwerte wie die Datenbank (`active`, `role`, `must_change_password`); Fehler dabei hatte anfangs 59 Tests gebrochen.
- Hilfe: neues Thema „Benutzer und Passwörter“. 88 Backend-Tests grün, im Browser durchgespielt (anlegen, doppelte E-Mail, zurücksetzen, Zwangswechsel, falsche/schwache Passwörter, sperren, Selbstsperre).
- **Offen:** Kein E-Mail-Versand (deshalb vorläufiges Passwort statt Einladung); kein „Passwort vergessen“ für nicht angemeldete Benutzer (der Admin setzt zurück).

**Erledigt (2026-11-19, Meldungen eingedeutscht):**
- Deutsche Sprachdateien in `backend/lang/de/` (Validierung mit allen Regeln, Feldnamen passend zur Oberfläche, Passwortregeln, Auth, Pagination, Passwörter) plus `lang/de.json`; Standardsprache ist `de` (auch in `config/app.php` und `.env.example`).
- Deutsche API-Fehler für nicht angemeldet (401), nicht gefunden (404), falsche Methode (405), zu viele Anfragen (429) und Serverfehler ohne Debug-Modus (`bootstrap/app.php`). Nebeneffekt: Ein nicht gefundener Eintrag verrät nicht mehr den internen Klassennamen.
- Ein Test (`LocalizationTest`) vergleicht die deutschen Dateien mit Laravels Vorlage und schlägt bei neuen, unübersetzten Schlüsseln nach einem Laravel-Update an. 104 Backend-Tests grün.
- **Regel für neue Felder:** Neue Eingabefelder bekommen einen Namen unter `attributes` in `lang/de/validation.php`, sonst erscheint der interne Feldname in der Meldung.

**Erledigt (2026-11-19, Anmeldung komfortabler):** Login merkt sich die E-Mail-Adresse der letzten erfolgreichen Anmeldung im Browser (`localStorage`, nur die Adresse, nie das Passwort); der Cursor springt dann ins Passwortfeld; „Anderer Benutzer“ vergisst die Adresse (für gemeinsam genutzte PCs). Die Felder tragen die Attribute für Passwort-Manager (`username`, `current-password`, `new-password`); die Seite „Mein Konto“ hat dafür ein unsichtbares Benutzernamen-Feld.

**Erledigt (2026-11-19, UUID als Primärschlüssel):**
- Entscheidung des Nutzers: Alle fachlichen Tabellen (Benutzer, Kunden, Ansprechpartner, Adressen, Firmeneinstellungen, Steuersätze, Nummernkreise) haben eine **UUID Version 7** als ID (Trait `HasUuids`, Spalte `uuid('id')->primary()`), zeitlich geordnet, gut für den Datenbank-Index. Gründe: Datenübernahmen aus Fremdsystemen, Zusammenführen und Verschieben von Mandanten, nicht erratbare Adressen.
- Ausnahmen: `audit_logs` behält eine fortlaufende Zahl als eigene ID (wird nur angefügt, nie importiert) und verweist per UUID. Technische Laravel-Tabellen (Cache, Jobs, Token-ID) bleiben unverändert. Geschäftsnummern (Kunde, Debitor, Rechnung) bleiben getrennte, lückenlose Zähler.
- Datenübernahme: `App\Support\ImportId::for($quellsystem, $art, $quellnummer)` leitet eine **feste UUID (Version 5)** ab, damit Importe wiederholbar sind und keine Dubletten erzeugen. Der Namensraum darf sich nie ändern (ein Test hält den Wert fest). **Falle:** `id` und Nummern sind gegen Zuweisung gesperrt; Importe müssen `Model::unguarded(fn () => …)` nutzen, sonst wird die feste ID still verworfen (auch das ist getestet). Fremdschlüssel bleiben aktiv: Kunden müssen vor ihren Ansprechpartnern gespeichert werden, aber ohne Zuordnungstabelle.
- **Regel für neue Tabellen:** `uuid('id')->primary()`, `foreignUuid(...)` für Verweise, Trait `HasUuids` am Modell; im Frontend sind IDs vom Typ `string`.
- Die Migrationen wurden direkt geändert (es gab noch keine echte Datenbank); die Entwicklungsdatenbank wurde mit `migrate:fresh --seed` neu aufgebaut. 116 Backend-Tests grün, im Browser geprüft.

**Erledigt (2026-11-19, Demo-Daten):**
- Befehl `php artisan bustotal:demo-daten [--anzahl=200] [--entfernen] [--force]` für eigene Tests, Schulungen und Messen; Anleitung in `docs/DEMO-DATEN.md`.
- Realistische, erfundene Kunden (Privat, Verein, Firma, Schule, Reisebüro) mit passenden Orten/PLZ/Vorwahlen, Ansprechpartnern, Abholadressen, einigen archivierten; E-Mail-Adressen enden auf `.example`.
- Wiederholbar (feste UUIDs per `ImportId`, Zufall mit festem Startwert je Kundennummer): gleiche Daten bei jedem Lauf, zweiter Lauf legt nichts doppelt an, größere Anzahl behält die ersten.
- Markierung `customers.is_demo` (im Formular nicht setzbar): `--entfernen` trifft nur Demo-Kunden; sind danach keine Kunden mehr da, beginnen Kunden-/Debitorennummern wieder bei K-00001/10000. Schutz in `production` nur mit `--force`. Ein Protokolleintrag je Lauf. Oberfläche zeigt das Kennzeichen „Demo“ am Kunden.
- Gemeinsamer Anlegeweg `CustomerService` (Anwendung und Demo-Daten). 142 Backend-Tests grün; 200 Demokunden in der Entwicklungsdatenbank geladen und im Browser geprüft.
- **Hinweis:** Jede Kundenanlage schreibt auch zwei Protokollzeilen für die Nummernzähler (Technik-Rauschen im Änderungsprotokoll); bei Gelegenheit prüfen, ob das gewollt ist.
- **Regel:** Neue Kundenfelder oder -arten müssen im Generator ergänzt werden (ein Test prüft, dass alle Demo-Kunden die Eingaberegeln bestehen).

**Erledigt (2026-11-19, Suchtreffer hervorheben):**
- **Regel des Nutzers für ALLE Suchen:** Treffer in Trefferlisten sind immer hervorgehoben. Baustein `<app-highlight [text] [query] [mode]>` (`frontend/src/app/shared/`), Logik in `highlight.ts` (Groß-/Kleinschreibung und Umlaute egal, wie die Datenbanksuche; `phrase` für Kundensuche, `words` für die Hilfe). Der Text wird nie als HTML eingefügt (sicher gegen Schadcode).
- Eingebaut in Kundenliste (Nummer, Name, PLZ, Ort, E-Mail) und Hilfe-Suche (Titel, Thema, Auszug). Bei Treffern in Feldern, die die Liste nicht zeigt (Debitorennr., Vor-/Nachname bei Firmen), erscheint der Hinweis „Treffer in …“. Die Markierung gehört immer zur angezeigten Liste; veraltete Antworten schneller Eingaben werden verworfen. Hilfe-Auszüge zeigen jedes Suchwort.
- Kräftigeres Gelb für Beamer/Messe (`--bs-highlight-bg` in `styles.scss`). 18 Testfälle der Markierungslogik (`highlight.spec.ts`, lauffähig mit `ng test`; hier mit einem Hilfsskript geprüft, weil für Karma kein Chrome eingerichtet ist).
- **Regel für neue Suchen:** Baustein verwenden; neue durchsuchte Backend-Felder in der Liste anzeigen oder in den Hinweis „Treffer in …“ aufnehmen.

**Erledigt (2026-11-19, Tabellen-Muster aus DATACUR26 übernommen):**
- Quelle: `D:\angular\DATACUR26\ClaudeCodePatterns\TableViewTemplate.(html|scss)` + `src\styles\_list-page.scss`; für BusTotal angepasst und in `docs/ClaudeCodePatterns/TableViewPattern.md` dokumentiert (inkl. Tabelle der Unterschiede). Globale Optik: `frontend/src/styles/_list-page.scss`. Lebende Referenz: Kundenliste.
- Kundenliste neu: Seitenkopf mit Zähler, klebende Filterleiste, klebender getönter Tabellenkopf, **serverseitige Sortierung** je Spalte (feste Spaltenliste, leere Werte am Ende, Kundennummer bei Gleichstand, SQL-sicher), Aktionsspalte (Öffnen, Archivieren, Wiederherstellen), Auswahlzeile, Dimmen statt Spinner. 155 Backend-Tests grün; im Browser geprüft (Kleben, Sortieren, Blättern, Archiv-Dialog, schmales Fenster, Firmenfarbe).
- Tabellentöne folgen der Firmenfarbe zur Laufzeit (`BrandService`: `--brand-subtle` usw.). Bootstrap Icons eingebunden. Rahmen: Route-Daten `fullPage: true` für Listenseiten.
- Regel aus dem Muster: **keine weichen Schatten** im Listenlayout (scharfe 1–2-px-Linien).
- **Benutzerliste ebenfalls nach dem Muster** (clientseitige Variante): Suche in Name/E-Mail/Rolle mit Markierung, Filter Rolle und Status, Sortierung (leere Werte immer am Ende), Aktionssymbole (Stift, Schlüssel, Schloss) statt Menü, Formular und vorläufiges Passwort oberhalb der Liste. Logik in `users-view.ts` mit 14 Testfällen (zusammen mit der Markierung 32 Fälle, lauffähig mit `ng test` bzw. hier per Hilfsskript). Im Browser durchgespielt (Anlegen, doppelte E-Mail, Zurücksetzen, Sperren, Entsperren, Bearbeiten, schmales Fenster).
- Offen: `EditFormTemplate` aus DATACUR26 als nächstes Muster (Bearbeitungsformulare) prüfen.

**Erledigt (2026-11-20, Phase 3 Mandantenfähigkeit – Backend):**
- Zentrale Datenbank mit Tabelle `tenants` (Passwort verschlüsselt); die Verbindung zur Firmen-Datenbank wird je Anfrage aus dem Host (Unterdomäne) eingestellt (`ResolveTenant`, `TenantManager`). Details: `docs/MANDANTEN.md`.
- Befehle `tenant:create`, `tenant:list`, `tenant:migrate`; `bustotal:admin-anlegen` und `bustotal:demo-daten` mit `--tenant=`. Dateien (Logo) je Firma getrennt.
- 11 Tests zur Mandantentrennung (Daten, Token, Anmeldung, Sperre, Passwortverschlüsselung, unsichere Angaben); gesamt 166 grün. Dev-Mandant `dev` mit eigener Datenbank `bustotal_dev` angelegt (die alten Daten in `bustotal_central` werden nicht mehr benutzt).
- **Offen:** Queue/Cron je Firma (Phase 8); Mittwald-Klärungen.

**Erledigt (2026-11-20, Phase 3 – Superadmin-Oberfläche):**
- Betreiber-Oberfläche unter `admin.<basis>` (lokal `http://admin.localhost:4200`): Anmeldung (eigene Konten, eigener Guard und Token, Pflicht-Passwortwechsel, mind. 12 Zeichen), Firmenliste nach Tabellen-Muster (Suche mit Markierung, Status-Filter, Sortierung), Firma anlegen (Zugang prüfen, Datenbank aufbauen, erster Administrator mit einmalig angezeigtem Passwort), bearbeiten, sperren/entsperren, Datenbank aktualisieren, Administrator nachlegen. Keine Löschfunktion. Eigenes unveränderliches Protokoll. Details: `docs/MANDANTEN.md`.
- Gemeinsamer Dienst `TenantProvisioner` für Befehl und Oberfläche; Befehl `superadmin:anlegen`. 182 Backend-Tests grün (16 neu), Listenlogik mit 7 Testfällen (per Hilfsskript geprüft), `ng build` läuft.
- **Im Browser durchgespielt** (2026-11-20): Anmelden, Firma anlegen (leere Datenbank, eigener DB-Benutzer), Anmeldung in der neuen Firma mit vorläufigem Passwort und Zwangswechsel, sperren, entsperren, Datenbank aktualisieren. Testdaten wieder entfernt. Hilfetext `betreiber.md` ist ein Entwurf.
- **Fehler dabei gefunden und behoben:** War jemand bei einer gesperrten Firma noch angemeldet, blieb die Seite leer (Endlosschleife zwischen `authGuard` und `guestGuard`, weil das Token blieb). Jetzt beendet der Guard die Sitzung, wenn der Benutzer nicht geladen werden kann; die Anmeldeseite sagt bei 404 „Firma unbekannt oder gesperrt“.
- Dev: Betreiber-Konto angelegt; die alten Tabellen `personal_access_tokens`, `cache`, `cache_locks` in `bustotal_central` wurden zu `legacy_*` umbenannt (nicht gelöscht), weil die zentrale Datenbank eigene braucht.

**Erledigt (2026-11-21, Phase 4 Fuhrpark – Fahrzeuge):**
- Fahrzeuge (`vehicles`, UUID, archivieren statt löschen): Fuhrparknummer und Kennzeichen eindeutig (Kennzeichen wird vereinheitlicht, archivierte Fahrzeuge blockieren es weiter), Art, Hersteller/Modell/Baujahr, Fahrgestellnummer (17 Zeichen ohne I/O/Q), Sitzplätze, Ausstattung (8 feste Schlüssel), Maße, Gesamtgewicht, Kostensatz je km, vier Fristen als Datumsspalten (HU, SP, Fahrtenschreiber, Wartung).
- Fristen: abgelaufen (vor heute), bald fällig (heute bis 30 Tage, `Vehicle::WARN_DAYS`), sonst in Ordnung; die Logik steht im Backend (`Vehicle::deadlines`) und gleichlautend im Frontend (`shared/deadline.ts`, Testfälle in `deadline.spec.ts`).
- Rechte: Lesen Admin/Disponent/Buchhaltung, Ändern und Archivieren Admin/Disponent, Wiederherstellen nur Admin, Fahrer kein Zugriff (kommt mit den Einsätzen).
- Oberfläche: Fahrzeugliste nach Tabellen-Muster (server-seitig; Sortierung auch nach „Nächste Frist“, Filter Art und Fristen, Treffer-Hinweis für die Fahrgestellnummer), Formular nach Formular-Muster, Menüpunkt „Fahrzeuge“, Startseiten-Kachel „Fällige Fristen im Fuhrpark“ (Admin, Disponent; nur sichtbar, wenn etwas fällig ist), Hilfethema `fuhrpark.md`, Rollentabelle ergänzt.
- 204 Backend-Tests grün (22 neu in `VehicleTest`), `ng build` läuft, Fristenlogik per Hilfsskript geprüft. **Im Browser noch nicht angesehen.**
- **Für die Entwicklung:** Die neue Tabelle muss in die Firmen-Datenbanken: `php artisan tenant:migrate`.
- **Offen in Phase 4:** Fahrer/Personal (Führerschein, Fahrerkarte, Code 95, Verfügbarkeit, Urlaub), Fahrzeug-Fristen für Fahrer in dieselbe Kachel, Demo-Daten für Fahrzeuge, Verfügbarkeit/Werkstatt-Status eines Fahrzeugs (mit der Disposition, Phase 7).

**Erledigt (2026-11-21, Phase 4 Fuhrpark – Fahrer und Personal):**
- Fahrer (`drivers`, UUID, archivieren statt löschen, z. B. bei Austritt): eigene Personalnummer (eindeutig), Name, Geburtsdatum, Kontakt, Adresse, Beschäftigung (Vollzeit/Teilzeit/Aushilfe/Extern), Eintritt, Stundensatz, Führerscheinklassen (B bis DE), Führerschein- und Fahrerkartennummer, fünf Fristen (Führerschein, P-Schein, Fahrerkarte, Code 95, ärztliche Untersuchung).
- Abwesenheiten (`driver_absences`: Urlaub, Krank, Schulung, Sonstiges, erster und letzter Tag): Überschneidungen beim selben Fahrer werden abgelehnt, direkt anschließende Zeiträume sind erlaubt; „heute abwesend“ in Liste und Detail. Das ist die Grundlage für die Verfügbarkeitsprüfung der Disposition (Phase 7).
- Fristenlogik jetzt gemeinsam (`HasDeadlines`) für Fahrzeuge und Fahrer; Kachel „Fällige Fristen beim Personal“ neben der des Fuhrparks.
- **Rechte (Entscheidung von mir, bitte bestätigen):** Fahrer sind Personendaten, deshalb nur Admin und Disponent (lesen und ändern); Buchhaltung und Fahrer kein Zugriff; Wiederherstellen nur Admin.
- Oberfläche: Liste und Formular nach den Mustern, Reiter „Abwesenheiten“, Menüpunkt „Fahrer“, Hilfethema `fahrer.md` (Entwurf), Rollentabelle ergänzt.
- Tests: Backend alle grün (224 insgesamt, 20 neu in `DriverTest`), `ng build` läuft. **Im Browser noch nicht angesehen.** Neue Tabellen in die Firmen-Datenbanken: `php artisan tenant:migrate`.
- **Offen:** Verknüpfung Fahrer ↔ Benutzerkonto (für die Kachel „Ihre Einsätze“, kommt mit der Disposition), Datenschutz für Personendaten (Aufbewahrung nach Austritt, Anonymisierung, Auskunft) mit Steuerberater/Datenschutz klären, Fristen und Nachweise fachlich prüfen lassen. Phase 4 ist damit funktional abgeschlossen.

**Erledigt (2026-11-22, Demo-Daten für den Fuhrpark):**
- `php artisan bustotal:demo-daten` legt jetzt neben den Kunden auch 14 Fahrzeuge, 20 Fahrer und Abwesenheiten an (`--art=kunden|fuhrpark|alles`, `--fahrzeuge`, `--fahrer`, `--entfernen`). Fristen und Abwesenheiten sind relativ zu heute gesetzt, damit die Kacheln „Fällige Fristen“ und „heute abwesend“ immer etwas zeigen. Wiederholbar, mit `is_demo` markiert (Entfernen trifft nie echte Daten), Namenskonflikte mit echten Datensätzen werden übersprungen. Anleitung: `docs/DEMO-DATEN.md`.
- Neue Migration `add_is_demo_to_fleet_tables` (Firmen-Datenbanken: `php artisan tenant:migrate`). 244 Backend-Tests grün (17 neu in `DemoFleetTest`; die Kunden-Tests nutzen `--art=kunden`).
- Das Kennzeichen „Demo“ steht jetzt auch in den Fahrzeug- und Fahrerformularen (wie bei den Kunden).

**Erledigt (2026-11-22, Phase 5 Streckenplanung – Backend-Grundlage):**
- Schnittstellen `RoutingProvider` und `GeocodingProvider` (`app/Routing/`), umgesetzt mit openrouteservice (Profil Bus/Lkw mit Fahrzeugmaßen, Zugangsschlüssel nur in `.env` als `ORS_API_KEY`, im Header statt in der Adresse, Zwischenspeicher, Wiederholung nur bei Serverfehlern). Austausch gegen einen anderen Dienst: eine Klasse + eine Zeile im `AppServiceProvider`.
- `GET /api/geocode` und `POST /api/routes/calculate` (nur Admin und Disponent, 60 Anfragen/Minute): Strecke, Leerfahrten getrennt, Fahrzeit, Ablauf mit Pausen und Ruhezeiten (`DrivingTimePlanner`, VO 561/2006: 4,5 h → 45 min, 9 h/Tag, 11 h Ruhe, 13-Stunden-Arbeitstag), Warnungen, Linienzug für die Karte, Fahrzeugkosten als Rechengrundlage. Speichert nichts.
- Fehler des Dienstes kommen deutsch als 422 (Eingabe) oder 424 (Dienst), nie als 5xx, weil das Frontend 502/503/504 sonst als „Server nicht erreichbar“ deutet.
- 291 Backend-Tests grün (47 neu: `DrivingTimePlannerTest`, `RoutePlanningTest`; der Dienst wird nie wirklich angerufen). **Nicht mit dem echten Dienst ausprobiert** (kein Schlüssel eingetragen) und im Browser nichts zu sehen (noch keine Oberfläche).
- **Offen/Entscheidungen:** Schlüssel besorgen und Nutzungsbedingungen/Kontingent für den gewerblichen Einsatz prüfen (alle Firmen teilen einen Schlüssel); Fahrzeiten mit echten Fahrten abgleichen (`ROUTING_DURATION_FACTOR`); Lenkzeitregeln fachlich prüfen lassen; Kachelanbieter für die Karte wählen.

**Nächster Schritt:** ORS-Schlüssel eintragen und die Streckenplanung einmal real ausprobieren, dann die Oberfläche (Ortssuche, Halteliste, Karte, Ablauf) bauen; Betriebshof als Firmeneinstellung. Parallel: Hilfetexte fachlich prüfen lassen, Phase 0 nachholen (Kalkulationsschema, DATEV-Daten vom Steuerberater, Mittwald-Klärungen).


