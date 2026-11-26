<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\TripOrderStatus;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Journey;
use App\Models\TripOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reise absagen und die Absage zurücknehmen. Die Reise und alle Belege bleiben erhalten (Aufbewahrung); alle Fahrtaufträge, die weder
 * erledigt noch abgerechnet sind, gelten mit ab, auch ausgegebene. Eine Stornogebühr oder Rückzahlung bucht die Software nicht von
 * selbst: Die Vorschau nennt nur, was schon bezahlt ist.
 */
class JourneyCancellationService
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Was eine Absage bewirkt: die betroffenen Fahrtaufträge, wie viele erledigte bleiben und die schon eingegangenen Beträge.
     *
     * @return array{orders: list<array<string, mixed>>, kept: int, payments: list<array<string, mixed>>, paid_total: string}
     */
    public function preview(Journey $journey): array
    {
        $orders = $this->open($journey)->with(['driver', 'driver2'])->orderBy('date_from')->orderBy('title')->orderBy('bus_no')->get();

        $documents = Document::where('journey_id', $journey->id)->where('status', DocumentStatus::Festgeschrieben->value)
            ->whereIn('type', [DocumentType::Rechnung->value, DocumentType::Anzahlung->value])->orderBy('finalized_at')->get();
        $paid = $this->payments->paidTotals($documents->pluck('id')->all());
        $rows = $documents->filter(fn (Document $d) => ($paid[$d->id] ?? 0) > 0)->values();

        return [
            'orders' => $orders->map(fn (TripOrder $o) => [
                'id' => $o->id, 'number' => $o->number, 'title' => $o->title, 'bus_no' => $o->bus_no,
                'status_label' => $o->status->label(),
                'drivers' => array_values(array_filter([$o->driver?->display_name, $o->driver2?->display_name, $o->external_company])),
            ])->all(),
            'kept' => $this->all($journey)->where('status', TripOrderStatus::Erledigt->value)->count(),
            'payments' => $rows->map(fn (Document $d) => [
                'id' => $d->id, 'number' => $d->number, 'type_label' => $d->type->label(), 'paid' => $this->money($paid[$d->id]),
            ])->all(),
            'paid_total' => $this->money((int) $rows->sum(fn (Document $d) => $paid[$d->id])),
        ];
    }

    public function cancel(Journey $journey, string $date, string $reason): Journey
    {
        return DB::transaction(function () use ($journey, $date, $reason) {
            // Zeilensperre: Währenddessen schreibt niemand einen Beleg dieser Reise fest (JourneyService::assertActive).
            $journey = Journey::lockForUpdate()->findOrFail($journey->id);
            if ($journey->isCancelled()) {
                $this->fail('Diese Reise ist schon abgesagt.');
            }
            if (Journey::query()->completed()->whereKey($journey->id)->exists()) {
                $this->fail('Diese Reise ist abgeschlossen (durchgeführt und bezahlt) und kann nicht mehr abgesagt werden.');
            }

            $journey->forceFill(['cancelled_on' => $date, 'cancel_reason' => trim($reason), 'cancelled_by' => auth()->id()])->save();

            $orders = $this->open($journey)->lockForUpdate()->get();
            foreach ($orders as $order) {
                $order->forceFill(['cancelled_at' => now()])->save();
            }
            AuditLog::record('cancelled', $journey, null, [
                'date' => $date, 'reason' => trim($reason), 'trip_orders' => $orders->pluck('id')->all(),
            ]);

            return $journey;
        });
    }

    public function reactivate(Journey $journey): Journey
    {
        return DB::transaction(function () use ($journey) {
            $journey = Journey::lockForUpdate()->findOrFail($journey->id);
            if (! $journey->isCancelled()) {
                $this->fail('Diese Reise ist nicht abgesagt.');
            }

            $previous = ['date' => $journey->cancelled_on->toDateString(), 'reason' => $journey->cancel_reason];
            $journey->forceFill(['cancelled_on' => null, 'cancel_reason' => null, 'cancelled_by' => null])->save();

            $orders = $this->all($journey)->whereNotNull('cancelled_at')->lockForUpdate()->get();
            foreach ($orders as $order) {
                $order->forceFill(['cancelled_at' => null])->save();
            }
            AuditLog::record('reactivated', $journey, null, $previous + ['trip_orders' => $orders->pluck('id')->all()]);

            return $journey;
        });
    }

    private function all(Journey $journey): Builder
    {
        return TripOrder::whereIn('journey_section_id', $journey->sections()->reorder()->select('id'));
    }

    /** Die Fahrtaufträge, die eine Absage trifft: noch nicht abgesagt, nicht erledigt und nicht abgerechnet. */
    private function open(Journey $journey): Builder
    {
        return $this->all($journey)->whereNull('cancelled_at')
            ->where('status', '!=', TripOrderStatus::Erledigt->value)->where('billing_status', '!=', 'abgerechnet');
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['journey' => [$message]]);
    }
}
