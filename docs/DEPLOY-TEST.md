# Test-Deploy auf dem Mittwald-Agentur-Server

Zweck: Hosting-Grenzen früh erkennen (siehe `FAHRPLAN.md`, „Offene Klärungen mit Mittwald“). Keine echten Kundendaten verwenden.
Platzhalter: `<test-domain>` = `bt-test.konzeptdata.de`. Der Name `bt-test` ist nur ein Testname.

## Geprüft (2026-11-19)
| Punkt | Ergebnis |
|---|---|
| SSH | geht (`ssh p576090@193.53.247.58`), Umgebung ist ein chroot; schreiben nur in `files/` (und `html/`, `tmp/`) |
| PHP (Konsole) | 8.5.12, alle nötigen Erweiterungen vorhanden; `memory_limit` 256M |
| Composer | nicht installiert → `~/files/tools/composer.phar` (läuft, 2.11.2) |
| Git | nicht vorhanden → Code per SFTP |
| Wildcard-DNS `*.bt-test` | wird angenommen |
| SSL | Let's Encrypt nur **pro Host** (kostenlos, 20 Zertifikate/Woche je Domain); Wildcard nur kostenpflichtig (49 € + 24,90 €/Monat netto) |
| Ausgehendes HTTPS | geht (openrouteservice erreichbar) |
| Dokumentenverzeichnis | frei wählbar: `/files/bt-test/backend/public` |

Folge für Mandanten: Jede Unterdomäne (`admin.<test-domain>`, `firma1.<test-domain>` …) braucht ein **eigenes** Let's-Encrypt-Zertifikat (Kundencenter → SSL-Zertifikate).

## Lösung (2026-11-20)
**Auf dem Agentur-Server wird nur der Ordner `html/` als Webverzeichnis bedient** (Ziele unter `files/` liefern Fehler 500, selbst für eine `test.txt`). Deshalb:
- Laravel (Code, `vendor`, `.env`, `storage`) bleibt in `files/bt-test/backend/` (nicht öffentlich).
- Der öffentliche Teil (Angular-Dateien, `index.php`, `.htaccess`) liegt in `html/bt-test/`; die Subdomains zeigen auf `html/bt-test`.
- `html/bt-test/index.php` hat `$base = __DIR__.'/../../files/bt-test/backend';` und lädt `maintenance.php`, `vendor/autoload.php` und `bootstrap/app.php` über `$base`.
- Die `.htaccess` mit `RewriteBase /` und absoluten Zielen funktioniert; die Anmeldeseite von `admin.bt-test.konzeptdata.de` erscheint.
Die folgenden Abschnitte beschreiben noch den ersten Versuch (Dokumentenverzeichnis `backend/public`); maßgeblich ist dieser Abschnitt.

## Stand 2026-11-19 (Abbruch, inzwischen gelöst)
Schritte 1 bis 4 sind erledigt, Zertifikate für `bt-test` und `admin.bt-test` aktiv. **Offen:** Jede Anfrage an `admin.bt-test.konzeptdata.de` (auch `/test.txt` ohne `.htaccess`) endet mit Fehler 500, im Apache-Log „exceeded the limit of 10 internal redirects“. Ziel der Subdomain: `/files/bt-test/backend/public/`; im Pfad steht ein `./` (`/home/www/p576090/./files/...`). Ungeprüft: Gegentest mit Ziel unter `html/` (z. B. `html/bt-probe`), danach Ticket bei Mittwald. Außerdem: Die Wildcard `*.bt-test` legt **keinen** DNS-Eintrag an, und Mandanten-Subdomains brauchen einzeln Zertifikate.

## 1. Vorbereiten
- Datenbank 1 (zentral): Name `usr_p576090_3`, Host `db003362.mydbserver.com`, Benutzer `p576090`.
- **Achtung:** Jede Datenbank kann auf einem **anderen** Datenbank-Host liegen (Kundencenter → Datenbanken, je Datenbank prüfen). Ein falscher Host zeigt sich als „Access denied … to database“ (Fehler 1044).
- Für eine Test-Firma wird eine **zweite** Datenbank gebraucht (Kundencenter → Datenbanken). Laut Kundencenter sind **beliebig viele** Datenbanken möglich (Stand 2026-11-19), das Anlegen bleibt aber Handarbeit im Kundencenter.

## 2. Backend hochladen (SFTP)
Nach `/files/bt-test/backend/` hochladen: alles aus `backend/` **außer** `vendor`, `node_modules`, `.env`, `storage/logs/*`, `storage/framework/*` (Ordnerstruktur von `storage` mitnehmen), `tests`.

## 3. Auf dem Server (SSH)
```bash
cd ~/files/bt-test/backend
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
php ~/files/tools/composer.phar install --no-dev --optimize-autoloader
```

`.env` auf dem Server anlegen (Passwörter selbst eintragen, nie in Chat oder Git):
```
APP_NAME="BusTotal"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://bt-test.konzeptdata.de
APP_LOCALE=de
LOG_LEVEL=warning

DB_CONNECTION=central
DB_HOST=db003362.mydbserver.com
DB_PORT=3306
DB_CENTRAL_DATABASE=usr_p576090_3
DB_USERNAME=p576090
DB_PASSWORD=<Passwort>

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

TENANT_BASE_DOMAIN=bt-test.konzeptdata.de
SUPERADMIN_SUBDOMAIN=admin
ORS_API_KEY=<Schlüssel>
```
`php artisan migrate` ohne Angaben ist in diesem Projekt ersetzt und baut zentrale Datenbank und alle Firmen auf (siehe `MANDANTEN.md`). Nur mit ausdrücklichem `--database` oder `--path` verhält es sich wie das Original.

Dann:
```bash
php artisan key:generate --force
php artisan migrate --database=central --path=database/migrations/central --force
php artisan superadmin:anlegen <e-mail>
php artisan config:cache && php artisan route:cache
```

## 4. Frontend
Lokal bauen (PowerShell, im Ordner `frontend`): `ng.cmd build --configuration production`.
Den **Inhalt** von `frontend/dist/frontend/browser/` nach `/files/bt-test/backend/public/` hochladen.
Dort die Datei `.htaccess` durch diese ersetzen (API an Laravel, alles andere an Angular):
```
DirectoryIndex index.html index.php
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{REQUEST_URI} ^/api/
    RewriteRule ^ index.php [L]
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^ index.html [L]
</IfModule>
```

## 5. Domains
- `admin.bt-test.konzeptdata.de` und eine Test-Firma (`firma1.bt-test.konzeptdata.de`): je ein Let's-Encrypt-Zertifikat bestellen.
- Wildcard `*.bt-test` muss dasselbe Ziel wie `bt-test` haben (`/files/bt-test/backend/public`).
- Erst wenn alles läuft: „HTTPS-Weiterleitung erzwingen“ aktivieren.

## 6. Prüfen und notieren
Anmelden unter `https://admin.bt-test.konzeptdata.de`, Firma anlegen (zweite Datenbank), unter der Firmen-Unterdomäne anmelden, Streckenplanung ausprobieren.
Cronjob (später, Phase 8): minütlich `php /files/bt-test/backend/artisan schedule:run` (Kundencenter → Cronjobs; dort Pfad-Schreibweise prüfen).
Ergebnisse in `FAHRPLAN.md` unter „Offene Klärungen mit Mittwald“ eintragen.
