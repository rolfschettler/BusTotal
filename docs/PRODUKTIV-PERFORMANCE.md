# Performance im Produktivbetrieb

Gehört zu Phase 8 (Betrieb auf Mittwald, siehe `FAHRPLAN.md`). Die Entwicklungsumgebung wird dafür nicht weiter
optimiert. Der Code selbst ist unauffällig: Listen sind seitenweise (`paginate(20)`), Suchspalten und Fristen
haben Indizes, die Mandantenabfrage ist eine einzelne indizierte Abfrage.

## 1. Server (einmal beim Einrichten prüfen)

| Punkt | Soll | Warum |
|---|---|---|
| PHP als FPM/FastCGI, nicht CLI | Standard bei Mittwald | Mehrere Anfragen gleichzeitig (der lokale `artisan serve` arbeitet nacheinander) |
| OPcache | aktiv (`opcache.enable=1`), `validate_timestamps=0` wenn möglich | Größter Einzelhebel: Laravel liest sonst bei jeder Anfrage alle PHP-Dateien neu |
| PHP-Version | ≥ 8.3, neueste unterstützte | Schneller und sicher |
| Datenbank | MySQL auf demselben Server | Jede Abfrage ohne Netzwerkstrecke; eine Seite macht mehrere |
| HTTP/2 und Kompression (gzip/brotli) | aktiv | Kleinere, parallel geladene Angular-Dateien |

Prüfen lässt sich OPcache mit einer temporären Datei `phpinfo()` (danach löschen!) oder dem Mittwald-Kundencenter.

## 2. Einstellungen in der `.env` auf dem Server

```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
CACHE_STORE=file
SESSION_DRIVER=file
```

`CACHE_STORE=file` statt `database`: Zähler der Anmeldebegrenzung (`throttle`) schreiben sonst bei jeder Anfrage
in die Datenbank. Die Anwendung nutzt Token statt Sitzungen, `SESSION_DRIVER` ist daher unkritisch.
Redis nur verwenden, wenn der Hoster es bietet.

## 3. Bei jedem Deployment

```
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan migrate --force        # je Mandant, siehe MANDANTEN.md
```

Nach `config:cache` liest Laravel die `.env` nicht mehr. Änderungen an der `.env` wirken erst nach erneutem
`config:cache`. Bei aktivem `validate_timestamps=0` zusätzlich OPcache leeren (PHP-FPM neu laden).

## 4. Angular

- Ausschließlich den Produktions-Build ausliefern: `ng build` (Standardkonfiguration „production“, minifiziert,
  Dateinamen mit Hash). Nie `ng serve` oder den Development-Build.
- Dateien mit Hash im Namen (`*.js`, `*.css`) dürfen lange im Browser-Cache bleiben (z. B. 1 Jahr),
  `index.html` darf nicht gecacht werden (sonst sieht niemand neue Versionen).
- Seiten werden bereits einzeln nachgeladen (`loadComponent`), das hält den Start klein. Die Budgets in
  `frontend/angular.json` (700 kB Warnung) melden, wenn der Start zu groß wird.

## 5. Auf Dauer beobachten

- Langsame Seiten: Anzahl der API-Aufrufe je Seite und Antwortzeit im Browser-Netzwerk-Tab.
- Bei großen Datenmengen: Das Dashboard liest alle Fahrzeuge und Fahrer, um Fristen zu berechnen. Das ist bei
  üblichen Fuhrparks klein, bei mehreren Hundert Fahrzeugen auf eine datenbankseitige Filterung umstellen.
- Die Kundensuche nutzt Teiltext-Suche; bei sehr vielen Kunden (Zehntausende) auf Volltextindex prüfen.
- Messen vor Optimieren: erst auf einem Test-Deploy (siehe Fahrplan, Verifikation), nicht lokal.
