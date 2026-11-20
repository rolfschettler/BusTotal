<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\VehicleType;
use App\Models\PricingSetting;
use App\Models\PricingVehicleRate;
use App\Services\QuoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Preisfindung: Preisparameter der Firma und Angebot aus einer geplanten Tour. */
class PricingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload(PricingSetting::current()));
    }

    /** Standardpreise, dazu die Preise je Fahrzeugtyp (Schlüssel = Typ, leeres Feld = null) und die Typen für die Oberfläche. */
    private function payload(PricingSetting $settings): array
    {
        $own = PricingVehicleRate::all()->keyBy(fn ($r) => $r->vehicle_type->value);

        return $settings->toArray() + [
            'vehicle_types' => array_map(fn (VehicleType $t) => ['value' => $t->value, 'label' => $t->label()], VehicleType::cases()),
            'vehicle_rates' => collect(VehicleType::cases())->mapWithKeys(fn (VehicleType $t) => [
                $t->value => collect(PricingVehicleRate::FIELDS)->mapWithKeys(fn ($f) => [$f => $own->get($t->value)?->{$f}])->all(),
            ])->all(),
        ];
    }

    public function update(Request $request): JsonResponse
    {
        $typeRules = [];
        foreach (VehicleType::cases() as $t) {
            foreach (PricingVehicleRate::FIELDS as $field) {
                $typeRules["vehicle_rates.{$t->value}.{$field}"] = ['nullable', 'numeric', 'min:0', 'max:99999'];
            }
        }

        $data = $request->validate($typeRules + [
            'price_per_km' => ['required', 'numeric', 'min:0', 'max:9999'],
            'empty_km_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'day_rate' => ['required', 'numeric', 'min:0', 'max:99999'],
            'overnight_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'minimum_price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
        ]);

        $settings = PricingSetting::current();
        $typeRates = $data['vehicle_rates'] ?? null;
        unset($data['vehicle_rates']);

        DB::transaction(function () use ($settings, $data, $typeRates) {
            $settings->update($data);

            // Nicht mitgeschickte Typen bleiben unverändert. Ein Typ ganz ohne Wert verliert seine Zeile (= Standardpreise).
            foreach ($typeRates ?? [] as $type => $values) {
                $values = array_intersect_key($values ?? [], array_flip(PricingVehicleRate::FIELDS));
                $values = array_map(fn ($v) => $v === '' ? null : $v, $values);
                $values += array_fill_keys(PricingVehicleRate::FIELDS, null);

                if (count(array_filter($values, fn ($v) => $v !== null)) === 0) {
                    PricingVehicleRate::where('vehicle_type', $type)->get()->each->delete();
                } else {
                    PricingVehicleRate::updateOrCreate(['vehicle_type' => $type], $values);
                }
            }
        });

        return response()->json($this->payload($settings->fresh()));
    }

    /**
     * Angebot (Entwurf) aus den Kennzahlen einer Tour. Der Kunde wird im Beleg gewählt. Es kommen nur Zahlen an,
     * keine Route und keine Orte (siehe docs/STRECKENPLANUNG.md zur Lizenz der Routendaten).
     */
    public function quoteFromTour(Request $request, QuoteService $quotes): JsonResponse
    {
        $data = $request->validate([
            'loaded_km' => ['required', 'numeric', 'min:0', 'max:50000'],
            'empty_km' => ['required', 'numeric', 'min:0', 'max:50000'],
            'drive_s' => ['required', 'integer', 'min:0', 'max:3000000'],
            'days' => ['required', 'integer', 'min:1', 'max:60'],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'label' => ['nullable', 'string', 'max:240'],
            'start_at' => ['nullable', 'date'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'vehicle_id' => ['nullable', 'uuid', 'exists:vehicles,id'],
        ]);

        $document = $quotes->draftFromTour($data, $data['customer_id'] ?? null);

        return response()->json($document, 201);
    }
}
