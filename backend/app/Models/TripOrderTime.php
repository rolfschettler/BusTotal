<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Rückerfassung: Zeile aus „Zeiten und Kilometer“. Das Änderungsprotokoll schreibt der TripReportService als Ganzes. */
class TripOrderTime extends Model
{
    use HasUuids;

    protected $fillable = ['trip_order_id', 'position', 'event', 'date', 'place', 'time', 'km', 'leg'];

    protected $hidden = ['id', 'trip_order_id', 'position', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'km' => 'integer'];
    }
}
