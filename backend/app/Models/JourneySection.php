<?php

namespace App\Models;

use App\Enums\SectionKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Abschnitt eines Reisevorhabens (Hinfahrt, Fahrt vor Ort, Rückfahrt): entweder mit einer Tour aus dem Archiv oder mit eigenen
 * Angaben (Kilometer, Fahrzeit, Tage) ohne Route, zum Beispiel für eine Fahrt vor Ort.
 */
class JourneySection extends Model
{
    use HasUuids;

    protected $fillable = ['journey_id', 'position', 'kind', 'title', 'trip_id', 'tour_data', 'plan', 'loaded_km', 'drive_s', 'days', 'start_on', 'surcharge_percent', 'priced_signature'];

    protected function casts(): array
    {
        // tour_data: eigene Kopie der Tour, wie sie beim Anlegen der Strecke war (siehe TourSnapshotService::tripData)
        return ['kind' => SectionKind::class, 'tour_data' => 'array', 'plan' => 'array', 'start_on' => 'date:Y-m-d', 'surcharge_percent' => 'float', 'loaded_km' => 'float', 'drive_s' => 'integer', 'days' => 'integer'];
    }

    // Der Plan (mit Kartenlinie) gehört nur in den Einzelabruf der Strecke.
    protected $hidden = ['plan'];

    /** Eigene Angaben der Strecke ohne Tour, sonst null. @return array{loaded_km: float, drive_s: int, days: int}|null */
    public function ownFigures(): ?array
    {
        return $this->tour_data === null && $this->loaded_km !== null
            ? ['loaded_km' => $this->loaded_km, 'drive_s' => (int) $this->drive_s, 'days' => max(1, (int) $this->days)]
            : null;
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(JourneySectionVehicle::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
