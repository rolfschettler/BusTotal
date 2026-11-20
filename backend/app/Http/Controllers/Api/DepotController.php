<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\RespondsToRoutingFailure;
use App\Http\Controllers\Controller;
use App\Models\Depot;
use App\Routing\RoutingException;
use App\Services\PlaceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Betriebshöfe der Firma. Lesen dürfen Admin, Disponent und Buchhaltung (die Streckenplanung braucht sie), ändern nur der Admin.
 * Den Ort (Koordinaten) sucht der Server beim Speichern, und nur wenn die Adresse neu ist (siehe PlaceResolver).
 */
class DepotController extends Controller
{
    use RespondsToRoutingFailure;

    public function index(): JsonResponse
    {
        return response()->json(Depot::orderByDesc('is_default')->orderBy('name')->get());
    }

    public function store(Request $request, PlaceResolver $places): JsonResponse
    {
        return $this->save(new Depot, $this->validated($request), $places, 201);
    }

    public function update(Request $request, Depot $depot, PlaceResolver $places): JsonResponse
    {
        return $this->save($depot, $this->validated($request), $places, 200);
    }

    public function destroy(Depot $depot): JsonResponse
    {
        DB::transaction(function () use ($depot) {
            $depot->delete();
            // War es der Standard, übernimmt der nächste (nach Name) diese Rolle.
            if ($depot->is_default) {
                Depot::orderBy('name')->first()?->update(['is_default' => true]);
            }
        });

        return response()->json(null, 204);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    private function save(Depot $depot, array $data, PlaceResolver $places, int $status): JsonResponse
    {
        $depot->fill($data);

        try {
            $places->assign($depot);
        } catch (RoutingException $e) {
            return $this->failure($e);
        }

        DB::transaction(function () use ($depot) {
            // Die anderen Standard-Betriebshöfe (der eigene zählt nicht mit)
            $otherDefaults = fn () => Depot::where('is_default', true)->when($depot->exists, fn ($q) => $q->whereKeyNot($depot->getKey()));

            // Der erste Betriebshof ist der Standard; wer einen anderen zum Standard macht, nimmt es dem bisherigen.
            if (! $otherDefaults()->exists()) {
                $depot->is_default = true;
            }
            if ($depot->is_default) {
                $otherDefaults()->update(['is_default' => false]);
            }
            $depot->save();
        });

        return response()->json($depot->fresh(), $status);
    }
}
