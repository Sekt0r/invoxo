<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => fake()->word(),
            'name' => fake()->name(),
            'monthly_price_eur' => fake()->numberBetween(-10000, 10000),
            'invoice_monthly_limit' => fake()->numberBetween(-10000, 10000),
        ];
    }
}
