@echo off
rem OPcache beschleunigt PHP deutlich. "artisan serve" reicht -d-Schalter nicht an den Server weiter,
rem deshalb laedt PHP_INI_SCAN_DIR die Datei backend\dev-php\opcache.ini zusaetzlich zur php.ini.
rem Die php.ini des Systems bleibt unveraendert.
start "Backend" /D "%~dp0backend" cmd /k "set PHP_INI_SCAN_DIR=%~dp0backend\dev-php&& php artisan serve"
start "Frontend" /D "%~dp0frontend" cmd /k "set NG_CLI_ANALYTICS=false&& ng serve"
