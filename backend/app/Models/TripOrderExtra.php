<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Rückerfassung: Zusatzleistung unterwegs. Das Änderungsprotokoll schreibt der TripReportService als Ganzes. */
class TripOrderExtra extends Model
{
    use HasUuids;

    protected $fillable = ['trip_order_id', 'position', 'title', 'quantity', 'place', 'amount', 'payment', 'tax_rate'];

    protected $hidden = ['id', 'trip_order_id', 'position', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tax_rate' => 'decimal:2'];
    }
}
