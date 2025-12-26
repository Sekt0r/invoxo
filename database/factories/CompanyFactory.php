<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'country_code' => fake()->randomLetter(),
            'vat_id' => fake()->word(),
            'registration_number' => fake()->bothify('??#######'), // Random alphanumeric
            'tax_identifier' => fake()->bothify('??#######'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'default_vat_rate' => fake()->randomFloat(2, 0, 999.99),
            'invoice_prefix' => fake()->word(),
        ];
    }
}
