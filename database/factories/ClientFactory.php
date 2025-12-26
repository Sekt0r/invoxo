<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $country = fake()->randomElement(['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'RO', 'PL']);

        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            // Use a valid 2-letter ISO country code (randomLetter() produces invalid 1-letter codes).
            'country_code' => $country,
            // Keep a non-null VAT ID by default since multiple issuance/VAT-gating tests
            // rely on the "client has vat_id but no linked VatIdentity yet" => status=unknown/pending.
            'vat_id' => $country . fake()->numerify('#########'),
        ];
    }
}
