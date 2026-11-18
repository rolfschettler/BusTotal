<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RouteCalculateRequest;
use App\Models\Vehicle;
use App\Routing\GeocodingProvider;
use App\Routing\RoutingException;
use App\Services\TourCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Streckenplanung: Adresssuche und Tourberechnung. Beides ruft einen externen Dienst auf, der Kontingente hat;
 * deshalb sind beide Wege begrenzt und nur für Admin und Disponent.
 *
 * Fehler des Dienstes kommen mit deutscher Meldung zurück, bewusst NICHT als 5xx: Das Frontend deutet 502/503/504 als
 * "Server nicht erreichbar" (siehe httpErrorText). Hier ist unser Server erreichbar, der Fremddienst nicht.
 *  - 422: am eingegebenen Ort liegt es (keine Straße in der Nähe, Route zu lang)
 *  - 424: der Dienst selbst (nicht eingerichtet, Kontingent erreicht, antwortet nicht)
 */
class RouteController extends Controller
{
    /** Orte und Adressen suchen (für die Eingabe der Halte). */
    public function geocode(Request $request, GeocodingProvider $geocoder): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            $hits = $geocoder->search($data['q'], (int) ($data['limit'] ?? 5));
        } catch (RoutingException $e) {
            return $this->failure($e);
        }

        return response()->json(['data' => array_map(fn ($h) => $h->toArray(), $hits)]);
    }

    /** Tour berechnen: Strecke, Fahrzeit, Ablauf mit Pausen und Ruhezeiten. Speichert nichts. */
    public function calculate(RouteCalculateRequest $request, TourCalculator $calculator): JsonResponse
    {
        $data = $request->validated();
        $vehicle = isset($data['vehicle_id']) ? Vehicle::find($data['vehicle_id']) : null;

        try {
            $result = $calculator->calculate(
                $data['stops'],
                $data['depot'] ?? null,
                (bool) ($data['return_to_depot'] ?? true),
                $vehicle,
                isset($data['start_at']) ? CarbonImmutable::parse($data['start_at']) : null,
                (int) ($data['extended_days'] ?? 0),
            );
        } catch (RoutingException $e) {
            return $this->failure($e);
        }

        return response()->json($result);
    }

    private function failure(RoutingException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'reason' => $e->kind], $e->isInputProblem() ? 422 : 424);
    }
}
