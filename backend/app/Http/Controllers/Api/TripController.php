<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripKind;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Models\JourneySection;
use App\Models\Trip;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tour-Archiv: gespeicherte Touren suchen, lesen, speichern, ändern und löschen. Lesen dürfen Admin, Disponent und Buchhaltung,
 * Speichern, Ändern und Löschen nur Admin und Disponent (siehe routes/api.php).
 */
class TripController extends Controller
{
    /** Liste ohne Zeitplan und Linienzug; die Suche trifft Titel und Orte am Wortanfang. */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $trips = Trip::query()
            ->with('stops')
            // In wie vielen Reisen die Tour verwendet wird (solange sie verwendet wird, lässt sie sich nicht löschen)
            ->withCount('sections')
            ->when($q !== '', function ($query) use ($q) {
                $like = $this->escapeLike($q);
                $query->where(function ($w) use ($like) {
                    $w->where('title', 'like', $like.'%')->orWhere('title', 'like', '% '.$like.'%')
                        ->orWhereHas('stops', fn ($s) => $s->where('label', 'like', $like.'%')->orWhere('label', 'like', '% '.$like.'%'));
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return response()->json($trips);
    }

    public function show(Trip $trip): JsonResponse
    {
        return response()->json($trip->load('stops')->makeVisible(['route', 'geometry']));
    }

    public function store(Request $request, TripService $service): JsonResponse
    {
        $trip = $service->save(new Trip, $request->validate($this->rules()));

        return response()->json($trip->makeVisible(['route', 'geometry']), 201);
    }

    public function update(Request $request, Trip $trip, TripService $service): JsonResponse
    {
        $trip = $service->save($trip, $request->validate($this->rules()));

        return response()->json($trip->makeVisible(['route', 'geometry']));
    }

    public function destroy(Trip $trip): JsonResponse
    {
        $used = JourneySection::where('trip_id', $trip->id)->count();
        if ($used > 0) {
            return response()->json(['message' => $used === 1
                ? 'Diese Tour wird in einer Reise verwendet und kann nicht gelöscht werden.'
                : "Diese Tour wird in $used Reisen verwendet und kann nicht gelöscht werden."], 422);
        }

        $trip->delete();

        return response()->json(null, 204);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'trip_kind' => ['required', Rule::enum(TripKind::class)],
            'vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'vehicle_count' => ['required', 'integer', 'min:1', 'max:99'],
            'depot_id' => ['nullable', 'uuid', 'exists:depots,id'],
            'return_to_depot' => ['boolean'],
            'round_trip' => ['boolean'],
            'loaded_km' => ['required', 'numeric', 'min:0', 'max:99999'],
            'empty_km' => ['required', 'numeric', 'min:0', 'max:99999'],
            'drive_s' => ['required', 'integer', 'min:0', 'max:99999999'],
            'elapsed_s' => ['required', 'integer', 'min:0', 'max:99999999'],
            'days' => ['required', 'integer', 'min:1', 'max:366'],
            'route' => ['nullable', 'array'],
            'geometry' => ['nullable', 'array', 'max:200000'],
            'stops' => ['required', 'array', 'min:2', 'max:50'],
            'stops.*.label' => ['required', 'string', 'max:255'],
            'stops.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'stops.*.lon' => ['required', 'numeric', 'between:-180,180'],
            'stops.*.dwell_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'stops.*.driver_free' => ['nullable', 'boolean'],
        ];
    }

    private function escapeLike(string $text): string
    {
        return addcslashes($text, '\\%_');
    }
}
