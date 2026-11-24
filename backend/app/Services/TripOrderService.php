<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\TripOrderStatus;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Driver;
use App\Models\DriverAbsence;
use App\Models\TripOrder;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Der eine Weg, Fahrtaufträge anzulegen und zu disponieren. Das Ausgeben (Nummer, Fahrer-PDF) kommt in einem späteren Schritt.
 */
class TripOrderService
{
    public function __construct(private readonly NumberRangeService $numbers) {}

    /** Fahrzeuge bis zu so vielen Sitzplätzen dürfen mit Klasse D1 gefahren werden (höchstens 16 Fahrgäste, Klasse D1 nach FeV). */
    private const D1_MAX_SEATS = 17;

    /**
     * Fahrzeug (eigenes oder Fremdunternehmer), Fahrer 1 und 2 und die Eckdaten zuweisen. Prüfungen sind **Warnungen** (siehe
     * warnings()), nie eine Sperre: Der Disponent entscheidet. Hart abgelehnt werden nur unmögliche Angaben.
     *
     * Nach dem Ausgeben (Nummer vergeben) ist jede Änderung an Fahrzeug, Fahrern und Terminen nur mit Begründung möglich und wird
     * protokolliert. Erledigte und abgerechnete Aufträge sind gesperrt.
     *
     * @param  array<string, mixed>  $data  Schlüssel aus TripOrder::$fillable; fehlende Schlüssel bleiben unverändert
     */
    public function assign(TripOrder $order, array $data, ?string $reason = null): TripOrder
    {
        // Die Zustiegsstellen kommen als Liste unter „boardings“ und ersetzen die vorhandenen; fehlt der Schlüssel, bleiben sie.
        $boardings = array_key_exists('boardings', $data) ? $this->normalizeBoardings((array) $data['boardings']) : null;
        unset($data['boardings']);

        return DB::transaction(function () use ($order, $data, $boardings, $reason) {
            $order = TripOrder::lockForUpdate()->findOrFail($order->id);

            if ($order->status === TripOrderStatus::Erledigt || $order->billing_status === 'abgerechnet') {
                $this->fail('Dieser Fahrtauftrag ist erledigt und kann nicht mehr geändert werden.');
            }

            $order->fill($data);
            $this->validateAssignment($order);

            $issued = $order->isIssued();
            $changes = array_diff_key($order->getDirty(), array_flip(['driver_note', 'notes', 'collective_only']));
            $boardingsChanged = $boardings !== null && $boardings !== $this->currentBoardings($order);
            if ($boardingsChanged) {
                $changes['boardings'] = true;
            }
            if ($issued && $changes !== [] && blank($reason)) {
                $this->fail('Der Fahrtauftrag ist schon ausgegeben. Bitte eine Begründung für die Änderung angeben.');
            }

            // Vor dem Ausgeben folgt der Stand der Zuweisung; danach bleibt er „ausgegeben“.
            if (! $issued) {
                $order->status = $this->isComplete($order) ? TripOrderStatus::Disponiert : TripOrderStatus::Entwurf;
            }
            $order->save();

            if ($boardingsChanged) {
                $order->boardings()->delete();
                foreach ($boardings as $i => $row) {
                    $order->boardings()->create($row + ['position' => $i + 1]);
                }
            }

            if ($issued && $changes !== []) {
                AuditLog::record('reassigned', $order, null, ['reason' => $reason, 'fields' => array_keys($changes)]);
            }

            return $order->fresh(['vehicle', 'driver', 'driver2', 'boardings']);
        });
    }

    /**
     * Zustiegsstellen in eine feste Form bringen: leere Zeilen entfallen, jede andere braucht einen Ort.
     *
     * @param  array<int, mixed>  $rows
     * @return list<array{kind: string, place: string, time: ?string, passengers: ?string}>
     */
    private function normalizeBoardings(array $rows): array
    {
        // Die geprüften Daten können die Zeilen in anderer Reihenfolge liefern: nach der Zeilennummer ordnen
        ksort($rows);
        $out = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $place = trim((string) ($row['place'] ?? ''));
            $time = trim((string) ($row['time'] ?? ''));
            $count = trim((string) ($row['passengers'] ?? ''));
            $kind = ($row['kind'] ?? 'zustieg') === 'ausstieg' ? 'ausstieg' : 'zustieg';
            if ($place === '' && $time === '' && $count === '') {
                continue;
            }
            if ($place === '') {
                $this->fail('Bitte bei jedem Zu- und Ausstieg den Ort angeben.');
            }
            $out[] = ['kind' => $kind, 'place' => $place, 'time' => $time === '' ? null : $time, 'passengers' => $count === '' ? null : $count];
        }

        return $out;
    }

    /** @return list<array{place: string, time: ?string, passengers: ?string}> */
    private function currentBoardings(TripOrder $order): array
    {
        return $order->boardings()->get()->map(fn ($b) => ['kind' => $b->kind, 'place' => $b->place, 'time' => $b->time, 'passengers' => $b->passengers])->all();
    }

    /** Die erste Zahl aus einer Personenzahl wie „20“ oder „ca. 20 Pers.“; null, wenn keine Zahl darin steht. */
    private function passengerCount(?string $text): ?int
    {
        return $text !== null && preg_match('/\d+/', $text, $m) === 1 ? (int) $m[0] : null;
    }

    /**
     * Ausgeben: Nummer vergeben, Stand „ausgegeben“. Nur ein disponierter Auftrag (Fahrzeug und Fahrer oder Fremdunternehmer) wird
     * ausgegeben. Nummer und Stand entstehen in einer Transaktion, scheitert etwas, wird keine Nummer verbraucht.
     */
    public function issue(TripOrder $order): TripOrder
    {
        return DB::transaction(function () use ($order) {
            $order = TripOrder::lockForUpdate()->findOrFail($order->id);

            if ($order->isIssued()) {
                $this->fail('Dieser Fahrtauftrag ist schon ausgegeben.');
            }
            if ($order->status !== TripOrderStatus::Disponiert) {
                $this->fail('Bitte zuerst Fahrzeug und Fahrer zuweisen (oder einen Fremdunternehmer eintragen).');
            }

            $number = $this->numbers->next('fahrtauftrag');
            $order->forceFill([
                'number' => $number, 'status' => TripOrderStatus::Ausgegeben, 'issued_at' => now(), 'issued_by' => auth()->id(),
            ])->save();
            AuditLog::record('issued', $order, null, ['number' => $number]);

            return $order->fresh(['vehicle', 'driver', 'driver2']);
        });
    }

    /** Disponiert heißt: ein Fahrzeug (eigenes oder fremdes) und ein Fahrer sind zugewiesen, oder ein Fremdunternehmer fährt komplett. */
    private function isComplete(TripOrder $order): bool
    {
        return ($order->vehicle_id !== null && $order->driver_id !== null) || filled($order->external_company);
    }

    private function validateAssignment(TripOrder $order): void
    {
        if ($order->vehicle_id !== null && filled($order->external_company)) {
            $this->fail('Bitte entweder ein eigenes Fahrzeug oder einen Fremdunternehmer angeben, nicht beides.');
        }
        if ($order->vehicle_id !== null && ! Vehicle::whereKey($order->vehicle_id)->exists()) {
            $this->fail('Das Fahrzeug wurde nicht gefunden.');
        }
        foreach (['driver_id', 'driver2_id'] as $field) {
            if ($order->{$field} !== null && ! Driver::whereKey($order->{$field})->exists()) {
                $this->fail('Der Fahrer wurde nicht gefunden.');
            }
        }
        if ($order->driver2_id !== null && $order->driver_id === null) {
            $this->fail('Bitte zuerst Fahrer 1 angeben, dann Fahrer 2.');
        }
        if ($order->driver_id !== null && $order->driver_id === $order->driver2_id) {
            $this->fail('Fahrer 1 und Fahrer 2 dürfen nicht dieselbe Person sein.');
        }
        if ($order->date_from !== null && $order->date_to !== null && $order->date_to->lt($order->date_from)) {
            $this->fail('Das Ende der Fahrt liegt vor dem Beginn.');
        }
    }

    /**
     * Hinweise zur Zuweisung. Nie eine Sperre, jeder Hinweis nennt, was zu prüfen ist.
     *
     * @return list<array{code: string, field: string, text: string}>
     */
    public function warnings(TripOrder $order): array
    {
        $order->loadMissing(['vehicle', 'driver', 'driver2']);
        $from = $order->date_from;
        $to = $order->date_to ?? $from;
        $out = [];
        $add = function (string $code, string $field, string $text) use (&$out) {
            $out[] = ['code' => $code, 'field' => $field, 'text' => $text];
        };

        $vehicle = $order->vehicle;
        if ($vehicle !== null) {
            if ($order->vehicle_type !== null && $vehicle->type !== $order->vehicle_type) {
                $add('vehicle_type', 'vehicle', "Gebucht ist {$order->vehicle_type->label()}, das Fahrzeug {$vehicle->display_name} ist ein(e) {$vehicle->type->label()}.");
            }
            $count = $this->passengerCount($order->passengers === null ? null : (string) $order->passengers);
            if ($count !== null && $vehicle->seats !== null && $vehicle->seats < $count) {
                $add('seats', 'vehicle', "Das Fahrzeug {$vehicle->display_name} hat {$vehicle->seats} Sitzplätze, vorgesehen sind {$count} Fahrgäste.");
            }
            foreach ($this->deadlineProblems($vehicle, Vehicle::DEADLINES, $to) as $text) {
                $add('vehicle_deadline', 'vehicle', "Fahrzeug {$vehicle->display_name}: $text");
            }
            if ($from !== null) {
                $other = $this->overlapping($order, $from, $to)->where('vehicle_id', $vehicle->id)->first();
                if ($other !== null) {
                    $add('vehicle_busy', 'vehicle', "Das Fahrzeug {$vehicle->display_name} ist schon eingesetzt: {$this->describe($other)}.");
                }
            }
        }

        foreach (['driver' => $order->driver, 'driver2' => $order->driver2] as $field => $driver) {
            if ($driver === null) {
                continue;
            }
            foreach ($this->deadlineProblems($driver, Driver::DEADLINES, $to) as $text) {
                $add('driver_deadline', $field, "{$driver->display_name}: $text");
            }
            if ($vehicle !== null && ! $this->mayDrive($driver, $vehicle)) {
                $add('license', $field, "{$driver->display_name} hat keine passende Führerscheinklasse für {$vehicle->display_name} (nötig: ".($this->needsD($vehicle) ? 'D oder DE' : 'D1, D1E, D oder DE').').');
            }
            if ($from !== null) {
                $absence = DriverAbsence::where('driver_id', $driver->id)
                    ->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString())->first();
                if ($absence !== null) {
                    $add('absence', $field, "{$driver->display_name} ist abwesend ({$absence->type->label()} {$absence->start_date->format('d.m.Y')} bis {$absence->end_date->format('d.m.Y')}).");
                }
                $other = $this->overlapping($order, $from, $to)
                    ->where(fn ($q) => $q->where('driver_id', $driver->id)->orWhere('driver2_id', $driver->id))->first();
                if ($other !== null) {
                    $add('driver_busy', $field, "{$driver->display_name} ist schon eingesetzt: {$this->describe($other)}.");
                }
            }
        }

        return $out;
    }

    /** Andere Fahrtaufträge, die sich zeitlich mit diesem überschneiden (nur mit bekanntem Beginn). */
    private function overlapping(TripOrder $order, Carbon $from, Carbon $to): Builder
    {
        return TripOrder::where('id', '!=', $order->id)
            ->whereNotNull('date_from')
            ->whereDate('date_from', '<=', $to->toDateString())
            ->whereRaw('COALESCE(date_to, date_from) >= ?', [$from->toDateString()]);
    }

    private function describe(TripOrder $other): string
    {
        $period = $other->date_from->format('d.m.Y');
        if ($other->date_to !== null && ! $other->date_to->isSameDay($other->date_from)) {
            $period .= ' bis '.$other->date_to->format('d.m.Y');
        }

        return "{$other->title} am $period";
    }

    /**
     * Fristen, die bis zum Ende der Fahrt abgelaufen sind. Ohne Datum der Fahrt zählt der heutige Tag.
     *
     * @param  array<string, string>  $deadlines  Spalte => Bezeichnung
     * @return list<string>
     */
    private function deadlineProblems(Vehicle|Driver $model, array $deadlines, ?Carbon $end): array
    {
        $end = ($end ?? now())->copy()->startOfDay();
        $out = [];
        foreach ($deadlines as $column => $label) {
            $date = $model->{$column};
            if ($date !== null && $date->copy()->startOfDay()->lt($end)) {
                $out[] = $date->copy()->startOfDay()->lt(now()->startOfDay())
                    ? "$label ist seit {$date->format('d.m.Y')} abgelaufen."
                    : "$label läuft am {$date->format('d.m.Y')} ab, vor dem Ende der Fahrt.";
            }
        }

        return $out;
    }

    /** Große Busse brauchen D oder DE, kleine (bis 16 Fahrgäste) genügt auch D1 oder D1E. */
    private function needsD(Vehicle $vehicle): bool
    {
        return $vehicle->seats === null || $vehicle->seats > self::D1_MAX_SEATS;
    }

    private function mayDrive(Driver $driver, Vehicle $vehicle): bool
    {
        $classes = (array) $driver->license_classes;
        $allowed = $this->needsD($vehicle) ? ['D', 'DE'] : ['D1', 'D1E', 'D', 'DE'];

        return array_intersect($allowed, $classes) !== [];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['assignment' => [$message]]);
    }

    /**
     * Legt zu einer festgeschriebenen Auftragsbestätigung je Strecke und Bus einen Fahrtauftrag als Entwurf an: Fahrzeugtyp vorbelegt,
     * Fahrzeug und Fahrer offen, Strecke aus der festgehaltenen Fahrtbeschreibung der Auftragsbestätigung.
     *
     * Wiederholbar und nie zerstörend: Ein Bus, zu dem schon ein Fahrtauftrag besteht (auch aus einer früheren Auftragsbestätigung
     * derselben Reise), wird übersprungen; nichts wird gelöscht oder überschrieben. Weniger Busse als zuvor lassen vorhandene Aufträge
     * stehen, das entscheidet der Disponent.
     *
     * @return list<TripOrder> die neu angelegten Fahrtaufträge
     */
    public function createFromConfirmation(Document $document): array
    {
        if ($document->type !== DocumentType::Auftragsbestaetigung || $document->isDraft()) {
            return [];
        }

        return DB::transaction(function () use ($document) {
            $created = [];

            foreach ($document->tour_snapshot['sections'] ?? [] as $section) {
                $sectionId = $section['section_id'] ?? null;
                if ($sectionId === null) {
                    continue;
                }
                $trip = $section['trip'] ?? null;

                foreach ($section['vehicles'] ?? [] as $vehicle) {
                    $type = $vehicle['type'] ?? null;
                    for ($bus = 1; $bus <= max(1, (int) ($vehicle['count'] ?? 1)); $bus++) {
                        $exists = TripOrder::where(['journey_section_id' => $sectionId, 'vehicle_type' => $type, 'bus_no' => $bus])->exists();
                        if ($exists) {
                            continue;
                        }

                        $order = new TripOrder;
                        $order->forceFill([
                            'status' => TripOrderStatus::Entwurf,
                            'journey_section_id' => $sectionId,
                            'document_id' => $document->id,
                            'customer_id' => $document->customer_id,
                            'title' => $section['title'] ?? $trip['title'] ?? $section['kind_label'] ?? 'Fahrt',
                            'trip_kind' => $trip['trip_kind'] ?? 'gelegenheit',
                            'vehicle_type' => $type,
                            'bus_no' => $bus,
                            // Die Strecke ohne Preise: die Grundlage des Fahrer-PDFs
                            'route' => array_diff_key($section, ['vehicles' => 1]),
                            'date_from' => $document->service_date_from,
                            'date_to' => $document->service_date_to,
                            'created_by' => auth()->id(),
                        ])->save();
                        $created[] = $order;
                    }
                }
            }

            return $created;
        });
    }
}
