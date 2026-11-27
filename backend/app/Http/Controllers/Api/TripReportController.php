<?php

namespace App\Http\Controllers\Api;

use App\Enums\CostCategory;
use App\Http\Controllers\Controller;
use App\Models\PricingSetting;
use App\Models\TripOrder;
use App\Services\TripReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rückerfassung eines Fahrtauftrags: anzeigen, speichern, erledigen und „erledigt“ zurücknehmen. */
class TripReportController extends Controller
{
    public function __construct(private readonly TripReportService $reports) {}

    public function show(TripOrder $tripOrder): JsonResponse
    {
        return response()->json($this->payload($tripOrder));
    }

    public function update(Request $request, TripOrder $tripOrder): JsonResponse
    {
        $km = ['nullable', 'integer', 'min:0', 'max:9999999'];
        $money = ['nullable', 'numeric', 'min:0', 'max:999999'];
        $data = $request->validate([
            'actual_passengers' => ['nullable', 'string', 'max:40'],
            'report_notes' => ['nullable', 'string', 'max:4000'],
            'times' => ['present', 'array', 'max:60'],
            'times.*.event' => ['required', 'string', 'max:100'],
            'times.*.date' => ['nullable', 'date_format:Y-m-d'],
            'times.*.place' => ['nullable', 'string', 'max:150'],
            'times.*.time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'times.*.km' => $km,
            'times.*.leg' => ['nullable', 'in:betriebshof,befoerderung,leerfahrt'],
            'borders' => ['present', 'array', 'max:20'],
            'borders.*.country' => ['required', 'string', 'max:60'],
            'borders.*.km_in' => $km,
            'borders.*.km_out' => $km,
            'borders.*.source' => ['required', 'in:fahrer,tachograph,navigation'],
            'extras' => ['present', 'array', 'max:40'],
            'extras.*.title' => ['required', 'string', 'max:150'],
            'extras.*.quantity' => ['nullable', 'string', 'max:40'],
            'extras.*.place' => ['nullable', 'string', 'max:100'],
            'extras.*.amount' => $money,
            'extras.*.payment' => ['required', 'in:bar,rechnung'],
            'extras.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'costs' => ['present', 'array', 'max:60'],
            'costs.*.category' => ['required', Rule::enum(CostCategory::class)],
            'costs.*.amount' => $money,
            'costs.*.receipt_no' => ['nullable', 'string', 'max:60'],
            'costs.*.travel_service' => ['required', 'boolean'],
            'costs.*.charge_customer' => ['required', 'boolean'],
        ], [
            'times.*.event.required' => 'Bitte jede Zeile bei „Zeiten und Kilometer“ benennen.',
            'times.*.time.regex' => 'Bitte die Uhrzeit als HH:MM angeben, zum Beispiel 06:30.',
            'times.*.km.integer' => 'Kilometerstände bitte als ganze Zahl eingeben.',
            'borders.*.country.required' => 'Bitte bei jedem Grenzübertritt das Land angeben.',
            'borders.*.km_in.integer' => 'Kilometerstände bitte als ganze Zahl eingeben.',
            'borders.*.km_out.integer' => 'Kilometerstände bitte als ganze Zahl eingeben.',
            'extras.*.title.required' => 'Bitte bei jeder Zusatzleistung angeben, was es war.',
            'extras.*.amount.numeric' => 'Beträge bitte als Zahl eingeben.',
            'costs.*.amount.numeric' => 'Beträge bitte als Zahl eingeben.',
        ]);
        // Die geprüften Daten können die Zeilen in anderer Reihenfolge liefern: nach der Zeilennummer ordnen
        foreach (['times', 'borders', 'extras', 'costs'] as $list) {
            ksort($data[$list]);
        }

        return response()->json($this->payload($this->reports->save($tripOrder, $data)));
    }

    public function complete(TripOrder $tripOrder): JsonResponse
    {
        return response()->json($this->payload($this->reports->complete($tripOrder)));
    }

    public function reopen(Request $request, TripOrder $tripOrder): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => 'Bitte eine Begründung angeben.',
            'reason.max' => 'Die Begründung darf höchstens 255 Zeichen lang sein.',
        ]);

        return response()->json($this->payload($this->reports->reopen($tripOrder, $data['reason'])));
    }

    /** Der Fahrtauftrag in der Kurzform, seine Rückerfassung und die Auswahllisten. */
    private function payload(TripOrder $order): array
    {
        $order->load(['customer', 'vehicle', 'driver', 'driver2', 'section']);
        $pricing = PricingSetting::current();

        return [
            'order' => [
                'id' => $order->id, 'number' => $order->number, 'title' => $order->title, 'bus_no' => $order->bus_no,
                'status' => $order->status->value, 'status_label' => $order->isCancelled() ? 'Abgesagt' : $order->status->label(),
                'cancelled_at' => $order->cancelled_at?->toIso8601String(), 'billing_status' => $order->billing_status,
                'date_from' => $order->date_from?->toDateString(), 'date_to' => $order->date_to?->toDateString(),
                'journey_id' => $order->section?->journey_id, 'customer' => $order->customer?->display_name,
                'vehicle' => $order->vehicle?->display_name, 'external_company' => $order->external_company,
                'drivers' => array_values(array_filter([$order->driver?->display_name, $order->driver2?->display_name])),
                'passengers' => $order->passengers,
            ],
            'report' => $this->reports->report($order),
            'choices' => [
                'categories' => CostCategory::options(),
                // Vorbelegung des Steuersatzes einer Zusatzleistung: der Satz der Fahrtart
                'default_tax_rate' => (float) $pricing->taxRateFor($order->trip_kind),
                'tax_rates' => collect([$pricing->tax_rate, $pricing->tax_rate_line, $pricing->tax_rate_school, 0])
                    ->map(fn ($r) => (float) $r)->unique()->sortDesc()->values()->all(),
            ],
        ];
    }
}
