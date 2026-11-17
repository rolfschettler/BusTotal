> Stand: 2026-11-17 – Phase 1.1 erledigt, 1.2 teilweise. Planung vom 2026-11-16. Erledigte Schritte mit [x] abhaken.

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

## [ ] Phase 3 – Mandantenfähigkeit
- Zentrale DB (Mandanten, Domains, DB-Zugangsdaten verschlüsselt) + DB je Mandant
- Middleware: Subdomain → Mandant → DB-Verbindung; Migrationen für alle Mandanten-DBs
- Superadmin-Oberfläche: Mandant anlegen (DB-Zugangsdaten eintragen → migrieren → Admin-User)

## [ ] Phase 4 – Fuhrparkverwaltung
- Fahrzeuge (Typ, Sitzplätze, Ausstattung, Maße/Gewicht, km-Kostensatz), Fristen (HU, SP, Tacho, Wartung)
- Fahrer/Personal (Führerschein, Fahrerkarte, Code 95, Verfügbarkeit, Urlaub)

## [ ] Phase 5 – Streckenplanung (OSM)
- Routing-Schnittstelle + Adapter für externen Dienst, Geocoding, Karte
- Route mit Zwischenstopps, Leerfahrten Depot↔Start/Ziel, Fahrzeit inkl. Pflichtpausen und Lenkzeitprüfung
- Ergebnis als Kalkulationsbasis speichern

## [ ] Phase 6 – Belegwesen
- Kalkulationsengine; Belegkette Angebot → Auftrag → AB → Rechnung
- GoBD: festgeschriebene Rechnungen, Storno/Gutschrift, lückenlose Nummern, §14 UStG
- PDF, E-Mail-Versand, **DATEV-EXTF-Export** + Debitoren; später E-Rechnung

## [ ] Phase 7 – Disposition
- Plantafel Fahrzeuge × Fahrer, Drag & Drop, Konfliktprüfung (Doppelbelegung, Fristen, Lenkzeiten, Sitzplätze)
- Fahrauftrag als PDF

## [ ] Phase 8 – Betrieb auf Mittwald
- Deployment-Ablauf, Cronjobs, Backups, Monitoring; Sicherheitsprüfung

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
- **Offen:** Serverseitige Fehlermeldungen von Laravel sind teils noch englisch (z. B. bei Kunden-Ansprechpartnern); deutsche Meldungen müssen zentral ergänzt werden. Kein E-Mail-Versand (deshalb vorläufiges Passwort statt Einladung); kein „Passwort vergessen“ für nicht angemeldete Benutzer (der Admin setzt zurück).

**Nächster Schritt:** Hilfetexte fachlich prüfen lassen, dann Phase 0 nachholen (Kalkulationsschema, DATEV-Daten vom Steuerberater, Mittwald-Klärungen) und Phase 3 Mandantenfähigkeit (Subdomain → eigene DB je Mandant) oder Phase 4 Fuhrpark.


