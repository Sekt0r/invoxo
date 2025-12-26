<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'public_id' => (string) Str::uuid(),
            'share_token' => Str::random(48),
            'number' => fake()->word(),
            'status' => 'draft',
            'currency' => 'EUR', // Default currency; should be set from bank accounts in real usage
            'issue_date' => fake()->date(),
            'due_date' => fake()->date(),
            'tax_treatment' => 'DOMESTIC',
            'vat_rate' => fake()->randomFloat(2, 0, 100),
            'vat_reason_text' => fake()->optional()->sentence(),
            'subtotal_minor' => fake()->numberBetween(0, 100000),
            'vat_minor' => fake()->numberBetween(0, 100000),
            'total_minor' => fake()->numberBetween(0, 100000),
        ];
    }
}
