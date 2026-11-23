<?php

namespace App\Models;

use App\Enums\SectionKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Abschnitt eines Reisevorhabens (Hinfahrt, Fahrten vor Ort, Rückfahrt) mit seiner Strecke aus dem Tour-Archiv. */
class JourneySection extends Model
{
    use HasUuids;

    protected $fillable = ['journey_id', 'position', 'kind', 'title', 'trip_id'];

    protected function casts(): array
    {
        return ['kind' => SectionKind::class];
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
