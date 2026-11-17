<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Driver> */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        static $n = 0;
        $n++;

        return [
            'personnel_number' => 'P-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).'-'.fake()->unique()->numerify('###'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'employment_type' => EmploymentType::Vollzeit,
            'license_classes' => ['D', 'DE'],
        ];
    }
}
