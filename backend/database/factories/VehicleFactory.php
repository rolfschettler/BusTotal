<?php

namespace Database\Factories;

use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        static $n = 0;
        $n++;

        return [
            'fleet_number' => 'B-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT).'-'.fake()->unique()->numerify('###'),
            'license_plate' => 'M-'.fake()->unique()->lexify('??').' '.fake()->numerify('###'),
            'type' => VehicleType::Reisebus,
            'manufacturer' => 'Setra',
            'model' => 'S 515 HD',
            'seats' => 49,
        ];
    }
}
