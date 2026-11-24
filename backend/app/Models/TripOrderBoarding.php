<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Zu- oder Ausstieg ("zustieg" / "ausstieg") eines Fahrtauftrags: Ort, Uhrzeit ("HH:MM") und, wenn bekannt, die Personenzahl; in Reihenfolge der Abholung. */
class TripOrderBoarding extends Model
{
    use HasUuids;

    protected $fillable = ['trip_order_id', 'position', 'kind', 'place', 'time', 'passengers'];

    protected $hidden = ['trip_order_id', 'created_at', 'updated_at'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(TripOrder::class, 'trip_order_id');
    }
}
