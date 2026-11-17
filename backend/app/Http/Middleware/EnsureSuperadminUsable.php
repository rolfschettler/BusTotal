<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Wie EnsureAccountUsable, aber für Betreiber-Konten: Sperre wirkt sofort, vorläufiges Passwort muss zuerst geändert werden. */
class EnsureSuperadminUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('superadmin');

        if ($admin && ! $admin->active) {
            $admin->tokens()->delete();
            abort(401, 'Das Konto ist gesperrt.');
        }

        if ($admin?->must_change_password && ! $request->is('api/superadmin/me', 'api/superadmin/me/password', 'api/superadmin/logout')) {
            return response()->json([
                'message' => 'Bitte vergeben Sie zuerst ein neues Passwort.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
