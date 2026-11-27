<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Rückerfassung: Grenzübertritt je Land. Das Änderungsprotokoll schreibt der TripReportService als Ganzes. */
class TripOrderBorder extends Model
{
    use HasUuids;

    protected $fillable = ['trip_order_id', 'position', 'country', 'km_in', 'km_out', 'source'];

    protected $hidden = ['id', 'trip_order_id', 'position', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['km_in' => 'integer', 'km_out' => 'integer'];
    }
}
