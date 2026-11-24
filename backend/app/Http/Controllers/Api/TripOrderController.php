<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TripOrder;
use App\Services\TripOrderPdf;
use App\Services\TripOrderService;
use App\Support\NameSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Fahrtaufträge: Liste, Anzeige, Disponieren (Fahrzeug, Fahrer, Eckdaten) und Ausgeben. Angelegt werden sie nicht hier, sondern mit der
 * festgeschriebenen Auftragsbestätigung (TripOrderService). Preise gibt es am Fahrtauftrag nicht.
 */
class TripOrderController extends Controller
{
    public function __construct(private readonly TripOrderService $orders) {}

    /** Nach Fahrtbeginn, ohne Datum zuletzt. Die Suche trifft Strecke, Nummer, Fremdunternehmer und den Kunden am Wortanfang. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TripOrderStatus::class)],
            'journey_id' => ['nullable', 'uuid'],
            'vehicle_id' => ['nullable', 'uuid'],
            'driver_id' => ['nullable', 'uuid'],
        ]);

        $query = TripOrder::query()
            ->select('trip_orders.*')
            ->with(['customer:id,customer_number,company_name,first_name,last_name,postal_code,city', 'vehicle', 'driver', 'driver2', 'section:id,journey_id'])
            ->leftJoin('customers as c', 'c.id', '=', 'trip_orders.customer_id');

        if (! empty($filters['status'])) {
            $query->where('trip_orders.status', $filters['status']);
        }
        if (! empty($filters['journey_id'])) {
            $query->whereIn('trip_orders.journey_section_id', fn ($q) => $q->select('id')->from('journey_sections')->where('journey_id', $filters['journey_id']));
        }
        if (! empty($filters['vehicle_id'])) {
            $query->where('trip_orders.vehicle_id', $filters['vehicle_id']);
        }
        if (! empty($filters['driver_id'])) {
            $query->where(fn ($w) => $w->where('trip_orders.driver_id', $filters['driver_id'])->orWhere('trip_orders.driver2_id', $filters['driver_id']));
        }

        if ($q = trim((string) $request->query('q'))) {
            NameSearch::apply($query, $q, ['last_name', 'first_name', 'city', 'postal_code', 'customer_number', 'company_name', 'email'], 'c', function ($w, string $token) {
                $w->orWhere('trip_orders.title', 'like', NameSearch::start($token))
                    ->orWhere('trip_orders.title', 'like', '% '.NameSearch::start($token))
                    ->orWhere('trip_orders.number', 'like', NameSearch::start($token))
                    ->orWhere('trip_orders.external_company', 'like', NameSearch::start($token));
            });
        }

        $result = $query->orderByRaw('trip_orders.date_from IS NULL')->orderBy('trip_orders.date_from')
            ->orderBy('trip_orders.created_at')->orderBy('trip_orders.id')->paginate(20)->withQueryString();
        $result->getCollection()->transform(fn (TripOrder $o) => $this->present($o, false));

        return response()->json($result);
    }

    public function show(TripOrder $tripOrder): JsonResponse
    {
        return response()->json($this->present($tripOrder, true));
    }

    /**
     * Fahrer-Fassung als PDF (ohne Preise, mit Erfassungsblatt), bei jedem Abruf frisch aus dem aktuellen Stand. Auch vor dem Ausgeben
     * abrufbar, dann mit dem Hinweis „noch nicht ausgegeben“. Jeder Abruf steht im Änderungsprotokoll.
     */
    public function pdf(TripOrder $tripOrder, TripOrderPdf $pdf): Response
    {
        $binary = $pdf->render($tripOrder);
        AuditLog::record('pdf_created', $tripOrder, null, ['number' => $tripOrder->number, 'status' => $tripOrder->status->value]);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($tripOrder->number ?? 'Fahrtauftrag-Entwurf').'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Disponieren: nur die übergebenen Felder ändern sich. Nach dem Ausgeben ist für Fahrzeug, Fahrer und Termine eine Begründung nötig. */
    public function update(Request $request, TripOrder $tripOrder): JsonResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['sometimes', 'nullable', 'uuid'],
            'external_company' => ['sometimes', 'nullable', 'string', 'max:150'],
            'external_plate' => ['sometimes', 'nullable', 'string', 'max:30'],
            'driver_id' => ['sometimes', 'nullable', 'uuid'],
            'driver2_id' => ['sometimes', 'nullable', 'uuid'],
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'departure_at' => ['sometimes', 'nullable', 'date'],
            'passengers' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:99'],
            'driver_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'collective_only' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'passengers.min' => 'Die Zahl der Fahrgäste muss mindestens 1 sein.',
            'passengers.max' => 'Mehr als 99 Fahrgäste sind nicht möglich.',
            'date_from.date_format' => 'Bitte das Datum als JJJJ-MM-TT angeben.',
            'date_to.date_format' => 'Bitte das Datum als JJJJ-MM-TT angeben.',
        ]);
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        // Leere Fremdunternehmer-Felder zählen als nicht angegeben
        foreach (['external_company', 'external_plate'] as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                $data[$field] = null;
            }
        }

        return response()->json($this->present($this->orders->assign($tripOrder, $data, $reason), true));
    }

    /** Ausgeben: vergibt die Nummer. Admin und Disponent. */
    public function issue(TripOrder $tripOrder): JsonResponse
    {
        return response()->json($this->present($this->orders->issue($tripOrder), true));
    }

    /** @return array<string, mixed> */
    private function present(TripOrder $order, bool $detail): array
    {
        $order->loadMissing(['customer', 'vehicle', 'driver', 'driver2', 'section']);
        $out = $order->toArray();
        $out['status_label'] = $order->status->label();
        $out['vehicle_type_label'] = $order->vehicle_type?->label();
        $out['journey_id'] = $order->section?->journey_id;
        unset($out['section']);

        if ($detail) {
            $out['warnings'] = $this->orders->warnings($order);
            $out['document_number'] = $order->document?->number;
        }

        return $out;
    }
}
