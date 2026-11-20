<?php

// Einstiegsdatei fuer Server, auf denen nur ein Web-Ordner bedient wird (z. B. Mittwald Agentur-Server).
// Der Laravel-Code liegt ausserhalb des Web-Ordners; dieser Pfad zeigt darauf. Wird vom Deploy-Skript erzeugt.

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = __DIR__.'/{{APP_DIR_FROM_WEB_DIR}}';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
