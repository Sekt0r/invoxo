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
            'base_currency' => fake()->randomLetter(),
            'default_vat_rate' => fake()->randomFloat(2, 0, 999.99),
            'invoice_prefix' => fake()->word(),
        ];
    }
}
