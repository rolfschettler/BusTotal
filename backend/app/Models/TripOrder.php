<?php

namespace App\Models;

use App\Enums\TripKind;
use App\Enums\TripOrderStatus;
use App\Enums\VehicleType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Fahrtauftrag: eine Strecke mit einem Bus. Entsteht als Entwurf aus der Auftragsbestätigung; Fahrzeug und Fahrer weist der Disponent zu.
 * Mit dem ersten Ausgeben gibt es eine Nummer (sie bleibt auch nach dem Zurücknehmen), ab der Abrechnung ist der Auftrag gesperrt. Das Modell setzt das durch, nicht nur die Schnittstelle.
 */
class TripOrder extends Model
{
    use Auditable, HasUuids;

    /** Nur diese Felder kommen aus Formularen. Alles andere setzt der TripOrderService. */
    protected $fillable = [
        'vehicle_id', 'external_company', 'external_plate', 'driver_id', 'driver2_id',
        'date_from', 'date_to', 'departure_at', 'staging_time', 'passengers', 'driver_note', 'notes', 'collective_only',
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
            'cancelled_at' => 'datetime',
            'collective_only' => 'boolean',
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
                throw new LogicException('Ein Fahrtauftrag mit Nummer darf nicht gelöscht werden.');
            }
        });
    }

    /** Mit der Reise abgesagt: wird nicht mehr disponiert, behält aber seinen Stand für den Fall, dass die Absage zurückgenommen wird. */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** Ist der Auftrag gerade an den Fahrer ausgegeben? Maßgeblich ist der Stand: Eine zurückgenommene Ausgabe behält ihre Nummer. */
    public function isIssued(): bool
    {
        return in_array($this->status, [TripOrderStatus::Ausgegeben, TripOrderStatus::Erledigt], true);
    }

    public function isRevoked(): bool
    {
        return $this->number !== null && ! $this->isIssued();
    }

    /** Zustiegsstellen in Reihenfolge der Abholung */
    public function boardings(): HasMany
    {
        return $this->hasMany(TripOrderBoarding::class)->orderBy('position');
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
