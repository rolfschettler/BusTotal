<?php

namespace App\Routing;

use App\Models\Vehicle;

/**
 * Maße und Gewicht eines Fahrzeugs für das Bus-/Lkw-Profil des Routing-Dienstes (Brückenhöhen, Gewichtsgrenzen ...).
 * Leere Werte werden nicht übergeben; der Dienst rechnet dann ohne diese Grenze.
 */
final readonly class VehicleLimits
{
    public function __construct(
        public ?float $heightM = null,
        public ?float $widthM = null,
        public ?float $lengthM = null,
        public ?float $weightT = null,
    ) {}

    public static function fromVehicle(Vehicle $vehicle): self
    {
        return new self(
            $vehicle->height_m,
            $vehicle->width_m,
            $vehicle->length_m,
            $vehicle->gross_weight_kg ? round($vehicle->gross_weight_kg / 1000, 2) : null,
        );
    }

    /** @return array<string, float> nur die gesetzten Grenzen, mit den Namen des Routing-Dienstes */
    public function toRestrictions(): array
    {
        return array_filter([
            'height' => $this->heightM, 'width' => $this->widthM, 'length' => $this->lengthM, 'weight' => $this->weightT,
        ], fn ($v) => $v !== null && $v > 0);
    }
}
