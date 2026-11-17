# Umbenennung: vom Arbeitsnamen „BusTotal“ zum echten Namen

„BusTotal“ ist ein **Arbeitsname**. Der echte Name wird am Ende des Projekts gewählt. Damit das ein kleiner, vorhersehbarer
Schritt bleibt, gilt ab sofort:

> **Neue Texte, Seiten und Meldungen schreiben den Produktnamen nie fest.** Im Frontend kommt er aus `APP_NAME`
> (`frontend/src/app/core/app-info.ts`), im Hilfetext steht der Platzhalter `{{app}}`, im Backend gilt `config('app.name')`
> (aus `APP_NAME` in `.env`).

Wichtig: Der Name in der Kopfleiste ist **nicht** der Produktname, sondern der **Firmenname des Mandanten** (Einstellungen › Firmendaten).
Der Produktname erscheint nur als Rückfall (solange noch keine Firma angelegt ist), im Browser-Tab beim Laden und im Hilfetext.

## A. Sichtbarer Name (billig, jederzeit änderbar)

| Wo | Was ändern |
|---|---|
| `frontend/src/app/core/app-info.ts` | `APP_NAME` (steuert Rückfall-Name, Tabtitel, Hilfetext) |
| `backend/.env` und `backend/.env.example` | `APP_NAME=…` (später auch E-Mail-Absender, PDF-Kopf, sobald es sie gibt) |
| `frontend/src/index.html` | `<title>` (nur der Titel vor dem Laden, danach setzt der BrandService ihn) |
| `frontend/public/favicon.ico` | Symbol, falls es zum Namen gehört |
| Dokumentation `docs/*.md`, `CLAUDE.md` | Name in Überschriften und Texten |

## B. Technische Namen (für Anwender unsichtbar, erst ändern, wenn der Name feststeht)

Ein Durchgang am Ende, danach `php artisan test` und `ng test` laufen lassen. Suche: `grep -rIni "bustotal"` (ohne `node_modules`, `vendor`, `dist`, `.git`).

| Was | Stellen | Folge der Änderung |
|---|---|---|
| Artisan-Befehle `bustotal:admin-anlegen`, `bustotal:demo-daten` | `Console/Commands/CreateAdmin.php`, `DemoData.php`, `DatabaseSeeder.php`, Tests, Tooltip in `customer-form.component.html`, `docs/DEMO-DATEN.md`, Hilfetexte | Neue Befehle, Anleitungen anpassen |
| Datenbanknamen `bustotal_central`, `bustotal_test` | `backend/.env`, `phpunit.xml`, `setup-dev-db.sql`, `setup-test-db.sql` | Datenbank neu anlegen oder umbenennen. **Auf dem Server (Mittwald) mit Bedacht**, am besten beim ersten echten Rollout gleich mit dem neuen Namen starten |
| Speicherschlüssel im Browser `bustotal_token`, `bustotal_last_email` | `core/auth.service.ts`, `pages/login/login.component.ts` | Alle Nutzer werden einmal abgemeldet |
| Test-Mailadressen `@bustotal.test` | `DatabaseSeeder.php`, `UserManagementTest.php` | Nur Entwicklung und Tests |
| Ordner `D:\angular\BusTotal`, Git-Repository | Dateisystem, GitHub | Pfade in Skripten prüfen (`start-server.bat` nutzt relative Pfade) |

## C. Nie ändern

- `NAMESPACE_UUID` in `backend/app/Support/ImportId.php`: Aus ihm entstehen stabile Import-IDs. Nur der Kommentar „Namensraum von BusTotal“
  darf umbenannt werden, **nicht** der Wert.

## D. Außerhalb des Codes

Markenrecherche (DPMA/EUIPO) und Domain-Verfügbarkeit **vor** der Namensentscheidung prüfen. Danach: Domain, E-Mail-Absender,
Mittwald-Projektname, Logo und Favicon, Impressum und Datenschutzerklärung.
