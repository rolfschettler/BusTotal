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

**Nächster Schritt:** Hilfesystem besprechen, dann Phase 0 nachholen (Kalkulationsschema, DATEV-Daten vom Steuerberater, Mittwald-Klärungen) und Phase 3 Mandantenfähigkeit (Subdomain → eigene DB je Mandant) oder Phase 4 Fuhrpark.


