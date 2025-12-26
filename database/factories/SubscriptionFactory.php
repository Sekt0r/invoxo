<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_id' => Plan::factory(),
            'starts_at' => fake()->dateTime(),
            'ends_at' => fake()->optional()->dateTime(),
        ];
    }
}
