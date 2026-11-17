<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Nutzung in Routen: ->middleware('role:admin,buchhaltung') */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        if (! $request->user()?->hasRole(...$allowed)) {
            abort(403, 'Keine Berechtigung.');
        }

        return $next($request);
    }
}
