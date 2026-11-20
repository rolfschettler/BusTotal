<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Eingaberegeln für die Tourberechnung (Halte, Betriebshof, Fahrzeug, Abfahrtszeit). */
class RouteCalculateRequest extends FormRequest
{
    // Wer darf überhaupt? Das regelt schon die Route (role:...).
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stops' => ['required', 'array', 'min:1', 'max:20'],
            'stops.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'stops.*.lon' => ['required', 'numeric', 'between:-180,180'],
            'stops.*.label' => ['nullable', 'string', 'max:255'],
            // Aufenthalt am Halt in Minuten (bis 24 Stunden); driver_free: Der Fahrer hat in dieser Zeit frei (zählt als Pause/Ruhezeit).
            'stops.*.dwell_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'stops.*.driver_free' => ['nullable', 'boolean'],
            // movable: Zwischenhalt, dessen Platz die Optimierung bestimmen darf (Von und Nach bleiben stehen).
            'stops.*.movable' => ['nullable', 'boolean'],
            'optimize' => ['nullable', 'boolean'],
            'depot' => ['nullable', 'array'],
            'depot.lat' => ['required_with:depot', 'numeric', 'between:-90,90'],
            'depot.lon' => ['required_with:depot', 'numeric', 'between:-180,180'],
            'depot.label' => ['nullable', 'string', 'max:255'],
            'return_to_depot' => ['nullable', 'boolean'],
            'vehicle_id' => ['nullable', 'uuid', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'start_at' => ['nullable', 'date'],
            'extended_days' => ['nullable', 'integer', 'min:0', 'max:2'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $points = count((array) $this->input('stops')) + ($this->input('depot') ? ($this->boolean('return_to_depot', true) ? 2 : 1) : 0);
            if ($points < 2 && ! $v->errors()->has('stops')) {
                $v->errors()->add('stops', 'Für eine Route braucht es mindestens zwei Orte (mit Betriebshof reicht ein Halt).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'stops.max' => 'Eine Tour darf höchstens 20 Halte haben.',
            'vehicle_id.exists' => 'Dieses Fahrzeug gibt es nicht.',
        ];
    }
}
