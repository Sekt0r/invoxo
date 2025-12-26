<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VatIdentity>
 */
class VatIdentityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_code' => fake()->randomElement(['GB', 'DE', 'FR', 'IT', 'ES']),
            'vat_id' => fake()->bothify('??#########'),
            'status' => fake()->randomElement(['valid', 'invalid', 'pending', 'unknown']),
            'status_updated_at' => fake()->optional()->dateTime(),
            'last_checked_at' => fake()->optional()->dateTime(),
            'name' => fake()->optional()->company(),
            'address' => fake()->optional()->address(),
            'source' => 'vatlayer',
        ];
    }
}
