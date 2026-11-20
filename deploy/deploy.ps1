<#
.SYNOPSIS
  Spielt BusTotal auf einen Server ein (Windows PowerShell 5.1, Aufruf im Projektordner).

.DESCRIPTION
  1. Baut das Frontend (Produktion).
  2. Packt Backend und Webdateien in zwei ZIP-Dateien (ohne vendor, .env, storage, Tests).
  3. Laedt beide per scp hoch und fuehrt auf dem Server per ssh aus: Wartungsmodus, Code ersetzen,
     composer install, Zentral-Datenbank migrieren, Firmen-Datenbanken migrieren (tenant:migrate),
     Webdateien ersetzen, Caches, Wartungsmodus aus.

  Nie angefasst werden: .env, vendor (nur composer install), storage (Logo, Logs), Datenbanken ausser den Migrationen.

.EXAMPLE
  .\deploy\deploy.ps1 -Target agentur-test
  .\deploy\deploy.ps1 -Target agentur-test -DryRun      # nur bauen und packen, nichts hochladen
  .\deploy\deploy.ps1 -Target agentur-test -SkipBuild   # vorhandenen Frontend-Build verwenden

  Einstellungen je Server: deploy\targets.json (Vorlage: deploy\targets.example.json). Anleitung: docs\DEPLOY.md
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)][string]$Target,
    [switch]$SkipBuild,
    [switch]$DryRun
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$deployDir = $PSScriptRoot

function Step([string]$text) { Write-Host ""; Write-Host "== $text" -ForegroundColor Cyan }
function Fail([string]$text) { Write-Host "FEHLER: $text" -ForegroundColor Red; exit 1 }
function Check-Exit([string]$what) { if ($LASTEXITCODE -ne 0) { Fail "$what (Exit-Code $LASTEXITCODE)" } }

# --- Einstellungen laden -------------------------------------------------------------------------
$configFile = Join-Path $deployDir 'targets.json'
if (-not (Test-Path $configFile)) {
    Fail "deploy\targets.json fehlt. Kopiere deploy\targets.example.json nach deploy\targets.json und passe sie an."
}
$all = Get-Content -Raw -Encoding UTF8 $configFile | ConvertFrom-Json
$cfg = $all.$Target
if ($null -eq $cfg) { Fail "Ziel '$Target' steht nicht in deploy\targets.json (vorhanden: $(($all.PSObject.Properties.Name) -join ', '))." }

foreach ($key in 'sshUser', 'sshHost', 'layout', 'appDir', 'php', 'composer', 'tempDir') {
    if (-not ($cfg.PSObject.Properties.Name -contains $key) -or [string]::IsNullOrWhiteSpace([string]$cfg.$key)) {
        Fail "In targets.json fehlt fuer '$Target' die Einstellung '$key'."
    }
}
$layout = [string]$cfg.layout
if ($layout -notin 'classic', 'split') { Fail "layout muss 'classic' oder 'split' sein." }
if ($layout -eq 'split') {
    foreach ($key in 'webDir', 'appDirFromWebDir') {
        if (-not ($cfg.PSObject.Properties.Name -contains $key) -or [string]::IsNullOrWhiteSpace([string]$cfg.$key)) {
            Fail "Bei layout 'split' fehlt die Einstellung '$key'."
        }
    }
    $webDir = [string]$cfg.webDir
} else {
    $webDir = ([string]$cfg.appDir).TrimEnd('/') + '/public'
}
$appDir = ([string]$cfg.appDir).TrimEnd('/')
$tempDir = ([string]$cfg.tempDir).TrimEnd('/')
$sshPort = if ($cfg.PSObject.Properties.Name -contains 'sshPort') { [string]$cfg.sshPort } else { '22' }
$remote = "$($cfg.sshUser)@$($cfg.sshHost)"

foreach ($tool in 'ssh', 'scp', 'robocopy') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { Fail "'$tool' wurde nicht gefunden." }
}
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

Write-Host "Ziel: $Target ($remote, Layout $layout)"
Write-Host "Code:  $appDir"
Write-Host "Web:   $webDir"

# --- 1. Frontend bauen ---------------------------------------------------------------------------
$frontendDir = Join-Path $root 'frontend'
$distDir = Join-Path $frontendDir 'dist\frontend\browser'
if (-not $SkipBuild) {
    Step 'Frontend bauen (Produktion)'
    $env:NG_CLI_ANALYTICS = 'false'
    Push-Location $frontendDir
    try {
        & ng.cmd build --configuration production
        Check-Exit 'Frontend-Build'
    } finally { Pop-Location }
}
if (-not (Test-Path (Join-Path $distDir 'index.html'))) { Fail "Frontend-Build fehlt: $distDir\index.html" }

# --- 2. Paketieren -------------------------------------------------------------------------------
Step 'Pakete schnueren'
$work = Join-Path ([IO.Path]::GetTempPath()) ('bustotal-deploy-' + [guid]::NewGuid().ToString('N'))
$stageBackend = Join-Path $work 'backend'
$stageWeb = Join-Path $work 'web'
New-Item -ItemType Directory -Force -Path $stageBackend, $stageWeb | Out-Null

$backendSrc = Join-Path $root 'backend'
$xd = @('vendor', 'node_modules', 'tests', 'public', 'storage', 'dev-php', '.git') | ForEach-Object { Join-Path $backendSrc $_ }
$xd += (Join-Path $backendSrc 'bootstrap\cache')
$xf = @('.env', '.env.*', '*.sql', 'phpunit.xml', '.phpunit.result.cache', 'CLAUDE.md', 'AGENTS.md', 'README.md', 'package.json', 'vite.config.js')
& robocopy $backendSrc $stageBackend /E /XD $xd /XF $xf /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) { Fail "Kopieren des Backends fehlgeschlagen (robocopy $LASTEXITCODE)" }
# robocopy: ein Kopierlauf mit Exit-Code 1 bis 7 ist normal und kein Fehler.
$global:LASTEXITCODE = 0
# .env.example gehoert nicht aufs Hochladen, bleibt aber lokal Vorlage.
Remove-Item -ErrorAction SilentlyContinue (Join-Path $stageBackend '.env.example')
# Leere Bootstrap-Cache-Ordner brauchen wir nicht mitzuschicken; der Server legt sie an.

# Web: Angular-Build + .htaccess + index.php (+ robots.txt)
Copy-Item -Recurse -Force (Join-Path $distDir '*') $stageWeb
Copy-Item -Force (Join-Path $deployDir 'htaccess.spa.txt') (Join-Path $stageWeb '.htaccess')
$robots = Join-Path $backendSrc 'public\robots.txt'
if (Test-Path $robots) { Copy-Item -Force $robots $stageWeb }
if ($layout -eq 'split') {
    $tpl = Get-Content -Raw -Encoding UTF8 (Join-Path $deployDir 'index.split.php.tpl')
    $tpl = $tpl.Replace('{{APP_DIR_FROM_WEB_DIR}}', ([string]$cfg.appDirFromWebDir).TrimEnd('/'))
    [IO.File]::WriteAllText((Join-Path $stageWeb 'index.php'), ($tpl -replace "`r`n", "`n"), (New-Object Text.UTF8Encoding($false)))
} else {
    Copy-Item -Force (Join-Path $backendSrc 'public\index.php') $stageWeb
}

$zipBackend = Join-Path $work 'bustotal-backend.zip'
$zipWeb = Join-Path $work 'bustotal-web.zip'
# Eigene ZIP-Erzeugung: Windows PowerShell 5.1 schreibt sonst Backslashes in die Pfade, die auf Linux als Teil des Dateinamens gelten.
function New-Zip([string]$sourceDir, [string]$zipPath) {
    $sourceDir = (Resolve-Path $sourceDir).Path.TrimEnd('\')
    $zip = [IO.Compression.ZipFile]::Open($zipPath, [IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem -LiteralPath $sourceDir -Recurse -Force -File | ForEach-Object {
            $relative = $_.FullName.Substring($sourceDir.Length + 1).Replace('\', '/')
            [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relative, [IO.Compression.CompressionLevel]::Optimal)
        }
    } finally { $zip.Dispose() }
}
New-Zip $stageBackend $zipBackend
New-Zip $stageWeb $zipWeb
Write-Host ("Backend: {0:N1} MB, Web: {1:N1} MB" -f ((Get-Item $zipBackend).Length / 1MB), ((Get-Item $zipWeb).Length / 1MB))

if ($DryRun) {
    Write-Host ""
    Write-Host "Probelauf beendet. Pakete liegen in: $work" -ForegroundColor Yellow
    exit 0
}

# --- 3. Hochladen und auf dem Server ausfuehren --------------------------------------------------
Step "Hochladen nach $remote (ggf. Passwort eingeben)"
& scp -P $sshPort -q $zipBackend $zipWeb "${remote}:${tempDir}/"
Check-Exit 'Hochladen (scp)'

$script = @'
set -euo pipefail
trap 'echo "FEHLER in Zeile $LINENO. Der Wartungsmodus bleibt AN. Fehler beheben und erneut ausfuehren." >&2' ERR
cd "$HOME"
APP='@@APP@@'; WEB='@@WEB@@'; TMP='@@TMP@@'; PHP='@@PHP@@'; COMPOSER='@@COMPOSER@@'

[ -f "$APP/.env" ] || { echo "FEHLER: $APP/.env fehlt. Beim ersten Mal die .env selbst anlegen (docs/DEPLOY.md)." >&2; exit 1; }
mkdir -p "$APP" "$WEB"

extract() {
  $PHP -r '$z = new ZipArchive; if ($z->open($argv[1]) !== true) { fwrite(STDERR, "ZIP nicht lesbar\n"); exit(1); } $z->extractTo($argv[2]); $z->close();' "$1" "$2"
}

echo "-- Wartungsmodus an"
(cd "$APP" && $PHP artisan down --retry=60 >/dev/null 2>&1) || true

echo "-- Code ersetzen"
for d in app routes database config resources lang; do rm -rf "$APP/$d"; done
extract "$TMP/bustotal-backend.zip" "$APP"
mkdir -p "$APP/storage/framework/cache" "$APP/storage/framework/sessions" "$APP/storage/framework/views" "$APP/storage/logs" "$APP/bootstrap/cache"

echo "-- Composer"
cd "$APP"
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

echo "-- Zwischenspeicher der Konfiguration verwerfen"
rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/events.php

echo "-- Zentrale Datenbank"
$PHP artisan migrate --database=central --path=database/migrations/central --force

echo "-- Datenbanken der Firmen"
$PHP artisan tenant:migrate

echo "-- Webdateien ersetzen"
cd "$HOME"
rm -f "$WEB"/chunk-*.js "$WEB"/main-*.js "$WEB"/polyfills-*.js "$WEB"/styles-*.css
rm -rf "$WEB/help" "$WEB/media"
extract "$TMP/bustotal-web.zip" "$WEB"

echo "-- Zwischenspeicher aufbauen"
cd "$APP"
$PHP artisan config:cache
$PHP artisan route:cache

echo "-- Wartungsmodus aus"
$PHP artisan up
rm -f "$HOME/$TMP/bustotal-backend.zip" "$HOME/$TMP/bustotal-web.zip"
echo "-- Fertig"
'@
$script = $script.Replace('@@APP@@', $appDir).Replace('@@WEB@@', $webDir).Replace('@@TMP@@', $tempDir)
$script = $script.Replace('@@PHP@@', [string]$cfg.php).Replace('@@COMPOSER@@', [string]$cfg.composer)
$script = $script -replace "`r`n", "`n"

Step "Auf dem Server ausfuehren (ggf. Passwort eingeben)"
$script | & ssh -p $sshPort $remote 'bash -s'
Check-Exit 'Ausfuehren auf dem Server'

Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
Write-Host ""
Write-Host "Fertig. Pruefe die Seite im Browser (Strg+F5)." -ForegroundColor Green
