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
  - **Update 2026-11-19 (Mittwald-Ticket #6077836):** Die App ist auf dem Agentur-Server machbar, dort gilt aber ein **PHP-Memory-Limit von 1024M**. Mittwald rät zu einem **mStudio Managed vServer** (nur gebuchte Ressourcen als Grenze; 1 vCPU / 2 GB RAM reichen für Start und Entwicklung, später skalierbar; Testzeitraum inzwischen 30 Tage). **Entscheidung offen** – Details unter „Offene Klärungen mit Mittwald“.
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

### Offene Fragen aus der Belegwesen-Recherche (Recherche 2026-11-20, Fragen übernommen 2026-11-23, Quellen und Einordnung in `docs/fachlich/belegwesen.md`)
**Maßgebliche Festlegungen (2026-11-22): `docs/fachlich/loesungskonzept-abrechnung.md`** (Entscheidungen nach bestem Wissen, Strittiges einstellbar, Freigabeliste für den Kunden). Die Fragenliste an den Berater ist demgegenüber nur noch Prüfliste.
**Frühere Gesamtliste für den Steuerberater (2026-11-22, 26 Fälle mit Zahlenbeispiel, Frage und Annahme, in Teilen 1 bis 4):** `docs/fachlich/fragen-steuerberater.md`.
Diese Fragen blockieren nichts, die Software ist so gebaut, dass die Antworten nur Einstellungen oder kleine Änderungen auslösen. Bis zur Antwort gelten die Annahmen in Klammern.
**An den Steuerberater:**
1. Steuersatz je Fahrtart (Charter, Transfer, Schulbus, Linie, Ausflug), insbesondere die 50-km-Regel nach § 12 Abs. 2 Nr. 10 UStG. (Satz je Position einstellbar, Vorbelegung 19 %; bei 0 % ist ein Steuerhinweis Pflicht.)
2. Grenzüberschreitende Fahrten: Streckenanteile je Land und Steuersatz nach § 3b UStG, nötige Nachweise. (Nicht umgesetzt; die Streckenplanung könnte später den Anteil je Land liefern.)
3. Pauschalreisen: Muss die Software die Margenbesteuerung nach § 25 UStG abbilden? (Nicht umgesetzt; ändert Rechnung und Steuerausweis grundlegend, deshalb **zuerst beim Auftraggeber klären, ob es das gibt**.)
4. Gutschriften, Stornos, Korrekturen: gleicher oder eigener Nummernkreis? Zahlungseingang und Steuerentstehung bei Anzahlungen (§ 13 UStG). (Eigener Kreis `gutschrift`; jede festgeschriebene Anzahlung gilt als erhalten.)
5. Aufbewahrung von Angeboten und Auftragsbestätigungen: 6 Jahre? (Rechnungen 8 Jahre seit 2025 laut Quellen; noch **keine Löschsperre** gebaut.)
6. Steuer je Satz aus der Nettosumme statt je Position. (So gerechnet.)
7. Umsatzgrenze und Beginn der E-Rechnungspflicht (2027 über 800.000 € Vorjahresumsatz, 2028 alle B2B; Quellen widersprechen sich bei den BMF-Schreiben, am Original prüfen). (Rechnung liegt als strukturierte Daten vor; XRechnung/ZUGFeRD noch nicht gebaut.)
**An den Auftraggeber:** Kommen Pauschalreisen vor? Welche Belegarten braucht der Alltag (Mahnung?)? Format der Belegnummern je Firma? Wie viele Rechnungen pro Monat (für den Lasttest)? Darf der Disponent Angebote freigeben? (Bisher nur Admin und Buchhaltung.)

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

## [ ] Phase 5 – Streckenplanung (OSM) (Backend und Oberfläche gebaut, Speichern offen)
- [x] Routing-Schnittstelle + Adapter für externen Dienst (openrouteservice), Geocoding – Details: `docs/STRECKENPLANUNG.md`
- [x] Route mit Zwischenstopps, Leerfahrten Depot↔Start/Ziel, Fahrzeit inkl. Pflichtpausen und Lenkzeitprüfung (Berechnung, `POST /api/routes/calculate`)
- [x] Oberfläche: Ortssuche, Halteliste, Karte (Leaflet), Zeitplan mit Warnungen (Seite „Streckenplanung“; im Browser mit erfundenen Antworten des Routing-Dienstes geprüft, **mit dem echten Dienst noch nicht**)
- [x] Mit dem echten Dienst ausprobiert (2026-11-22, siehe Eintrag unten)
- [x] **Betriebshöfe und gespeicherte Orte (2026-11-27):** Mehrere Betriebshöfe je Firma (Reiter „Betriebshöfe“, Standard), Koordinaten einmal gesucht und in der zentralen Tabelle `places` gespeichert (`PlaceResolver`, Baustein `HasPlace`, eine Adresse nie zweimal gesucht), Auswahl in der Streckenplanung statt Browser-Speicher, Tests, Hilfetexte. Architektur für Adressen, Haltestellen und Reiseziele: `docs/STRECKENPLANUNG.md` (Gespeicherte Orte). Im Browser noch nicht geprüft. Offen: Kunden- und Fahrer-Adressen anbinden, Nachtrag-Befehl.
- [ ] Kachelanbieter für die Karte wählen
- [ ] Ergebnis als Kalkulationsbasis speichern: **zweifach** (Tour-Archiv zur Wiederverwendung und Schnappschuss am Beleg), je Strecke mit **Fahrzeugtyp und Anzahl** statt konkretem Fahrzeug, mehrere Strecken je Reisevorhaben. Konzept: `docs/fachlich/workflow-reisevorhaben.md`

## [ ] Phase 6 – Belegwesen
Fachliche Grundlagen: `docs/fachlich/belegwesen.md` (Recherche, offene Fragen an Steuerberater und Auftraggeber).
**Fachlicher Workflow (festgehalten 2026-11-21, noch nicht gebaut):** `docs/fachlich/workflow-reisevorhaben.md`. Kurzfassung: Streckenplanung (Fahrzeugtyp) → Angebot → Auftragsbestätigung mit vorläufigem Preis, daraus je Bus ein **Fahrtauftrag** → Disposition (Fahrzeug, Fahrer 1/2) → Fahrt (Fahrer erfasst Zusatzleistungen, Kosten, km-Stand an der Grenze) → **Rückerfassung** → Rechnung aus den Fahrtaufträgen (Gesamtrechnung je **Reisevorhaben** oder Sammelrechnung über beliebige Fahrtaufträge, z. B. Werksverkehr) → Auslandsanteil und § 25 für das Finanzamt. Das **Reisevorhaben** (Hinfahrt, Fahrten vor Ort, Rückfahrt, je mit einem oder mehreren Bussen) ist ein optionaler, schlanker Container, Summen und Status werden abgeleitet.
**Reihenfolge der Umsetzung (festgelegt 2026-11-22, Begründung und Abhängigkeiten in `docs/fachlich/workflow-reisevorhaben.md` Abschnitt 8):** Etappe 0 Grundlagen im Belegwesen (Fahrtart und Steuersatz, Zahlungseingang, Belegfeinschliff) → **Spur E-Rechnung** (Termin hängt vom Vorjahresumsatz des Kunden ab: über 800.000 € sofort nach Etappe 0, sonst vor Etappe 4) → 1 Tour, Schnappschuss, Reisevorhaben → 2 Fahrtauftrag → 3 Rückerfassung → 4 Rechnung aus Fahrtaufträgen (mit Sammelrechnung und § 3b) → 5 § 25 → 6 Voranmeldungsbericht, Datenexport, DATEV, Löschsperre → 7 Disposition (Phase 7, vorziehbar).
- [x] **Etappe 0 (2026-11-22): Fahrtart und Steuersatz, Zahlungseingang, Belegfeinschliff.** **Im Browser durchgespielt (2026-11-22, mit dem Nutzer):** Preise mit Sätzen je Fahrtart, Fahrtart im Beleg und in der Streckenplanung (50-km-Hinweis), Angebot aus der Tour, Zahlungen (Teilzahlung, Überzahlung abgelehnt, Rest, Storno einer Buchung), Storno einer Rechnung mit Rückzahlung. Beim Testen gefunden und behoben: Raster in Preisen und Position, Seitenleiste, fehlende Hinweise auf die zwei Wege zum Angebot, Zahlungsabschnitt erst nach F5 (Zahlstand fehlte in der Antwort beim Festschreiben), sekundäre Taste im Formular sah deaktiviert aus. Migrationen `2026_11_29_100000_add_trip_kind_to_documents_and_pricing` und `2026_11_30_100000_create_document_payments_table` ausgeführt (dev und firma2, 0 Fehler). 477 Backend-Tests grün (neu: `TripKindTest` 15, `PaymentTest` 15), `ng build` läuft.
  - **0.1 Fahrtart und Steuersatz:** Enum `TripKind` (Gelegenheitsverkehr, Linienverkehr, Schüler-/Schulträgerfahrt, Sonstiges), Spalte `document_lines.trip_kind`, Sätze je Fahrtart in `pricing_settings` (`tax_rate` = Regelsatz, neu `tax_rate_line`, `tax_rate_school`, Standard 7), `PricingSetting::taxRateFor()`. Angebot aus der Tour trägt die Fahrtart und setzt den Satz daraus (Streckenplanung: Auswahl „Fahrtart“, Hinweis über 50 km). Beleg: Auswahl „Fahrtart“ je Position stellt den Satz ein, Folgebelege und Storno übernehmen sie. **Prüfhinweise** (`TaxHints`, nur Warnungen, nie Sperre): ermäßigter Satz bei Gelegenheitsverkehr, 50-km-Erinnerung bei Linie und Schule; sie stehen als `warnings` in der Antwort von Anzeigen, Anlegen und Ändern. Einstellungen › Preise: drei Sätze.
  - **0.2 Zahlungseingang:** Tabelle `document_payments` (nie gelöscht, nur storniert mit Grund), Enums `PaymentKind` und `PaymentMethod`, `PaymentService` (Zahlstand aus den Buchungen, Rechnung bleibt unverändert; Betrag höchstens der offene Betrag, Rückzahlung höchstens der bezahlte, kein Datum in der Zukunft, Zeilensperre gegen gleichzeitige Buchungen), Schnittstellen `GET/POST /api/documents/{id}/payments` und `POST …/payments/{id}/cancel` (Lesen Admin, Disponent, Buchhaltung; Buchen und Stornieren Admin, Buchhaltung). Zahlstand (`payment`: zu zahlen, bezahlt, offen, Stand, überfällig) an Anzeige und Liste, die Liste holt die Summen je Seite mit einer Abfrage. Oberfläche: Baustein `document-payments` im festgeschriebenen Beleg, Spalte „Stand“ der Liste zeigt bezahlt, teilweise, offen, überfällig.
  - **0.3 Belegfeinschliff:** Der Storno-Beleg heißt **„Stornorechnung“** statt „Gutschrift / Storno“ (UStAE 14.3 Abs. 2 S. 5; technischer Schlüssel `gutschrift` bleibt). Die Anzahlungsrechnung ist im PDF durch den Titel „Anzahlungsrechnung“ erkennbar, der Leistungszeitraum steht schon im Kopf der PDF, daran war nichts zu ändern.
  - **Hilfe:** neues Thema „Fahrten anlegen, durchführen und abrechnen“ (`fahrten.md`, wächst mit jeder Etappe), Abschnitte „Fahrtart und Steuersatz“ und „Zahlungen“ in `belege.md`, Einstellungen und Streckenplanung ergänzt, `funktionen.md` auf den Stand gebracht.
  - **Seitenleiste neu geordnet (2026-11-22, beim Testen gefunden):** Gruppen nach dem Arbeitsablauf: **Fahrten und Abrechnung** (Streckenplanung, Belege), **Stammdaten** (Kunden, Fahrzeuge, Fahrer), **Verwaltung** (Benutzer, Einstellungen); Start und Hilfe stehen außerhalb. Gruppenüberschriften klein, grau, mit Trennlinie, Einträge mit Symbol und eingerückt. Streckenplanung und Neues Angebot verweisen auf den jeweils anderen Weg zum Angebot (zwei Wege, Anleitung Schritt 2). Im Browser angesehen. **Spätere Menüpunkte** (Reisevorhaben, Fahrtaufträge, Zahlungen u. a.) kommen in die Gruppe „Fahrten und Abrechnung“ in Arbeitsreihenfolge.
  - **Formular-Raster (2026-11-22, beim Testen gefunden):** Einstellungen › Preise mischte Hälften (6+6) und Drittel (4+4+4), die Spalten standen nicht untereinander. Neue Regel „Ein Formular, ein Raster“ in `docs/ClaudeCodePatterns/EditFormPattern.md` (Drittel oder Viertel, nie mischen, Zeilen müssen nicht voll sein); Preise auf das Drittelraster gebracht. **Noch zu prüfen und anzugleichen:** übrige Reiter der Einstellungen, Kunden-, Fahrzeug- und Fahrerformular, Positionen im Beleg (6+2+2+2).
  - **Offen aus Etappe 0:** Karma-Tests für die neuen Oberflächenteile (kein Chrome eingerichtet); Filter „offen/überfällig“ in der Belegliste; Warnung beim Festschreiben, wenn eine Rechnung nur teilweise bezahlt ist, ist nicht vorgesehen.
- [x] **Spur E-Rechnung, Teil XRechnung (2026-11-22, Kunde verlangt sie zwingend):** Entwurf und Feldzuordnung in `docs/fachlich/erechnung-xrechnung.md`. XRechnung 3.0 (CII) mit `horstoeko/zugferd`; Kundenfelder `einvoice`, `buyer_reference` (Leitweg-ID); Belegfelder `einvoice_*` (Migrationen `2026_12_01_100000` und `110000`). `EInvoiceService` erzeugt die XML beim Festschreiben (Typ 380/386/381, Schlussrechnung mit Anzahlungen, Steuerkategorien S/E/O), prüft vorher die Pflichtangaben (nichts wird festgeschrieben, keine Nummer verbraucht), archiviert mit SHA-256; Download `GET /api/documents/{id}/einvoice`; Taste im Beleg, Hilfe, 22 neue Tests (499 grün). **Offen:** Prüfung einer Beispieldatei mit dem KoSIT-Validator, Test im Browser, **ZUGFeRD** (PDF/A-3), Anlagen (Fahrtenliste), E-Mail-Versand.
- [ ] **Etappe 1:** Reisevorhaben (optionaler Container mit Abschnitten), Tour-Archiv und Schnappschuss am Beleg, Angebot mit mehreren Strecken und Fahrzeugtypen
- [ ] Fahrtauftrag als Beleg (mehrere je Auftrag, Erfassungsteil, Fahrer-Fassung ohne Preise), Rückerfassung, Abrechnungsstatus, Rechnung aus Fahrtaufträgen (Gesamt- und Sammelrechnung), Rahmenauftrag für wiederkehrende Fahrten
- [ ] Auslandsanteil aus den Grenzdaten und § 25-Marge, erst nach Klärung mit dem Steuerberater. Recherche und Bauplan: `docs/fachlich/paragraph-25-abrechnung.md` (Marge je Reisevorhaben, Vorleistungen in `travel_costs`, `MarginCalculator`, Rechnung ohne Steuerausweis)
- [x] **Beleg-Backend (2026-11-23):** Tabellen `documents` und `document_lines`, Dienst `DocumentService` (Entwurf, Festschreiben, Storno, Folgebeleg), Rechner `DocumentCalculator` (ganze Cent, Steuer je Satz aus der Nettosumme), Schnittstelle `/api/documents`, 29 Tests. Details unten.
- [x] **Oberfläche für Belege (2026-11-23):** Liste (`/belege`, Tabellen-Muster, Filter nach Art und Stand, Suche mit Markierung), Formular (`/belege/neu`, `/belege/:id`, Formular-Muster, Kundenauswahl per Suche, Positionen mit Summenvorschau, Festschreiben, Storno, Folgebeleg), Hilfetext `belege.md`. Gebaut und per Einheitstest geprüft (Rechenlogik `document-calc.ts`), im Browser geprüft (2026-11-23: anlegen, speichern, Festschreiben mit Fehlerliste, Rechnung festschreiben, Storno, Folgebeleg, Liste mit Markierung). Abweichung vom Tabellen-Muster: Liste hat keine sortierbaren Spaltenköpfe (feste Reihenfolge nach Datum).
- [x] **Preisfindung (2026-11-24), Vorlage:** `PriceCalculator` (Schnittstelle, austauschbar im `AppServiceProvider`) mit `StandardPriceCalculator`, Preise je Firma in `pricing_settings` (Einstellungen › Reiter „Preise“, nur Admin ändert), `QuoteService` macht aus den Kennzahlen einer Tour (loaded/empty km, Lenkzeit, Tage, Zuschlag) ein Angebot als Entwurf (`POST /api/quotes/from-tour`), Knopf „Angebot erstellen“ unter dem Ergebnis der Streckenplanung. 14 Backend-Tests. Bausteine: Fahrstrecke (Leerkilometer zu x %), Fahrerzeit (Stundensatz × Lenkzeit), Tagespauschale, Übernachtung, Mindestpreis-Ausgleich, Zuschlag in %. **Das ist eine Vorlage, nicht das endgültige Kalkulationsschema** (Phase 0, mit Auftraggeber und Steuerberater klären). Maut fehlt bewusst (von Hand als Position). Zum Server gehen nur Zahlen, nie Route oder Orte (Lizenzfrage der Routendaten, siehe `STRECKENPLANUNG.md`). Gebaut und getestet (Build, Einheitstests), im Browser geprüft (2026-11-24: Reiter Preise speichern, Tour München – Salzburg, Angebot mit 4 Positionen und Zuschlag, Summen stimmen); in einer vorhandenen Entwicklungsdatenbank erst `php artisan tenant:migrate` ausführen.
- [x] **Preis je Fahrzeugtyp (2026-11-25):** Tabelle `pricing_vehicle_rates`, je Typ abweichende Werte für km, Stunde, Tag, Nacht und Mindestpreis (leer = Standard), Typ kommt aus dem gewählten Fahrzeug der Tour, Reiter „Preise“, Tests, Hilfetexte. Im Browser noch nicht geprüft.
- [x] **Wochenende und Feiertag automatisch (2026-11-26):** Bundesland des Betriebssitzes in den Firmendaten (je Tour änderbar), Feiertage je Land selbst gerechnet (`GermanHolidays`, Ostern nach Meeus/Jones/Butcher), Sätze für Samstag, Sonntag, Feiertag in den Preisen, Zuschlag je Tag auf den Tagesanteil der Summe (höchster Satz je Tag, nie doppelt), Tage in deutscher Zeit, Tests, Hilfetexte. Im Browser noch nicht geprüft.
- [ ] Preisfindung ausbauen: Kalkulationsschema des Auftraggebers, Maut, Angebot aus gespeicherter Tour
- [ ] Angebot/Auftragsbestätigung: neue Version statt Änderung nach dem Festschreiben
- [x] **Schlussrechnung mit Abzug der Anzahlungen (2026-11-25):** Anzahlungsrechnung aus einem Angebot/AB mit Vorschlag 30 % (je Steuersatz eine Position, Prozent per `percent` änderbar). Die Schlussrechnung (Rechnung aus demselben Ursprungsbeleg) zeigt die **volle Leistung** und zieht festgeschriebene, nicht stornierte Anzahlungen **getrennt** ab (Spalten `deductions`, `deduction_net/tax/gross`, `payable_total`; § 14 Abs. 5 UStG: Gesamtentgelt, Anzahlung mit Steuer, Restbetrag). Jede Anzahlung nur von einer gültigen Schlussrechnung (nach Storno wieder frei), Abzug wird beim Festschreiben neu bestimmt, Anzahlungen über dem Rechnungsbetrag verhindern das Festschreiben, Storno spiegelt den Abzug. 13 neue Backend-Tests (362 gesamt), Summenblock im Formular, Hilfetext. Im Browser geprüft (2026-11-25: Anzahlung 30 % festschreiben, Schlussrechnung mit Abzug und „Zu zahlen“, Festschreiben, Storno mit gespiegeltem Abzug). In einer vorhandenen Entwicklungsdatenbank erst `php artisan tenant:migrate`. **Offen für den Steuerberater:** Die Steuer auf Anzahlungen entsteht mit dem Zahlungseingang; die Software kennt den Zahlungseingang noch nicht und behandelt jede festgeschriebene Anzahlungsrechnung als erhalten.
- [ ] Gutschrift (Teilbetrag), Zahlungseingang erfassen (offene Posten, Anzahlung bezahlt?), Zuordnung der Anzahlungen auch über Auftragsbestätigung hinweg (bisher nur gleicher Ursprungsbeleg)
- [ ] GoBD: festgeschriebene Rechnungen, Storno/Gutschrift, lückenlose Nummern, §14 UStG (Grundlage steht, siehe unten)
- [x] **PDF der Belege (2026-11-26):** `GET /api/documents/{id}/pdf` (Admin, Disponent, Buchhaltung), erzeugt mit **dompdf** (`composer require dompdf/dompdf`, reine PHP-Bibliothek, LGPL, keine Zusatzprogramme; auf Mittwald nötig: `dom`, `mbstring`, `gd`, alle vorhanden), `DocumentPdf` + Vorlage `resources/views/pdf/document.blade.php`. Inhalt: Briefkopf mit Logo und Markenfarbe, Empfängeranschrift, Belegnummer/Datum/Leistungszeitpunkt/Fälligkeit, Positionen, Steuer je Satz, Anzahlungsabzug und „Zu zahlen“, Steuerhinweise als Fußnoten, Zahlungshinweis mit Bankverbindung, Fußzeile mit Firmenangaben und Seitenzahl. Entwurf mit Hinweis „ENTWURF“. Festgeschriebene Belege zeigen die **Kopie** von Absender und Empfänger (neu mit Briefkopf-Zusatz und Fußzeile im `seller`-Stand). Knopf „PDF“ im Formular und Symbol in der Liste. Jeder Abruf steht im Änderungsprotokoll (`pdf_created`). 13 neue Tests (375 gesamt). Das Layout wurde an einem Beispiel-PDF (2 Seiten) erzeugt; das Fenster der Browser-Vorschau durfte die Datei nicht öffnen, **Layout vom Nutzer an den Beispiel-PDFs gesehen und für gut befunden (2026-11-26).** Feinabstimmung nach DIN 5008 mit Fensterumschlag bleibt offen.
- [x] **Archiv-PDF beim Festschreiben (GoBD, 2026-11-28).** `DocumentArchive` (`app/Services/DocumentArchive.php`): Das PDF eines festgeschriebenen Belegs entsteht **einmal** in der Transaktion von `DocumentService::issue()` (Festschreiben und Storno), liegt im privaten Speicher (Disk `local`, je Mandant, `archive/documents/{Beleg-ID}.pdf`), SHA-256 in `documents.archive_sha256` (neu: `archive_path`, `archive_sha256`, `archived_at`; per API nicht sichtbar). `GET /api/documents/{id}/pdf` liefert bei festgeschriebenen Belegen die abgelegte Datei nach Prüfsummenprüfung, nie neu erzeugt; Entwürfe weiter frisch. **Fehlerfall festgelegt:** Scheitert Erzeugung oder Ablage, rollt die Transaktion zurück: Beleg bleibt Entwurf, keine Nummer verbraucht (422 mit Hinweistext), eine schon abgelegte Datei wird entfernt. Stimmt die Prüfsumme nicht oder fehlt die Datei: 500 mit Hinweis, Eintrag `archive_check_failed` im Änderungsprotokoll, **kein stilles Neuerzeugen**. **Alte Belege ohne Archiv-PDF** werden beim ersten Abruf einmalig nacherzeugt (`archive_backfilled` im Protokoll, Logo und Farbe dann von diesem Tag, `archived_at` zeigt es). Das Modell erlaubt die Archivfelder nach dem Festschreiben nur einmal zu setzen. Die Datei wird nie vom Programm gelöscht; die DSGVO-Anonymisierung berührt sie nicht (das PDF enthält bewusst die Kopie von Absender/Empfänger, die Aufbewahrungspflicht geht vor, siehe `docs/fachlich/belegwesen.md`). **Betrieb:** `storage/app/private/archive` je Mandant gehört in die Datensicherung. Die Belegtests nutzen `Storage::fake`. Neue Tests in `DocumentArchiveTest`. Der E-Mail-Versand nutzt `DocumentArchive::read()` für den Anhang. Offen: Löschsperre bis Fristende für ganze Mandanten und ein Befehl zur Stichprobenprüfung der Prüfsummen.
- [x] **Druckvorschau für PDFs (2026-11-28)**: generischer Baustein `PrintPreviewService` + `<app-print-preview>` (pdf.js `pdfjs-dist` 4.10.38, legacy-Build, erst beim Klick geladen, Worker als Asset unter `/pdfjs/`). PDF erscheint als Overlay in der App statt in neuem Tab, Drucken über den Druckdialog des Geräts (PC, Android, iOS), Herunterladen als Notausgang. Belegliste (Druckersymbol) und Belegformular (Taste „Drucken“) nutzen ihn; Regel in `CLAUDE.md`. **Auf Tablets (Android, iPad) noch vom Nutzer zu prüfen.**
- E-Mail-Versand (verschickt später das Archiv-PDF), **DATEV-EXTF-Export** + Debitoren; später E-Rechnung

**Beleg-Backend, Entscheidungen (Stand 2026-11-23):**
- Belegarten: Angebot, Auftragsbestätigung, Anzahlungsrechnung, Rechnung, Gutschrift (= Storno). Anzahlung und Rechnung teilen sich den Nummernkreis `rechnung`, Storno nutzt `gutschrift`.
- Status `entwurf` (ohne Nummer, änderbar, löschbar) → `festgeschrieben` (Nummer, gesperrt). Die Nummer wird erst beim Festschreiben in derselben Transaktion vergeben: Fehler oder gelöschter Entwurf verbrauchen keine Nummer.
- Sperre gilt auch im Modell (`Document`, `DocumentLine`), nicht nur in der Schnittstelle. Erlaubt nach dem Festschreiben ist nur der Verweis auf den Storno-Beleg (`cancelled_by_id`).
- Absender und Empfänger werden **beim Festschreiben** als Kopie (`seller`, `recipient`) gespeichert, solange Entwurf folgt der Beleg dem Kundenstamm. Der Storno übernimmt die Kopien des Originals.
- Steuer je Satz aus der Nettosumme (nicht je Position), Steuersatz je Position einstellbar, bei 0 % ist ein Grund Pflicht. **[offen, Steuerberater]**
- Pflichtangaben-Prüfung beim Festschreiben (Rechnungen: Anschriften, Steuernummer/USt-IdNr., Leistungszeitpunkt, Positionen). Angebot und AB brauchen weniger.
- Rechte: Entwürfe Admin/Disponent/Buchhaltung, Festschreiben und Storno nur Admin/Buchhaltung (Disponent darf Angebote bisher nicht freigeben – mit Auftraggeber klären).
- Einzelinstanz-Modus: nichts im Beleg-Code setzt mehrere Firmen voraus (alles liegt in der Mandanten-Datenbank).
- Noch **nicht** gebaut (Stand 2026-11-28): Löschsperre nach Aufbewahrungsfrist/Verknüpfung mit DSGVO-Anonymisierung (Beleg trägt Kopie, Kunde kann anonymisiert werden, siehe `belegwesen.md` Abschnitt 2), E-Mail, DATEV, E-Rechnung. PDF und Archiv-PDF sind inzwischen gebaut (siehe Liste oben).

## [ ] Phase 7 – Disposition
- Plantafel Fahrzeuge × Fahrer, Drag & Drop, Konfliktprüfung (Doppelbelegung, Fristen, Lenkzeiten, Sitzplätze)
- Fahrauftrag als PDF (Fahrer-Fassung ohne Preise, zugleich Erfassungsblatt; Fahrzeugtyp wird hier zum konkreten Fahrzeug, ein oder zwei Fahrer je Fahrzeug; Konzept: `docs/fachlich/workflow-reisevorhaben.md`)
- Fachliche Grundlagen: `docs/fachlich/lenk-ruhezeiten.md` (Recherche vom 2026-11-28 mit Quellen, Abgleich mit `DrivingTimePlanner`, Folgerungen, offene Fragen an Verband/Gewerbeaufsicht und Auftraggeber).
  **Wichtigste Erkenntnisse:** Die bestehende Tourplanung ist eine sichere, einfache Auslegung **für eine Tour** und widerspricht den recherchierten Regeln nicht, reicht für die **Disposition aber nicht**: Dort zählt der **Verlauf je Fahrer und Woche** (Wochenlenkzeit 56 h, Doppelwoche 90 h, höchstens zwei 10-Stunden-Tage je Woche, höchstens drei verkürzte Tagesruhezeiten, Wochenruhezeit 45/24 h). Weitere Punkte: Einsatzart (Gelegenheitsverkehr oder Linie bis 50 km) und **Auslandsfahrt** am Einsatz (12-Tage-Regel nur grenzüberschreitend, **offen**), **Zweierbesatzung** (9 h in 30 h), **Arbeitszeit** (48/60 h) braucht eine Arbeitszeitbuchung, Warnung statt Sperre, Grenzwerte an **einer** Stelle.
- [x] **Fristen „Fahrerkarte auslesen“ (28 Tage) und „Fahrzeugeinheit auslesen“ (90 Tage) (2026-11-28).** Neue Spalten `drivers.driver_card_read_due_date` und `vehicles.tachograph_read_due_date` (wie alle Fristen als Datum „auslesen bis“), eingetragen in `DEADLINES`, in der Fristen-Funktion (`HasDeadlines`), im SQL-Sortierausdruck der Listen und im Fristfilter. **Eigene Vorwarnzeit je Frist** (`WARN_DAYS_OVERRIDE`: Fahrerkarte 7, Fahrzeugeinheit 14 Tage statt 30, sonst wäre eine 28-Tage-Frist sofort „bald fällig“); die Antwort enthält `warn_days`, das Frontend rechnet mit `deadlineState(days, warnDays)`. Schnittstellen `POST /api/drivers/{id}/card-read` und `POST /api/vehicles/{id}/tachograph-read` (Admin, Disponent; optional `date` bis heute) setzen die nächste Frist = Datum + 28/90 Tage (Regel nur im Backend: `Driver::CARD_READ_EVERY_DAYS`, `Vehicle::TACHOGRAPH_READ_EVERY_DAYS`). Formulare: Feld „Auslesen bis“ und Taste „Karte ausgelesen“ bzw. „Einheit ausgelesen“ (erst nach dem Speichern aktiv). Kacheln der Startseite zeigen die Fristen ohne Änderung. Demodaten mit überfälligen und bald fälligen Fällen. Hilfetexte `fahrer.md`, `fuhrpark.md`. 13 neue Backend-Tests (437 gesamt), 1 neuer Frontend-Test (83 gesamt). Migration ausgeführt (dev, firma2). Im Browser geprüft (2026-11-28: Felder, Tasten „Karte ausgelesen“ und „Einheit ausgelesen“ mit Frist +28 bzw. +90 Tage, Warnfarbe an der Grenze von 7 bzw. 14 Tagen, Eintrag in der Kachel der Startseite). Die Software liest den Fahrtenschreiber nicht aus, sie erinnert nur. Offen: Mitführpflicht der Nachweise (28 oder 56 Tage) und Aufbewahrung klären (Fragen in `docs/fachlich/lenk-ruhezeiten.md`).
  **Fragezeichen mit Quellen (2026-11-28):** Im Formular von Fahrzeug und Fahrer steht ein Fragezeichen am Titel „Fristen“ und an jedem Fristfeld; es öffnet die Hilfe an der Stelle, die den Termin erklärt (`fuhrpark#fristen`, `fuhrpark#tacho-auslesen`, `fahrer#fristen`, `fahrer#karte-auslesen`). Die Hilfetexte nennen je Frist die Grundlage und führen eine **Quellenliste** mit Gesetzen (StVZO §§ 29, 57b, Anlage VIII/VIIIa; FeV §§ 23, 24, 48; FPersV § 5; BKrFQV), Behörden- und Kammermerkblättern, Stand der Lesung 2026-11-28. Externe Links tragen `target="_blank"` und `rel="noopener noreferrer"` (gesetzt im `help-parser` nach dem Bereinigen, mit Test `help-parser.spec.ts`), die Anwendung bleibt mit ihren Eingaben offen. Im Browser geprüft (2026-11-28): Fragezeichen am Titel und an den Feldern in Fahrer- und Fahrzeugformular, Sprung zu `fahrer#fristen` und `fuhrpark#tacho-auslesen`, Quellenliste mit 14 bzw. 7 externen Links. **Nicht belegt:** die Abstände der HU für Kraftomnibusse (Anlage VIII Nr. 2 prüfen, in der Hilfe nur verwiesen, keine Zahl genannt). Die Quellen sind beim Pflegen der Fristen regelmäßig zu prüfen.
- [ ] Fahrer-Zeitkonto je Woche aus den geplanten Einsätzen, Konfliktprüfung als Warnung mit Begründung, Zwei-Fahrer-Option, Einsatzart und Auslandsfahrt am Einsatz (Reihenfolge und Umfang nach Antworten des Verbands, siehe Fragen in der Fachdatei).

## [ ] Phase 8 – Betrieb auf Mittwald
- Deployment-Ablauf: **Skript `deploy\deploy.ps1`** (Anleitung `docs/DEPLOY.md`, Probelauf getestet, erster echter Lauf offen); Cronjobs, Backups, Monitoring; Sicherheitsprüfung
- Performance im Produktivbetrieb: Checkliste in `PRODUKTIV-PERFORMANCE.md` (OPcache, Caches, Produktions-Build)

## Verifikation
- Automatisierte Tests je Modul (PHPUnit/Pest, Angular-Tests, Playwright für Belegkette)
- Mandantentrennung explizit testen
- DATEV-Export vom Steuerberater testweise importieren lassen
- Routing-Ergebnisse mit realen Fahrten vergleichen
- Früh einen Test-Deploy auf Mittwald (nach Phase 1), um Hosting-Grenzen rechtzeitig zu erkennen

## Offene Klärungen mit Mittwald
Antwort von Mittwald (Ticket #6077836, 2026-11-19): „Kann sowohl im Agentur-Server als auch im mStudio vServer so umgesetzt werden“ – pauschal, **ohne Einzelantworten**. Agentur-Server: PHP-Memory-Limit 1024M. Empfehlung: Managed vServer (mStudio), 1 vCPU / 2 GB RAM zum Start, 10 Tage Test.
**Einzelantworten Mittwald (2026-11-20, mStudio-vServer):**
- Preis 1 vCPU / 2 GB RAM: **21 € mtl. zzgl. USt.**, vCPU/RAM/Speicher im mStudio frei einstellbar und erweiterbar. Testzeitraum **30 Tage** kostenlos für Bestandskunden, **einmal je Kundennummer**, über den **Vertragsdaten-Assistenten** im Kundencenter (gilt für den ersten darüber gebuchten Tarif). Kündigungsfrist 30 Tage, Backups 30 Tage bis 12 Monate.
- Wildcard-Subdomain auf eine App: **ja**. **Let's-Encrypt-Wildcard-Zertifikat im mStudio: ja**, automatisch ausgestellt und verlängert (keine DNS-Validierung durch den Kunden) → kein Zertifikat je Firma mehr.
- **Keine Root-Rechte**; SSH mit dem Schlüssel des mStudio-Benutzers; **Container möglich** (z. B. dauerhaft laufender Queue-Worker); minütliche Cronjobs im mStudio; PHP 8.3/8.4/8.5, mehrere Versionen je Projekt; Composer als Software der App.
- Datenbanken und -benutzer: **unbegrenzt**; **Anlegen per API derzeit nicht möglich** (soll wieder gehen) → Firmen-Datenbank bleibt Handarbeit im mStudio.
- [x] Wildcard-Subdomain `*.bustotal.de` + SSL möglich? – **ja** (siehe oben). Offen: Domain muss dafür vermutlich bei Mittwald (DNS) liegen.
- [x] SSH, Composer, PHP ≥ 8.3, Cronjobs verfügbar? – **ja**
- [x] Anzahl MySQL-Datenbanken im Tarif (begrenzt die Mandantenzahl)? – **Agentur-Server: beliebig viele möglich** (Kundencenter, 2026-11-19); Anlegen bleibt Handarbeit. Beim vServer noch bestätigen lassen.
- [ ] **Entscheidung:** Agentur-Server oder mStudio-Managed-vServer? **Tendenz vServer** (Wildcard-Zertifikat automatisch, Queue-Worker, kein `html/`-Zwang erwartet – im Test prüfen). Bei vServer entfallen Teile der Hosting-Folgen oben (eigener Queue-Worker statt Cron, `CREATE DATABASE` evtl. möglich, Docker ggf. möglich) – dann Tabelle „Folgen aus dem Mittwald-Hosting“ und Phase 8 anpassen.
- **Ergebnis Test-Deploy Agentur-Server (2026-11-19, Details `DEPLOY-TEST.md`):** SSH, PHP 8.5, Composer (als Datei), alle Erweiterungen, ausgehendes HTTPS, beliebig viele Datenbanken, Backend + zentrale Datenbank + Betreiber-Konto laufen. **Wichtigste Erkenntnis:** Webserver bedient **nur `html/`** (Ziele unter `files/` → Fehler 500). Lösung: Code in `files/…/backend`, öffentlicher Teil in `html/bt-test` (siehe `DEPLOY-TEST.md`). **Läuft (2026-11-20):** Betreiber-Anmeldung, Firma anlegen, Firmen-Anmeldung. **Nachteile des Agentur-Servers:** Wildcard-Subdomain erzeugt **keinen** DNS-Eintrag (Subdomains nur einzeln), Let's Encrypt nur pro Host, Wildcard-Zertifikat kostenpflichtig → je Firma Handarbeit. Der Routing-Dienst war vom Web-PHP aus erreichbar, lieferte aber zeitweise 503 (Ausfall beim kostenlosen Dienst, nicht beim Hosting). Tendenz **vServer**, sobald Mittwald die Fragen (Wildcard-SSL, Root, Worker) beantwortet hat.
- [ ] Testzeitraum (**30 Tage**, einmalig je Kundennummer – erst starten, wenn der Deploy vorbereitet ist) mit einem **Test-Deploy** nutzen: Wildcard, Cron/Queue, Composer, PHP-Version, Memory-Limit der Streckenplanung prüfen.

### Eignungsprüfung mStudio (Stand 2026-11-20, Schreibtischprüfung ohne Testzugang)
Quellen: Mittwald-Antworten (Ticket #6077836) und [Mittwald-Entwicklerdoku: PHP-Anwendungen](https://developer.mittwald.de/docs/v2/platform/workloads/php/), [Container](https://developer.mittwald.de/docs/v2/platform/workloads/containers/), [Deployer](https://developer.mittwald.de/docs/v2/guides/deployment/deployer/).

| Anforderung | Stand | Bewertung |
|---|---|---|
| PHP ≥ 8.3, Composer, SSH | PHP 8.3/8.4/8.5, Composer als App-Software, SSH-Schlüssel | **erfüllt** |
| Wildcard `*.domain` auf eine App + SSL | ja, Wildcard-Zertifikat automatisch | **erfüllt** (Vorteil gegenüber Agentur-Server) |
| Document Root `public/` statt `html/`-Zwang | Doku: Document Root pro App frei wählbar (auch `/current/public` bei Deployer) | **erfüllt, im Test bestätigen** – dann entfällt die `html/bt-test`-Aufteilung |
| Cron minütlich | ja | **erfüllt** (`schedule:run`) |
| Queue-Worker dauerhaft | Container möglich (eigenes Image nötig) | **machbar, mehr Aufwand**. Einfache Lösung: Cron minütlich `queue:work --stop-when-empty`; Container-Worker erst bei Bedarf |
| Memory-Limit (Streckenplanung) | nur gebuchte Ressourcen (z. B. 2 GB), kein fester PHP-Deckel genannt | **im Test prüfen** (`memory_limit`, `max_execution_time`) |
| MySQL, viele Datenbanken | unbegrenzt | **erfüllt** |
| Datenbank per Programm anlegen | per API derzeit nicht möglich | **Handarbeit** bleibt (Firma anlegen = DB im mStudio anlegen) |
| Root-Rechte | keine | unkritisch, solange alles über App/Container läuft |
| Ausgehendes HTTPS (Routing-Dienst) | beim Agentur-Server ja, vServer nicht ausdrücklich bestätigt | **im Test prüfen** |
| Mailversand (SMTP) | nicht geklärt | **offen**: Mail-Dienst/SMTP-Zugang für Systemmails klären |
| DNS der Domain | vermutlich muss die Domain bei Mittwald liegen | **offen** (Wildcard-DNS-Eintrag nötig) |
| Backups | 30 Tage bis 12 Monate | **Wiederherstellung (Datenbanken je Firma) im Test probieren** |
| Kosten | 1 vCPU / 2 GB: 21 € mtl. zzgl. USt., skalierbar, Kündigung 30 Tage | akzeptabel |

**Fazit:** Keine Hürde gegen mStudio gefunden, es ist dem Agentur-Server überlegen (Wildcard-SSL, freier Document Root, Ressourcen frei wählbar). **Entscheidung für mStudio-vServer**, vorbehaltlich der Punkte „im Test prüfen“.
**Testprogramm für die 30 Tage (am Stück abarbeiten):** 1. App mit Document Root `public/` anlegen, 2. Wildcard-Domain + Zertifikat, 3. Deploy mit Skript, 4. Betreiber + zwei Firmen, je eigene Datenbank, 5. Cron/Queue, 6. Streckenplanung (Speicher, Laufzeit, Routing-Dienst), 7. Systemmail, 8. Backup/Wiederherstellung, 9. Last mit realistischen Datenmengen.
**Lasttest (Schritt 9 präzisieren):** Szenario A „große Firma“: z. B. 50.000 Aufträge, 200 Touren pro Monat, mehrere Jahre Belege; gemessen werden Listen, Suche, PDF-Erzeugung, DATEV-Export, Streckenplanung. Szenario B „viele kleine Firmen“: z. B. 20 Firmen mit je 2.000 Aufträgen, gleichzeitig angemeldet. Szenario C: A und B gleichzeitig (Nachbar-Effekt). Messwerte: Antwortzeiten, Speicher, CPU, Queue-Rückstand, Datenbankgröße. Ergebnis fürs Angebot: welche Firmengröße auf welche Ressourcen (vCPU/RAM) passt.
**Agentur-Server** bleibt als **Staging** mit Demodaten (keine echten Kundendaten); Deploy-Skript dafür auf mehrere Ziele (`staging`, `produktiv`) erweitern.

## Architekturabwägung: eine Installation für alle Firmen oder eine Instanz je Firma (2026-11-20)
**Stand:** Entwicklungs- und Angebotsphase, die Kundenstruktur des Auftraggebers ist offen (wenige sehr große oder viele kleine Firmen). **Entscheidung: nichts umbauen**, beide Betriebsarten offen halten.
| | Eine Installation, viele Firmen (jetzt) | Eine Instanz je Firma |
|---|---|---|
| Code | komplexer (Mandant aus Subdomain, Datenbankwechsel, Betreiberportal) | einfacher |
| Neue Firma | im Portal, Datenbank bleibt Handarbeit | neue App, Datenbank, Domain; skriptbar |
| Updates | einmal ausrollen | N-mal; Versionsstände können auseinanderlaufen |
| Ressourcen | gemeinsam, günstig | je Instanz Grundverbrauch, teurer |
| Isolation | Trennung über Datenbanken; Last-Spitzen treffen alle | physisch getrennt, kein Nachbar-Effekt |
| Überblick | zentral im Portal | fehlt, eigenes Monitoring nötig |
**Faustregel:** bis ca. 5–10 Firmen sind getrennte Instanzen einfacher, ab ca. 15–20 oder bei Aufnahme neuer Firmen ohne Handarbeit lohnt die mandantenfähige Installation. Eigenbetrieb durch die Firmen oder Kundenanpassungen sprechen für getrennte Instanzen.
**Zielbild (Hybrid):** viele kleine Firmen auf einem gemeinsamen Server (mandantenfähig), wenige sehr große Firmen auf eigenem vServer im **Einzelinstanz-Modus** (derselbe Code, Konfigurationsschalter, Betreiberportal aus). Als Preisstufen „Shared“ und „Dedicated“ anbietbar.
**Folgerungen:**
- Im Code **keine Annahme**, dass es immer mehrere Firmen gibt (Einzelinstanz-Modus muss möglich bleiben). Der Schalter selbst wird erst bei Bedarf gebaut.
- **MySQL ist geeignet** (Datenmengen eines Busbetriebs sind klein, Indizes beachten). Engpässe eher bei PDF-Erzeugung, DATEV-Export, Routing-Dienst und langen Auswertungen auf der Live-Datenbank. Eine große Firma kann später auf einen eigenen Datenbankserver umziehen, der Code ist DB-neutral.
- **mStudio-vServer** ist am Start kein Engpass (vCPU/RAM/Speicher einstellbar), wächst aber nur nach oben. Getrennte Instanzen bringen Isolation, nicht automatisch mehr Leistung.
- Belastbare Aussagen erst nach dem **Lasttest** (Testprogramm mStudio, Schritt 9).
- [ ] **Offene Fragen an den Auftraggeber:** erwartete Firmenzahl im ersten Jahr, Betrieb durch uns (SaaS) oder durch die Firmen, Bedeutung der physischen Datentrennung.

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

**Erledigt (2026-11-22, Phase 5 Streckenplanung – Oberfläche):**
- Seite `/streckenplanung` (Menü „Planung“, Admin und Disponent): Ortssuche (nur auf Knopfdruck, Treffer markiert), Halte mit Aufenthalt und „Fahrer frei“, Reihenfolge ändern, optionaler Betriebshof mit Rückfahrt (Leerfahrten getrennt), Fahrzeug- und Abfahrtswahl, „Lange Tage“, Karte (Leaflet) mit Linienzug, Kennzahlen, Zeitplan nach Tagen, Abschnitte, Warnungen. Ergebnis wird als „veraltet“ markiert, wenn die Eingabe danach geändert wird. Hilfethema `strecken.md`, Rollentabelle ergänzt.
- Neu im Backend: `GET /api/vehicles/choices` (alle aktiven Fahrzeuge ohne Seiten). Frontend: `RouteService`, `MapComponent`, reine Hilfsfunktionen mit 12 Testfällen (per Hilfsskript geprüft), `ng build` läuft, 293 Backend-Tests sollten laufen (Fahrzeug-Tests 24 grün).
- **Im Browser geprüft** (2026-11-22, mit erfundenen Antworten für Suche und Berechnung, weil kein Schlüssel eingetragen ist): Suche mit Markierung, Halte, Betriebshof, Karte, Ergebnis, „veraltet“, Hilfe. Dabei gefunden und behoben: Karte so hoch wie das Formular (jetzt mitlaufend, feste Höhe), Kacheln falsch nach Größenänderung (ResizeObserver), lange Ortsnamen im Zeitplan (gekürzt). **Nach dem Einbau von Leaflet muss `ng serve` neu gestartet werden** (sonst fehlt das Leaflet-CSS, Marker liegen unter der Karte). Mit dem echten Dienst noch nicht. Kartenkacheln: vorerst `tile.openstreetmap.org` (nur zum Ausprobieren), vor dem Produktivbetrieb Anbieter wählen (`core/map-config.ts`). Details: `docs/STRECKENPLANUNG.md`.

**Erledigt (2026-11-22, Streckenplanung mit dem echten Dienst ausprobiert):**
- Schlüssel in `backend/.env` eingetragen (vom Nutzer), erster echter Test gegen openrouteservice. Dabei drei Fehler gefunden, die die erfundenen Testantworten nicht zeigen konnten, und behoben: (1) Der Dienst verlangt für die Routenberechnung `Accept: application/geo+json`, sonst 406/Code 2007. (2) Mit `instructions: false` (und `geometry_simplify`) liefert er **keine Abschnitte** mit Strecke und Fahrzeit je Halt; beides entfernt, der Linienzug wird stattdessen selbst auf höchstens 1500 Punkte ausgedünnt (Anfang und Ende bleiben). (3) Meldung für Code 2009 („keine Route zwischen den Orten“) erklärt jetzt, dass es an den Fahrzeugmaßen liegen kann.
- Ergebnis: Ansbach → Ulm Hbf → Hotel Bayerischer Hof München → Ansbach mit Demo-Bus B-01: 501 km (davon 349 km Leerfahrt), 7:02 Std. Lenkzeit, 8:52 Std. Gesamtdauer, Ende 14:52, Fahrzeugkosten 751,45 €. Die Abschnitte stimmen (144 / 152 / 205 km). Ø-Geschwindigkeit des Lkw-Profils rund 71 km/h: **mit echter Busfahrt vergleichen** (`ROUTING_DURATION_FACTOR`).
- Erkenntnisse zur Ortssuche: Sie sucht Namen wie in OpenStreetMap. „Ulm Hbf“ und „München Hbf“ treffen, „Ulm Hauptbahnhof“ liefert falsche Orte; Hotels mit Ort („Hotel Bayerischer Hof München“) treffen gut. Beispiele in Oberfläche und Hilfe geändert, Hilfe erklärt es. Länderfilter bewusst nicht gesetzt (Auslandstouren).
- Erkenntnis zu Fahrzeugmaßen: Mit Höhe 3,65 m ist der Startpunkt „München Hbf“ nicht erreichbar (nur die Höhe löst das aus, vermutlich niedrige Unterführung); andere Punkte in München gehen. Die Fahrzeugmaße wirken also wirklich.
- 296 Backend-Tests grün (5 neu rund um diese Fehler), `ng build` läuft. Nur Backend-Server neu starten, falls er die Änderung nicht übernimmt.

**Erledigt (2026-11-22, Eingabe der Streckenplanung vereinfacht):**
- Auf Wunsch des Nutzers („zu kompliziert, Von – über – Nach“): Statt Suche, Treffer einer Rolle zuordnen, Halteliste und Betriebshof nun drei klare Felder **Von**, **Über** (beliebig viele, mit Zwischenhalt-Knopf), **Nach**, dazu „Rückfahrt zum Start“, „Tauschen“, Fahrzeug und Abfahrt. Jedes Feld sucht selbst (nach kurzem Tippen), Treffer anklicken, Kreuz zum Ändern. Betriebshof, Aufenthalt/„Fahrer frei“ und lange Tage unter eingeklapptem „Weitere Angaben“. Hilfetext angepasst.
- Im Browser mit dem **echten Dienst** geprüft: Suche, Auswahl, Berechnung (Ulm Hbf → Bad Kissingen, 249 km, 3:16 Std.), Karte mit A/Z und Linienzug. 19 Testfälle der Anzeigelogik grün, `ng build` läuft.
**Fehler behoben (2026-11-22, Zwischenspeicher):** Bei der Streckenplanung gab die zweite gleiche Suche (und Route) einen Serverfehler, weil Objekte im Zwischenspeicher lagen. Jetzt nur Arrays, versionierte Schlüssel, Test mit Dateispeicher. 298 Backend-Tests grün.

**Erledigt (Ortsfelder ohne Vorschläge, Kontingent geschont):**
- Anlass: Das Tageskontingent der Ortssuche war aufgebraucht („Quota exceeded“, vom Dienst als 403 gemeldet und bisher fälschlich als „Zugang abgelehnt“ angezeigt). Ursache: Vorschläge beim Tippen.
- Auf Wunsch des Nutzers wie in RATIOserver: **kein Autocomplete mehr.** Von, Über, Nach und Betriebshof sind reine Textfelder (`place-field.component.ts`), der Text bleibt unverändert. Gesucht wird beim Berechnen, „Verwendet: …“ zeigt den gefundenen Ort. Enter im Feld berechnet. Optional „Reihenfolge der Zwischenhalte optimieren“ (Rundreiseproblem, `TourOptimizer`, Fahrzeitmatrix).
- Keine wiederholte Suche: Zwischenspeicher im Browser (`RouteService.locate`) und normalisierter Server-Zwischenspeicher; Sperre je Bereich bis Mitternacht UTC nach „Quota exceeded“; Tageszähler; `/geocode` auf 30 je Minute und Benutzer begrenzt. Einzelheiten in `docs/STRECKENPLANUNG.md`.
- Tests: Backend Streckenplanung 42 grün, Frontend 75 grün, `ng build` läuft. Im Browser noch nicht erneut geprüft.
- **Später:** gespeicherte Orte je Firma (Betriebshof, häufige Kunden) zuerst lokal suchen; für viele Firmen eigener oder bezahlter Geocoder hinter `GeocodingProvider`.


**Nächster Schritt:** Streckenplanung im Browser mit dem echten Dienst ansehen (`ng serve` neu starten), Kachelanbieter wählen, Fahrzeit mit echter Fahrt abgleichen, dann Betriebshof als Firmeneinstellung und Tour speichern. Parallel: Hilfetexte fachlich prüfen lassen, Phase 0 nachholen (Kalkulationsschema, DATEV-Daten vom Steuerberater, Mittwald-Klärungen).


