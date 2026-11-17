@echo off
start "Backend" /D "%~dp0backend" cmd /k "php artisan serve"
start "Frontend" /D "%~dp0frontend" cmd /k "set NG_CLI_ANALYTICS=false&& ng serve"
