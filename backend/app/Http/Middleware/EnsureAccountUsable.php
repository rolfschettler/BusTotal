<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Läuft nach der Anmeldung bei jeder Anfrage:
 *  - Ein gesperrter Benutzer verliert sofort den Zugang, auch mit noch gültigem Token.
 *  - Wer noch das vorläufige Passwort hat, darf nur sein Passwort ändern (oder sich abmelden).
 */
class EnsureAccountUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            $user->tokens()->delete();
            abort(401, 'Das Konto ist gesperrt.');
        }

        if ($user?->must_change_password && ! $request->is('api/me', 'api/me/password', 'api/logout')) {
            return response()->json([
                'message' => 'Bitte vergeben Sie zuerst ein neues Passwort.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
