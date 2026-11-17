<?php

namespace App\Http\Requests;

use App\Enums\VehicleEquipment;
use App\Enums\VehicleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Eingaberegeln für Fahrzeuge anlegen und ändern. */
class VehicleRequest extends FormRequest
{
    // Wer darf überhaupt? Das regelt schon die Route (role:...).
    public function authorize(): bool
    {
        return true;
    }

    /** Kennzeichen immer gleich schreiben (Großbuchstaben, Leerraum zusammengefasst), damit "m-ab 123" kein zweites Fahrzeug ergibt. */
    protected function prepareForValidation(): void
    {
        $plate = $this->input('license_plate');
        if (is_string($plate)) {
            $this->merge(['license_plate' => mb_strtoupper(trim(preg_replace('/\s+/', ' ', $plate)))]);
        }
        $vin = $this->input('vin');
        if (is_string($vin)) {
            $this->merge(['vin' => mb_strtoupper(trim($vin))]);
        }
    }

    public function rules(): array
    {
        $ignore = $this->route('vehicle')?->id;

        return [
            'fleet_number' => ['required', 'string', 'max:30', Rule::unique('vehicles', 'fleet_number')->ignore($ignore)],
            'license_plate' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'license_plate')->ignore($ignore)],
            'type' => ['required', Rule::enum(VehicleType::class)],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year_built' => ['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)],
            // Fahrgestellnummer: 17 Zeichen, ohne I, O und Q (ISO 3779)
            'vin' => ['nullable', 'string', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/'],
            'seats' => ['required', 'integer', 'min:1', 'max:200'],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => ['string', 'distinct', Rule::enum(VehicleEquipment::class)],
            'length_m' => ['nullable', 'numeric', 'min:1', 'max:30'],
            'width_m' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'height_m' => ['nullable', 'numeric', 'min:1', 'max:6'],
            'gross_weight_kg' => ['nullable', 'integer', 'min:500', 'max:60000'],
            'cost_per_km' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'hu_due_date' => ['nullable', 'date_format:Y-m-d'],
            'sp_due_date' => ['nullable', 'date_format:Y-m-d'],
            'tachograph_due_date' => ['nullable', 'date_format:Y-m-d'],
            'service_due_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'fleet_number.unique' => 'Diese Fuhrparknummer gibt es schon.',
            'license_plate.unique' => 'Dieses Kennzeichen gibt es schon.',
            'vin.regex' => 'Die Fahrgestellnummer hat 17 Zeichen (Buchstaben ohne I, O, Q und Ziffern).',
        ];
    }
}
