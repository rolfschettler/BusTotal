<?php

namespace App\Http\Controllers\Api;

use App\Enums\AbsenceType;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverAbsence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Abwesenheiten (Urlaub, Krankheit, Schulung) eines Fahrers. */
class DriverAbsenceController extends Controller
{
    public function store(Request $request, Driver $driver): JsonResponse
    {
        $data = $this->data($request, $driver, null);

        return response()->json($driver->absences()->create($data)->fresh(), 201);
    }

    public function update(Request $request, Driver $driver, DriverAbsence $absence): JsonResponse
    {
        abort_unless($absence->driver_id === $driver->id, 404);
        $absence->update($this->data($request, $driver, $absence));

        return response()->json($absence->fresh());
    }

    public function destroy(Driver $driver, DriverAbsence $absence): JsonResponse
    {
        abort_unless($absence->driver_id === $driver->id, 404);
        $absence->delete();

        return response()->json(['message' => 'Abwesenheit gelöscht.']);
    }

    private function data(Request $request, Driver $driver, ?DriverAbsence $current): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(AbsenceType::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Zwei Abwesenheiten desselben Fahrers dürfen sich nicht überschneiden (Enddatum zählt mit).
        $overlap = $driver->absences()
            ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'In diesem Zeitraum gibt es schon eine Abwesenheit.']);
        }

        return $data;
    }
}
