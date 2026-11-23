<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ein Halt einer gespeicherten Tour. Die Koordinaten stehen im Ort (places), nie hier. */
class TripStop extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    // Die Koordinaten kommen mit jedem Halt, ohne weitere Abfrage.
    protected $with = ['place'];

    protected function casts(): array
    {
        return ['driver_free' => 'boolean'];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
