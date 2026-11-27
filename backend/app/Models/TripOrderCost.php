<?php

namespace App\Models;

use App\Enums\CostCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Rückerfassung: Kosten unterwegs. Das Änderungsprotokoll schreibt der TripReportService als Ganzes. */
class TripOrderCost extends Model
{
    use HasUuids;

    protected $fillable = ['trip_order_id', 'position', 'category', 'amount', 'receipt_no', 'travel_service', 'charge_customer'];

    protected $hidden = ['id', 'trip_order_id', 'position', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['category' => CostCategory::class, 'amount' => 'decimal:2', 'travel_service' => 'boolean', 'charge_customer' => 'boolean'];
    }
}
