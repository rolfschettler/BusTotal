<?php

namespace App\Models;

use App\Enums\VehicleType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Abweichende Preise für einen Fahrzeugtyp. Felder ohne Wert (NULL) übernehmen den Standardpreis der Firma. */
class PricingVehicleRate extends Model
{
    use Auditable, HasUuids;

    /** Die Preisfelder, die je Fahrzeugtyp abweichen dürfen. */
    public const FIELDS = ['price_per_km', 'hourly_rate', 'day_rate', 'overnight_rate', 'minimum_price'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'price_per_km' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'day_rate' => 'decimal:2',
            'overnight_rate' => 'decimal:2',
            'minimum_price' => 'decimal:2',
        ];
    }
}
