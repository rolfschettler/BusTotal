<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /** Fabrik für Testdaten. Nummern setzen Tests bei Bedarf selbst. */
    public function definition(): array
    {
        static $n = 0;
        $n++;

        return [
            'customer_number' => 'T-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT).'-'.fake()->unique()->numerify('####'),
            'type' => CustomerType::Firma,
            'company_name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->numerify('#####'),
            'city' => fake()->city(),
            'country' => 'DE',
        ];
    }

    public function privat(): static
    {
        return $this->state(fn () => [
            'type' => CustomerType::Privat,
            'company_name' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
        ]);
    }
}
