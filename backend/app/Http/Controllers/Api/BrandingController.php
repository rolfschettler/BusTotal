<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Öffentlich (ohne Anmeldung): Name, Farbe und Logo der Firma. Die Login-Seite braucht das,
 * bevor jemand angemeldet ist. Es werden bewusst keine weiteren Firmendaten ausgeliefert.
 */
class BrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $c = CompanySetting::current();

        return response()->json([
            'name' => $c->name,
            'brand_color' => $c->brand_color,
            'has_logo' => $c->has_logo,
            // Ändert sich bei neuem Logo, damit der Browser nicht das alte aus dem Cache zeigt.
            'logo_version' => $c->has_logo ? $c->updated_at?->timestamp : null,
        ]);
    }

    public function logo(): Response
    {
        $path = CompanySetting::current()->logo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
