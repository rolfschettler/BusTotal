<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Subdomain -> Mandant -> Datenbank. Läuft als ERSTE Middleware der API, noch vor der Anmeldung,
 * damit Benutzer und Token aus der richtigen Datenbank gelesen werden. Ein Token einer anderen
 * Firma wird dadurch gar nicht erst gefunden.
 *
 * Die Betreiber-Adresse (admin.<basis>) hat keine Firma: Dort gibt es nur /api/superadmin/*, und
 * diese Schnittstelle gibt es umgekehrt nur dort.
 */
class ResolveTenant
{
    public function __construct(private TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $superPath = $request->is('api/superadmin/*');

        if (! $this->tenants->enabled()) {
            return $superPath ? $this->unknown() : $next($request);
        }

        $subdomain = $this->tenants->subdomainFromHost($request->getHost());
        $isSuperHost = $subdomain !== null && $subdomain === config('tenancy.superadmin_subdomain');

        if ($isSuperHost || $superPath) {
            if (! ($isSuperHost && $superPath)) {
                return $this->unknown();
            }
            $this->tenants->useCentral();

            return $next($request);
        }

        // Nur lokal: Host ohne Unterdomäne (http://localhost:4200) bedient einen festen Entwicklungs-Mandanten.
        if ($subdomain === null && app()->environment('local')) {
            $subdomain = config('tenancy.local_fallback');
        }

        $tenant = $subdomain ? Tenant::where('subdomain', $subdomain)->where('is_active', true)->first() : null;

        // Absichtlich dieselbe Antwort für "gibt es nicht" und "gesperrt": nichts verraten.
        if ($tenant === null) {
            return $this->unknown();
        }

        $this->tenants->activate($tenant);

        return $next($request);
    }

    private function unknown(): Response
    {
        return response()->json(['message' => 'Diese Adresse gehört zu keiner Firma.'], 404);
    }
}
