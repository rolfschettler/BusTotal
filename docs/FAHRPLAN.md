> Stand: 2026-11-16 â€“ Planung abgeschlossen. Erledigte Schritte mit [x] abhaken.

# BusTotal â€“ Grober Entwicklungsfahrplan (Bustouristik)

## Kontext
Neues, leeres Projekt (`D:\angular\BusTotal`). Ziel: mandantenfÃ¤hige Branchensoftware fÃ¼r Busunternehmen mit
Kunden-, Fuhrpark-, Dispositions-, Beleg- (Angebot â†’ Auftrag â†’ AB â†’ Rechnung, DATEV) und Streckenplanungsmodul (OSM).
Eine Codebase, beliebig viele Mandanten, **je Mandant eigene Datenbank**, Backend PHP, Frontend Angular.
Entwickler hat noch keine Erfahrung mit Laravel und Docker â†’ Claude begleitet Schritt fÃ¼r Schritt mit ErklÃ¤rungen.

Getroffene Entscheidungen:
- Mandantenzugriff Ã¼ber **Subdomain** (`firma.bustotal.de`) â†’ Mandant wird aus dem Host-Header ermittelt
- Hosting: vorhandener **Mittwald Agentur-Server (altes Kundencenter)** â†’ klassisches PHP/MySQL-Hosting, **kein Docker**
- DATEV: **Buchungsstapel-Export (EXTF-CSV)** + GoBD-konforme Rechnungen
- PHP-Framework: **Laravel (Empfehlung)** â€“ bitte bestÃ¤tigen

### Folgen aus dem Mittwald-Hosting (wichtig)
| Thema | Konsequenz |
|---|---|
| Datenbank | Produktiv **MySQL**. Code bleibt trotzdem DB-neutral (Eloquent), Postgres bleibt spÃ¤ter mÃ¶glich. |
| DB je Mandant | Datenbanken werden im Kundencenter **manuell angelegt**; Zugangsdaten trÃ¤gt der Superadmin in BusTotal ein, danach migriert BusTotal die DB automatisch. (Automatisches `CREATE DATABASE` ist auf Shared-Hosting i. d. R. nicht erlaubt.) |
| Routing / OSM | Kein eigener Routing-Server mÃ¶glich â†’ **externer Routing-Dienst** Ã¼ber eine Schnittstelle im Code (z. B. openrouteservice, GraphHopper; Bus-/LKW-Profil, Kosten/Kontingente prÃ¼fen). Austauschbar, damit spÃ¤ter eigener Server mÃ¶glich ist. |
| Hintergrundjobs | Keine dauerhaft laufenden Worker â†’ Laravel-Queue mit Datenbank-Treiber, abgearbeitet per **Cronjob** (`schedule:run` minÃ¼tlich). |
| Wildcard-Subdomain | **Bei Mittwald klÃ¤ren**: `*.bustotal.de` auf das Laravel-Verzeichnis + SSL-Zertifikat. Fallback: Subdomains je Kunde einzeln anlegen. |
| Deployment | Per SSH/Git + `composer install` + `php artisan migrate` (fÃ¼r alle Mandanten); Angular als statische Dateien hochladen. Bei Mittwald klÃ¤ren: SSH, Composer, PHP-Version â‰¥ 8.3, Cron. |

---

## [ ] Phase 0 â€“ Voraussetzungen schaffen

### Fachlich
- **Glossar & Prozesse**: Anfrage â†’ Kalkulation â†’ Angebot â†’ Auftrag â†’ AB â†’ Disposition â†’ DurchfÃ¼hrung â†’ Rechnung â†’ DATEV
- **Kalkulationsschema**: km-Satz, Stundensatz Fahrer, Pauschalen, Maut, Ãœbernachtung, ZuschlÃ¤ge, MwSt
  (PersonenbefÃ¶rderung 7 %/19 % je nach Strecke bzw. Ausland â€“ Steuerberater einbinden)
- **Rechtliches**: GoBD (UnverÃ¤nderbarkeit, lÃ¼ckenlose Nummernkreise, Storno statt LÃ¶schen), DSGVO, Lenk-/Ruhezeiten (VO 561/2006)
- **E-Rechnung** (XRechnung/ZUGFeRD) ab 2027/2028 Pflicht â†’ Architektur jetzt vorsehen
- **DATEV-Daten** vom Steuerberater: SKR03/04, ErlÃ¶skonten, Berater-/Mandantennummer, Debitorenkreis

### Technisch
- **Lokale Entwicklungsumgebung ohne Docker** (passt zum Mittwald-Ziel, weniger Lernaufwand):
  vorhandenes PHP 8.4 + Node 24, dazu Composer, MySQL (lokal), Git, VS Code/PhpStorm, Angular CLI.
  Alternativ Laravel Herd fÃ¼r Windows. Docker kann spÃ¤ter fÃ¼r den Routing-Test ergÃ¤nzt werden.
- Monorepo: `/backend` (Laravel), `/frontend` (Angular), `/docs`
- REST-API mit OpenAPI-Beschreibung â†’ Angular-Client generieren
- Auth: Laravel Sanctum, Rollen je Mandant (Admin, Disponent, Buchhaltung, Fahrer)
- Frontend: Angular, Angular Material oder PrimeNG, Leaflet fÃ¼r Karten
- Kartenkacheln: nicht die openstreetmap.org-Tiles produktiv nutzen â†’ Tile-Anbieter wÃ¤hlen
- PDF: serverseitig mit dompdf (lÃ¤uft auf Shared-Hosting)
- Git-Hosting (GitHub/GitLab) + CI (Tests bei jedem Push), Backup-Konzept je Mandanten-DB

---

## [ ] Phase 1 â€“ Fundament
1. Repo, lokale Umgebung, Laravel- und Angular-GrundgerÃ¼st
2. Auth, Benutzer, Rollen/Rechte, Audit-Log (GoBD)
3. Mandanten-Einstellungen (Firmendaten, Briefkopf, Bank, SteuersÃ¤tze, Nummernkreise)
4. Angular-Shell: Login, Layout, Navigation, Guards, API-Client

## [ ] Phase 2 â€“ Kundenverwaltung (gleichzeitig Laravel-Lernmodul)
- Kunden (Firma/Privat/Schule/Verein/ReisebÃ¼ro), Ansprechpartner, Adressen, Zahlungsbedingungen, Debitorennummer
- Suche, DublettenprÃ¼fung, Notizen, DSGVO-Export/LÃ¶schung

## [ ] Phase 3 â€“ MandantenfÃ¤higkeit
- Zentrale DB (Mandanten, Domains, DB-Zugangsdaten verschlÃ¼sselt) + DB je Mandant
- Middleware: Subdomain â†’ Mandant â†’ DB-Verbindung; Migrationen fÃ¼r alle Mandanten-DBs
- Superadmin-OberflÃ¤che: Mandant anlegen (DB-Zugangsdaten eintragen â†’ migrieren â†’ Admin-User)

## [ ] Phase 4 â€“ Fuhrparkverwaltung
- Fahrzeuge (Typ, SitzplÃ¤tze, Ausstattung, MaÃŸe/Gewicht, km-Kostensatz), Fristen (HU, SP, Tacho, Wartung)
- Fahrer/Personal (FÃ¼hrerschein, Fahrerkarte, Code 95, VerfÃ¼gbarkeit, Urlaub)

## [ ] Phase 5 â€“ Streckenplanung (OSM)
- Routing-Schnittstelle + Adapter fÃ¼r externen Dienst, Geocoding, Karte
- Route mit Zwischenstopps, Leerfahrten Depotâ†”Start/Ziel, Fahrzeit inkl. Pflichtpausen und LenkzeitprÃ¼fung
- Ergebnis als Kalkulationsbasis speichern

## [ ] Phase 6 â€“ Belegwesen
- Kalkulationsengine; Belegkette Angebot â†’ Auftrag â†’ AB â†’ Rechnung
- GoBD: festgeschriebene Rechnungen, Storno/Gutschrift, lÃ¼ckenlose Nummern, Â§14 UStG
- PDF, E-Mail-Versand, **DATEV-EXTF-Export** + Debitoren; spÃ¤ter E-Rechnung

## [ ] Phase 7 â€“ Disposition
- Plantafel Fahrzeuge Ã— Fahrer, Drag & Drop, KonfliktprÃ¼fung (Doppelbelegung, Fristen, Lenkzeiten, SitzplÃ¤tze)
- Fahrauftrag als PDF

## [ ] Phase 8 â€“ Betrieb auf Mittwald
- Deployment-Ablauf, Cronjobs, Backups, Monitoring; SicherheitsprÃ¼fung

## Verifikation
- Automatisierte Tests je Modul (PHPUnit/Pest, Angular-Tests, Playwright fÃ¼r Belegkette)
- Mandantentrennung explizit testen
- DATEV-Export vom Steuerberater testweise importieren lassen
- Routing-Ergebnisse mit realen Fahrten vergleichen
- FrÃ¼h einen Test-Deploy auf Mittwald (nach Phase 1), um Hosting-Grenzen rechtzeitig zu erkennen

## Offene KlÃ¤rungen mit Mittwald
- [ ] Wildcard-Subdomain `*.bustotal.de` + SSL mÃ¶glich?
- [ ] SSH, Composer, PHP â‰¥ 8.3, Cronjobs verfÃ¼gbar?
- [ ] Anzahl MySQL-Datenbanken im Tarif (begrenzt die Mandantenzahl)?

## NÃ¤chster konkreter Schritt
**Erledigt (2026-11-17):** Lokale Umgebung, Git-Repo, Laravel (/backend, MySQL `bustotal_central`), Angular (/frontend).`n**Nächster Schritt:** Phase 1 Punkt 2 – Auth (Sanctum), Benutzer, Rollen, Audit-Log; dann Kundenverwaltung als Lernmodul.

