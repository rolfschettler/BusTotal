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

    protected $fillable = ['journey_id', 'position', 'kind', 'title', 'trip_id', 'loaded_km', 'drive_s', 'days'];

    protected function casts(): array
    {
        return ['kind' => SectionKind::class, 'loaded_km' => 'float', 'drive_s' => 'integer', 'days' => 'integer'];
    }

    /** Eigene Angaben der Strecke ohne Tour, sonst null. @return array{loaded_km: float, drive_s: int, days: int}|null */
    public function ownFigures(): ?array
    {
        return $this->trip_id === null && $this->loaded_km !== null
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
