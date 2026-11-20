<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PricingSetting;
use App\Services\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Preisfindung: Preisparameter der Firma und Angebot aus einer geplanten Tour. */
class PricingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(PricingSetting::current());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'price_per_km' => ['required', 'numeric', 'min:0', 'max:9999'],
            'empty_km_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'day_rate' => ['required', 'numeric', 'min:0', 'max:99999'],
            'overnight_rate' => ['required', 'numeric', 'min:0', 'max:9999'],
            'minimum_price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'tax_rate' => ['required', 'numeric', 'between:0,100'],
        ]);

        $settings = PricingSetting::current();
        $settings->update($data);

        return response()->json($settings->fresh());
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
        ]);

        $document = $quotes->draftFromTour($data, $data['customer_id'] ?? null);

        return response()->json($document, 201);
    }
}
