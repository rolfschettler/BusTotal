<?php

namespace App\Models;

use App\Enums\TripKind;
use App\Enums\TripOrderStatus;
use App\Enums\VehicleType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Fahrtauftrag: eine Strecke mit einem Bus. Entsteht als Entwurf aus der Auftragsbestätigung; Fahrzeug und Fahrer weist der Disponent zu.
 * Ab „ausgegeben“ gibt es eine Nummer, ab der Abrechnung ist der Auftrag gesperrt. Das Modell setzt das durch, nicht nur die Schnittstelle.
 */
class TripOrder extends Model
{
    use Auditable, HasUuids;

    /** Nur diese Felder kommen aus Formularen. Alles andere setzt der TripOrderService. */
    protected $fillable = [
        'vehicle_id', 'external_company', 'external_plate', 'driver_id', 'driver2_id',
        'date_from', 'date_to', 'departure_at', 'passengers', 'driver_note', 'notes', 'collective_only',
    ];

    protected function casts(): array
    {
        return [
            'status' => TripOrderStatus::class,
            'trip_kind' => TripKind::class,
            'vehicle_type' => VehicleType::class,
            'route' => 'array',
            'date_from' => 'date:Y-m-d',
            'date_to' => 'date:Y-m-d',
            'departure_at' => 'datetime',
            'issued_at' => 'datetime',
            'collective_only' => 'boolean',
            'passengers' => 'integer',
            'bus_no' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Nach der Abrechnung ist nichts mehr änderbar, auch nicht am Modell vorbei (GoBD: Fahrtaufträge sind Aufbewahrungsunterlagen).
        static::updating(function (TripOrder $order) {
            if ($order->getRawOriginal('billing_status') === 'abgerechnet') {
                throw new LogicException('Ein abgerechneter Fahrtauftrag ist gesperrt.');
            }
        });

        static::deleting(function (TripOrder $order) {
            if ($order->number !== null) {
                throw new LogicException('Ein ausgegebener Fahrtauftrag darf nicht gelöscht werden.');
            }
        });
    }

    public function isIssued(): bool
    {
        return $this->number !== null;
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(JourneySection::class, 'journey_section_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function driver2(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver2_id')->withTrashed();
    }
}
