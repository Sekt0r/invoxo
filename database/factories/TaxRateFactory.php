<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRateFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'country_code' => fake()->randomLetter(),
            'tax_type' => fake()->word(),
            'standard_rate' => fake()->randomFloat(2, 0, 999.99),
            'source' => fake()->word(),
        ];
    }
}
