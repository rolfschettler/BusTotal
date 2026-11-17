<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mandant bestimmen, bevor irgendetwas anderes (Anmeldung, Datenbankzugriff) läuft.
        $middleware->api(prepend: [\App\Http\Middleware\ResolveTenant::class]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'account.ok' => \App\Http\Middleware\EnsureAccountUsable::class,
            'superadmin.ok' => \App\Http\Middleware\EnsureSuperadminUsable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Deutsche Fehlermeldungen für die API. Laravels Standardtexte sind englisch, und bei einem
        // nicht gefundenen Eintrag nennen sie sogar den internen Klassennamen des Datenbankmodells.
        $api = fn (Request $request) => $request->is('api/*');

        $exceptions->render(fn (AuthenticationException $e, Request $request) => $api($request)
            ? response()->json(['message' => 'Sie sind nicht angemeldet.'], 401) : null);

        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $api($request)
            ? response()->json(['message' => 'Der Eintrag wurde nicht gefunden.'], 404) : null);

        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $request) => $api($request)
            ? response()->json(['message' => 'Diese Anfrage ist hier nicht erlaubt.'], 405, $e->getHeaders()) : null);

        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => $api($request)
            ? response()->json(['message' => 'Zu viele Anfragen. Bitte warten Sie einen Moment.'], 429, $e->getHeaders()) : null);

        // Alle übrigen Serverfehler (500 und höher) ohne technische Einzelheiten. Im Entwicklungsmodus
        // (APP_DEBUG=true) bleibt die ausführliche Meldung zur Fehlersuche erhalten.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($request->is('api/*') && $response->getStatusCode() >= 500 && ! config('app.debug')) {
                return response()->json(
                    ['message' => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es später erneut.'],
                    $response->getStatusCode(),
                );
            }

            return $response;
        });
    })->create();
