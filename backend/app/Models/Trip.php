<?php

namespace App\Models;

use App\Enums\TripKind;
use App\Enums\VehicleType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine gespeicherte Tour (Tour-Archiv): Halte, Fahrzeugbedarf (Typ und Anzahl), Kennzahlen und berechnete Strecke samt Linienzug.
 * Zum Wiederverwenden in weiteren Angeboten. Der unveränderliche Stand am Beleg ist ein eigener Schnappschuss (nicht diese Zeile).
 */
class Trip extends Model
{
    use Auditable, HasUuids;

    protected $fillable = [
        'title', 'trip_kind', 'vehicle_type', 'vehicle_count', 'depot_id', 'from_depot', 'return_to_depot', 'round_trip',
        'loaded_km', 'empty_km', 'drive_s', 'elapsed_s', 'days', 'route', 'geometry',
    ];

    // Die große Linie und der Zeitplan gehören nur in den Einzelabruf, nicht in die Liste.
    protected $hidden = ['route', 'geometry'];

    // Der Linienzug hat tausende Punkte: nicht ins Änderungsprotokoll.
    protected array $auditExclude = ['route', 'geometry'];

    protected function casts(): array
    {
        return [
            'trip_kind' => TripKind::class,
            'vehicle_type' => VehicleType::class,
            'from_depot' => 'boolean',
            'return_to_depot' => 'boolean',
            'round_trip' => 'boolean',
            'loaded_km' => 'float',
            'empty_km' => 'float',
            'route' => 'array',
            'geometry' => 'array',
        ];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('position');
    }

    public function depot(): BelongsTo
    {
        return $this->belongsTo(Depot::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(JourneySection::class);
    }
}
