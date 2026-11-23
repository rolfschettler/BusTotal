<?php

namespace App\Models;

use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Fahrzeugbedarf eines Abschnitts: Typ und Anzahl (kein konkretes Fahrzeug, das wählt der Disponent später). */
class JourneySectionVehicle extends Model
{
    use HasUuids;

    protected $fillable = ['journey_section_id', 'vehicle_type', 'vehicle_count'];

    protected function casts(): array
    {
        return ['vehicle_type' => VehicleType::class];
    }
}
