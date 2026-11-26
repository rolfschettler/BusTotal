<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RespondsToRoutingFailure;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ServiceCredential;
use App\Routing\OpenRouteService;
use App\Routing\RoutingException;
use App\Services\ServiceKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Einstellungen › Dienste (nur Administratoren): Die Firma trägt den eigenen Zugangsschlüssel zu externen Diensten ein.
 * Ohne eigenen Schlüssel gilt der allgemeine des Servers als Rückfall (ServiceKeys). Der Schlüssel wird nie zurückgegeben,
 * nur seine letzten vier Zeichen, und nie protokolliert.
 */
class ServiceSettingsController extends Controller
{
    use RespondsToRoutingFailure;

    public function __construct(private readonly ServiceKeys $keys) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => array_map(fn (string $service) => $this->state($service), ServiceKeys::SERVICES)]);
    }

    public function update(Request $request, string $service): JsonResponse
    {
        $this->assertService($service);
        $data = $request->validate(['api_key' => ['required', 'string', 'min:8', 'max:500']], [
            'api_key.required' => 'Bitte den Zugangsschlüssel eintragen.',
            'api_key.min' => 'Der Zugangsschlüssel ist zu kurz.',
        ]);

        $this->keys->save($service, $data['api_key']);
        app(OpenRouteService::class)->resetQuota();
        AuditLog::record('service_key_set', null, null, ['service' => $service]);

        return response()->json($this->state($service));
    }

    /** Eigenen Schlüssel entfernen: Es gilt wieder der allgemeine Schlüssel des Servers, sofern es einen gibt. */
    public function destroy(string $service): JsonResponse
    {
        $this->assertService($service);
        $this->keys->forget($service);
        app(OpenRouteService::class)->resetQuota();
        AuditLog::record('service_key_removed', null, null, ['service' => $service]);

        return response()->json($this->state($service));
    }

    /**
     * „Verbindung prüfen“: eine kleine Ortssuche (ohne Zwischenspeicher) mit dem Schlüssel, der gerade gilt (kostet eine Anfrage). Das Ergebnis kommt
     * immer mit Status 200 zurück, damit die Oberfläche Erfolg und Ursache gleich behandelt.
     */
    public function test(string $service): JsonResponse
    {
        $this->assertService($service);

        try {
            app(OpenRouteService::class)->ping();
        } catch (RoutingException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage(), 'reason' => $e->kind] + $this->state($service));
        }

        return response()->json(['ok' => true, 'message' => 'Die Verbindung zum Dienst funktioniert.'] + $this->state($service));
    }

    private function assertService(string $service): void
    {
        abort_unless(in_array($service, ServiceKeys::SERVICES, true), 404);
    }

    /** @return array<string, mixed> */
    private function state(string $service): array
    {
        $resolved = $this->keys->resolve($service);
        $own = ServiceCredential::where('service', $service)->value('api_key');

        return [
            'service' => $service,
            // own: eigener Schlüssel der Firma, shared: allgemeiner Schlüssel des Servers (Rückfall), none: keiner
            'source' => $resolved['source'],
            'own_hint' => is_string($own) && $own !== '' ? $this->keys->hint($own) : null,
            'shared_available' => $this->keys->hasShared($service),
        ];
    }
}
