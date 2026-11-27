<?php

namespace App\Services;

use App\Enums\TripOrderStatus;
use App\Models\AuditLog;
use App\Models\TripOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rückerfassung: Das Büro tippt das Erfassungsblatt des Fahrers ein und setzt den Fahrtauftrag auf „erledigt“.
 *
 * Erfasst werden disponierte und ausgegebene Aufträge. Pflicht für „erledigt“ sind nur die Kilometerstände am Anfang und Ende
 * (bei Fremdunternehmern auch die nicht) und vollständige Grenzzeilen. Alles andere sind Hinweise, nie eine Sperre; abgelehnt
 * wird nur Unmögliches. Solange nicht abgerechnet ist, lässt sich „erledigt“ mit Begründung zurücknehmen.
 */
class TripReportService
{
    /** Ab dieser Abweichung der gefahrenen von den geplanten Kilometern gibt es einen Hinweis (Anteil). */
    private const KM_TOLERANCE = 0.15;

    public function __construct(
        private readonly TripOrderPdf $sheet,
        private readonly NumberRangeService $numbers,
    ) {}

    /**
     * Die gespeicherten Angaben; solange noch nichts gespeichert ist, die Zeilen wie auf dem Erfassungsblatt vorbelegt.
     *
     * @return array<string, mixed>
     */
    public function report(TripOrder $order): array
    {
        $order->load(['times', 'borders', 'extras', 'costs']);
        $times = $order->times->isEmpty() ? $this->prefilledTimes($order) : $order->times->toArray();

        return [
            'saved' => $order->times->isNotEmpty(),
            'times' => $times,
            'borders' => $order->borders->toArray(),
            'extras' => $order->extras->toArray(),
            'costs' => $order->costs->toArray(),
            'actual_passengers' => $order->actual_passengers,
            'report_notes' => $order->report_notes,
            'planned_km' => $this->plannedKm($order),
            'editable' => $this->editable($order),
            'missing' => $this->missing($order),
            'warnings' => $this->warnings($order),
        ];
    }

    /**
     * Speichern ersetzt alle Zeilen. Der Stand des Fahrtauftrags bleibt.
     *
     * @param  array<string, mixed>  $data  geprüfte Eingabe (TripReportController)
     */
    public function save(TripOrder $order, array $data): TripOrder
    {
        return DB::transaction(function () use ($order, $data) {
            $order = $this->lockEditable($order);
            $times = array_values($data['times'] ?? []);
            $borders = array_values($data['borders'] ?? []);
            $this->assertPossible($times, $borders);

            $before = $this->snapshot($order);
            foreach (['times' => $times, 'borders' => $borders, 'extras' => array_values($data['extras'] ?? []), 'costs' => array_values($data['costs'] ?? [])] as $relation => $rows) {
                $order->{$relation}()->delete();
                foreach ($rows as $i => $row) {
                    $order->{$relation}()->create($row + ['position' => $i + 1]);
                }
            }
            $order->forceFill([
                'actual_passengers' => $data['actual_passengers'] ?? null,
                'report_notes' => $data['report_notes'] ?? null,
            ])->save();

            $after = $this->snapshot($order);
            if ($before !== $after) {
                AuditLog::record('report_saved', $order, $before, $after);
            }

            return $order->fresh();
        });
    }

    /** Fahrt abschließen. Fehlt die Nummer (nie ausgegeben), wird sie jetzt vergeben. */
    public function complete(TripOrder $order): TripOrder
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lockEditable($order);

            if ($order->date_from !== null && $order->date_from->toDateString() > Carbon::today('Europe/Berlin')->toDateString()) {
                $this->fail('Die Fahrt hat noch nicht begonnen und kann nicht erledigt sein.');
            }
            if (($missing = $this->missing($order)) !== []) {
                $this->fail('Für „erledigt“ fehlt noch: '.implode(', ', $missing).'.');
            }

            $number = $order->number ?? $this->numbers->next('fahrtauftrag');
            $order->forceFill([
                'number' => $number, 'status' => TripOrderStatus::Erledigt,
                'issued_at' => $order->issued_at ?? now(), 'issued_by' => $order->issued_by ?? auth()->id(),
                'completed_at' => now(), 'completed_by' => auth()->id(),
            ])->save();
            AuditLog::record('completed', $order, null, ['number' => $number]);

            return $order->fresh();
        });
    }

    /** „Erledigt“ zurücknehmen: wieder „ausgegeben“, die Eingaben bleiben. Nur solange nicht abgerechnet. */
    public function reopen(TripOrder $order, string $reason): TripOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            $order = TripOrder::lockForUpdate()->findOrFail($order->id);
            if ($order->status !== TripOrderStatus::Erledigt || $order->billing_status === 'abgerechnet') {
                $this->fail('Zurücknehmen lässt sich nur ein erledigter, noch nicht abgerechneter Fahrtauftrag.');
            }

            $completedAt = $order->completed_at?->toIso8601String();
            $order->forceFill(['status' => TripOrderStatus::Ausgegeben, 'completed_at' => null, 'completed_by' => null])->save();
            AuditLog::record('report_reopened', $order, null, ['reason' => trim($reason), 'completed_at' => $completedAt]);

            return $order->fresh();
        });
    }

    public function editable(TripOrder $order): bool
    {
        return ! $order->isCancelled() && $order->billing_status !== 'abgerechnet'
            && in_array($order->status, [TripOrderStatus::Disponiert, TripOrderStatus::Ausgegeben], true);
    }

    /**
     * Was für „erledigt“ noch fehlt, in Worten. Grundlage sind die gespeicherten Zeilen.
     *
     * @return list<string>
     */
    public function missing(TripOrder $order): array
    {
        $order->load(['times', 'borders']);
        $out = [];
        if (blank($order->external_company)) {
            if ($order->times->first()?->km === null) {
                $out[] = 'km-Stand am Anfang (erste Zeile)';
            }
            if ($order->times->count() < 2 || $order->times->last()->km === null) {
                $out[] = 'km-Stand am Ende (letzte Zeile)';
            }
        }
        foreach ($order->borders as $b) {
            if ($b->km_in === null || $b->km_out === null) {
                $out[] = "Kilometerstände an der Grenze für {$b->country}";
            }
        }

        return $out;
    }

    /**
     * Hinweise, nie eine Sperre.
     *
     * @return list<string>
     */
    public function warnings(TripOrder $order): array
    {
        $order->load(['times', 'borders', 'extras']);
        $out = [];
        $km = $order->times->pluck('km')->filter(fn ($v) => $v !== null)->values();
        if ($km->all() !== $km->sort()->values()->all()) {
            $out[] = 'Die Kilometerstände steigen nicht von Zeile zu Zeile.';
        }

        $previous = null;
        foreach ($order->times as $t) {
            if ($t->date === null || $t->time === null) {
                continue;
            }
            $moment = $t->date->toDateString().' '.$t->time;
            if ($previous !== null && $moment < $previous) {
                $out[] = "Die Zeit bei „{$t->event}“ liegt vor der Zeit darüber.";
            }
            $previous = $moment;
        }

        $start = $order->times->first()?->km;
        $end = $order->times->count() > 1 ? $order->times->last()->km : null;
        if ($start !== null && $end !== null) {
            $planned = $this->plannedKm($order);
            $driven = $end - $start;
            if ($planned !== null && $planned > 0 && abs($driven - $planned) > $planned * self::KM_TOLERANCE) {
                $out[] = "Gefahren sind {$driven} km, geplant waren {$planned} km.";
            }
            foreach ($order->borders as $b) {
                if (($b->km_in !== null && ($b->km_in < $start || $b->km_in > $end)) || ($b->km_out !== null && ($b->km_out < $start || $b->km_out > $end))) {
                    $out[] = "Die Grenzstände für {$b->country} liegen nicht zwischen Anfang und Ende der Fahrt.";
                }
            }
        }

        if ($start !== null && $order->vehicle_id !== null && $order->date_from !== null) {
            // Der letzte frühere Auftrag desselben Busses: Sein höchster km-Stand darf nicht über dem Anfang liegen.
            $earlier = TripOrder::where('vehicle_id', $order->vehicle_id)->where('id', '!=', $order->id)->whereNull('cancelled_at')
                ->whereNotNull('date_from')->where('date_from', '<', $order->date_from->toDateString())
                ->whereHas('times', fn ($q) => $q->whereNotNull('km'))->orderByDesc('date_from')->first();
            $last = $earlier?->times()->max('km');
            if ($last !== null && $start < $last) {
                $out[] = "Der km-Stand am Anfang liegt unter dem letzten Stand dieses Busses ({$last} km, {$earlier->title}).";
            }
        }

        foreach ($order->extras as $e) {
            if ($e->payment === 'bar' && $e->amount === null) {
                $out[] = "„{$e->title}“ wurde bar bezahlt, aber der Betrag fehlt.";
            }
        }
        if ($order->borders->isNotEmpty()) {
            $out[] = 'Fahrt ins Ausland: Bitte prüfen, ob ausländische Umsatzsteuer anfällt.';
        }

        return $out;
    }

    /** Geplante Kilometer der Strecke (mit Leerfahrten), gerundet; null ohne Angaben. */
    public function plannedKm(TripOrder $order): ?int
    {
        $route = $order->route ?? [];
        $trip = $route['trip'] ?? null;
        $km = $trip ? ((float) ($trip['loaded_km'] ?? 0) + (float) ($trip['empty_km'] ?? 0)) : (float) ($route['own']['loaded_km'] ?? 0);

        return $km > 0 ? (int) round($km) : null;
    }

    /**
     * Die Zeilen wie auf dem Erfassungsblatt: Datum und Ort vorbelegt, der Betriebshof nur bei einer Leerfahrt dorthin. Die erste
     * Zeile ist der Anfang, die letzte das Ende der Fahrt.
     *
     * @return list<array<string, mixed>>
     */
    private function prefilledTimes(TripOrder $order): array
    {
        [$depotOut, $start, $destination, $back, $depotIn] = $this->sheet->eventRows($order);
        $rows = [];
        $add = function (string $event, array $from, ?string $leg) use (&$rows) {
            $rows[] = [
                'event' => $event,
                'date' => $from['date'] ? Carbon::createFromFormat('d.m.Y', $from['date'])->toDateString() : null,
                'place' => $from['place'] ?: null, 'time' => null, 'km' => null, 'leg' => $leg,
            ];
        };

        if ($depotOut['place'] !== '') {
            $add($depotOut['event'], ['date' => $start['date'], 'place' => $depotOut['place']], null);
        }
        $add($start['event'], $start, $rows === [] ? null : 'betriebshof');
        $add($destination['event'], $destination, 'befoerderung');
        $add($back['event'], $back, 'befoerderung');
        $depot = $depotIn['place'] !== '';
        $add($depot ? $depotIn['event'] : 'Ende der Fahrt', ['date' => $destination['date'], 'place' => $depotIn['place']], $depot ? 'betriebshof' : 'befoerderung');

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $times
     * @param  list<array<string, mixed>>  $borders
     */
    private function assertPossible(array $times, array $borders): void
    {
        $start = $times[0]['km'] ?? null;
        $end = count($times) > 1 ? ($times[count($times) - 1]['km'] ?? null) : null;
        if ($start !== null && $end !== null && $end < $start) {
            $this->fail('Der km-Stand am Ende ist kleiner als am Anfang. Bitte prüfen.');
        }
        foreach ($borders as $b) {
            if (($b['km_in'] ?? null) !== null && ($b['km_out'] ?? null) !== null && $b['km_out'] < $b['km_in']) {
                $this->fail("Grenze {$b['country']}: Der km-Stand bei der Ausfahrt ist kleiner als bei der Einfahrt.");
            }
        }
    }

    private function lockEditable(TripOrder $order): TripOrder
    {
        $order = TripOrder::lockForUpdate()->findOrFail($order->id);
        if (! $this->editable($order)) {
            $this->fail(match (true) {
                $order->isCancelled() => 'Dieser Fahrtauftrag ist mit der Reise abgesagt.',
                $order->status === TripOrderStatus::Entwurf => 'Bitte zuerst Bus und Fahrer einteilen.',
                default => 'Dieser Fahrtauftrag ist erledigt. Nehmen Sie zuerst „Erledigt“ zurück.',
            });
        }

        return $order;
    }

    /** Alle Angaben der Rückerfassung für das Änderungsprotokoll. */
    private function snapshot(TripOrder $order): array
    {
        $order->load(['times', 'borders', 'extras', 'costs']);

        return [
            'actual_passengers' => $order->actual_passengers, 'report_notes' => $order->report_notes,
            'times' => $order->times->toArray(), 'borders' => $order->borders->toArray(),
            'extras' => $order->extras->toArray(), 'costs' => $order->costs->toArray(),
        ];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['report' => [$message]]);
    }
}
