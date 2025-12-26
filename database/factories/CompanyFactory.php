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
        $country = fake()->randomElement(['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'RO', 'PL']);

        return [
            'name' => fake()->name(),
            // Use a valid 2-letter ISO country code (randomLetter() produces invalid 1-letter codes).
            'country_code' => $country,
            // Default: no VAT ID (many companies won't have it). Tests that need VAT set it explicitly.
            'vat_id' => null,
            'registration_number' => fake()->bothify('??#######'), // Random alphanumeric
            'tax_identifier' => fake()->bothify('??#######'),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'default_vat_rate' => fake()->randomFloat(2, 0, 999.99),
            // Stable default used throughout the product/tests.
            'invoice_prefix' => 'INV',
        ];
    }
}
