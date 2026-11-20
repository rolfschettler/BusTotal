# Deploy: BusTotal auf einen Server einspielen

Ein Befehl (Windows PowerShell, im Projektordner):

```powershell
.\deploy\deploy.ps1 -Target agentur-test
```

Weitere Schalter: `-DryRun` (nur bauen und packen, nichts hochladen; Pakete liegen im Temp-Ordner), `-SkipBuild` (vorhandenen Frontend-Build verwenden).
Voraussetzungen auf dem PC: Windows 10/11 mit `ssh`, `scp`, `robocopy` (Bordmittel) und Angular CLI (`ng.cmd`).

## Was das Skript tut
1. Frontend bauen (`ng build --configuration production`).
2. Zwei ZIP-Pakete schnüren: **Backend** (ohne `vendor`, `.env`, `storage`, Tests, `public`) und **Web** (Angular-Build, `.htaccess`, `index.php`).
3. Per `scp` hochladen, dann per `ssh` auf dem Server: Wartungsmodus an → Code ersetzen (`app`, `routes`, `database`, `config`, `resources`, `lang`) →
   `composer install --no-dev` → zentrale Datenbank migrieren → `tenant:migrate` (alle Firmen) → Webdateien ersetzen → `config:cache`, `route:cache` → Wartungsmodus aus.
4. Bei einem Fehler bricht es ab und lässt den **Wartungsmodus an**, damit nie neuer Code auf alter Datenbank läuft. Fehler beheben, Skript erneut starten.

**Nie angefasst:** `.env`, `vendor` (nur Composer), `storage` (Logo, Logs), alle Daten.
Die Passwörter fragt `ssh` ab (zweimal: Hochladen, Ausführen). Mit einem SSH-Schlüssel entfällt das; auf dem mStudio ist das ohnehin die Anmeldeart.

## Einstellungen je Server: `deploy\targets.json`
Vorlage: `deploy\targets.example.json`; die echte Datei steht nicht im Git. Pfade sind **relativ zum Home-Ordner** des SSH-Benutzers.

| Einstellung | Bedeutung |
|---|---|
| `sshUser`, `sshHost`, `sshPort` (optional) | Zugang |
| `layout` | `split`: Server bedient nur einen Web-Ordner (Mittwald Agentur-Server: `html/`). Code liegt getrennt, `index.php` wird erzeugt. `classic`: Dokumentenverzeichnis ist `<appDir>/public` (normaler vServer) |
| `appDir` | Ordner des Laravel-Codes, z. B. `files/bt-test/backend` |
| `webDir`, `appDirFromWebDir` | nur bei `split`: Web-Ordner (`html/bt-test`) und Weg von dort zum Code (`../../files/bt-test/backend`) |
| `php`, `composer` | Befehle auf dem Server, z. B. `php` und `php ~/files/tools/composer.phar` |
| `tempDir` | Ordner für die hochgeladenen ZIP-Dateien (muss existieren), z. B. `files` |

## Beim ersten Mal auf einem neuen Server
Das Skript braucht eine vorhandene `.env` (es bricht sonst ab) und eine leere zentrale Datenbank:
1. Ordner anlegen, Datenbank anlegen, `.env` auf dem Server anlegen (Inhalt: `docs/DEPLOY-TEST.md`, Abschnitt 3, mit `APP_KEY=` Zeile).
2. `.\deploy\deploy.ps1 -Target <name>`.
3. Einmalig auf dem Server: `php artisan key:generate --force`, danach `php artisan superadmin:anlegen <e-mail>` (das Skript hat den Schlüssel noch nicht gesetzt; vor dem ersten Deploy erzeugen ist ebenfalls möglich).
4. Domain, Zertifikat und Dokumentenverzeichnis im Kundencenter bzw. mStudio einstellen.

## Grenzen
- Cronjob und Queue-Worker richtet das Skript nicht ein (Phase 8).
- Datenbanken der Firmen werden nicht angelegt (Handarbeit im Kundencenter/mStudio, solange es keine API gibt).
- Gelöschte Dateien im Frontend räumt es über die Namensmuster (`chunk-*.js`, `main-*.js`, `polyfills-*.js`, `styles-*.css`, `help`, `media`) auf.
- Getestet ist bisher nur der Probelauf (`-DryRun`); der erste echte Lauf steht aus.
