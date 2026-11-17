<?php

namespace App\Http\Controllers\Api;

use App\Enums\VehicleEquipment;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\VehicleRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    /** Die früheste der vier Fristen. Leere Fristen zählen nicht (LEAST liefert sonst NULL, sobald eine Spalte leer ist). */
    private const NEXT_DEADLINE = "NULLIF(LEAST(COALESCE(hu_due_date, '9999-12-31'), COALESCE(sp_due_date, '9999-12-31'), COALESCE(tachograph_due_date, '9999-12-31'), COALESCE(service_due_date, '9999-12-31')), '9999-12-31')";

    /**
     * Erlaubte Sortierungen (siehe CustomerController::SORTS): Die Anfrage wählt nur einen Eintrag der festen Liste.
     */
    private const SORTS = [
        'fleet_number' => ['fleet_number'],
        'plate' => ['license_plate'],
        'type' => ['type'],
        'model' => ['manufacturer', 'model'],
        'seats' => ['seats'],
        'deadline' => [self::NEXT_DEADLINE],
    ];

    /** Liste mit Suche, Filter, Sortierung und Seiten (je 20 Fahrzeuge). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(VehicleType::class)],
            'deadline' => ['nullable', Rule::in(['overdue', 'soon'])],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(array_keys(self::SORTS))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = Vehicle::query();

        if ($request->boolean('archived')) {
            $query->onlyTrashed();
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        // Fristfilter: mindestens eine Frist ist abgelaufen bzw. läuft in WARN_DAYS Tagen oder früher ab.
        if ($deadline = $request->query('deadline')) {
            $limit = $deadline === 'overdue' ? now()->toDateString() : now()->addDays(Vehicle::WARN_DAYS)->toDateString();
            $op = $deadline === 'overdue' ? '<' : '<=';
            $query->where(function ($w) use ($limit, $op) {
                foreach (array_keys(Vehicle::DEADLINES) as $col) {
                    $w->orWhere($col, $op, $limit);
                }
            });
        }

        if ($q = trim((string) $request->query('q'))) {
            // % und _ sind in LIKE Platzhalter, hier wörtlich gemeint.
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like) {
                foreach (['fleet_number', 'license_plate', 'manufacturer', 'model', 'vin'] as $col) {
                    $w->orWhere($col, 'like', $like);
                }
            });
        }

        // Standard: nach Fuhrparknummer. Leere Werte immer am Ende, bei Gleichstand entscheidet die Fuhrparknummer,
        // sonst könnte ein Fahrzeug beim Blättern auf zwei Seiten erscheinen oder fehlen.
        $sort = $request->query('sort') ?: 'fleet_number';
        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        foreach (self::SORTS[$sort] as $expression) {
            $query->orderByRaw("({$expression}) IS NULL")->orderByRaw("{$expression} {$dir}");
        }

        return response()->json($query->orderBy('fleet_number')->paginate(20)->withQueryString());
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        return response()->json(Vehicle::create($request->validated())->fresh(), 201);
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return response()->json($vehicle);
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($request->validated());

        return response()->json($vehicle->fresh());
    }

    /** "Löschen" bedeutet Archivieren: Das Fahrzeug bleibt für spätere Aufträge und Belege erhalten. */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $vehicle->delete();

        return response()->json(['message' => 'Fahrzeug archiviert.']);
    }

    public function restore(string $id): JsonResponse
    {
        $vehicle = Vehicle::onlyTrashed()->findOrFail($id);
        $vehicle->restore();

        return response()->json($vehicle->fresh());
    }

    /** Auswahllisten für das Formular. */
    public function options(): JsonResponse
    {
        return response()->json([
            'types' => collect(VehicleType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'equipment' => collect(VehicleEquipment::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'deadlines' => collect(Vehicle::DEADLINES)->map(fn ($label, $col) => ['field' => $col, 'label' => $label])->values(),
            'warn_days' => Vehicle::WARN_DAYS,
        ]);
    }
}
