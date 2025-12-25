<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use App\Models\Public;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'public_id' => Public::factory(),
            'share_token' => fake()->regexify('[A-Za-z0-9]{64}'),
            'number' => fake()->word(),
            'status' => fake()->word(),
            'issue_date' => fake()->date(),
            'due_date' => fake()->date(),
            'tax_treatment' => fake()->word(),
            'vat_rate' => fake()->randomFloat(2, 0, 999.99),
            'vat_reason_text' => fake()->word(),
            'subtotal_minor' => fake()->numberBetween(-100000, 100000),
            'vat_minor' => fake()->numberBetween(-100000, 100000),
            'total_minor' => fake()->numberBetween(-100000, 100000),
        ];
    }
}
