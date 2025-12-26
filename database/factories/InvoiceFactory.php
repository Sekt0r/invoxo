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
            'status' => 'draft',
            'currency' => 'EUR', // Default currency; should be set from bank accounts in real usage
            // Draft invoices: number is null (assigned on issue), dates are null (set on issue)
            'number' => null,
            'issue_date' => null,
            'due_date' => null,
            // These have database defaults, so we let them apply
            // tax_treatment, vat_rate, vat_reason_text, subtotal_minor, vat_minor, total_minor
            // are all defaulted in migration and will be overwritten on issue anyway
        ];
    }

    /**
     * Create an issued invoice with realistic data.
     * Use this state if you need a fully-issued invoice for testing.
     */
    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'issued',
            // Use Carbon's "now()" so this remains deterministic under Carbon::setTestNow().
            // Keep issue_date year consistent with the number's year.
            'number' => 'INV-' . now()->year . '-' . str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->copy()->addDays(14)->toDateString(),
            'tax_treatment' => 'DOMESTIC',
            'vat_rate' => fake()->randomFloat(2, 0, 27),
            'subtotal_minor' => fake()->numberBetween(10000, 100000),
            'vat_minor' => fake()->numberBetween(1000, 20000),
            'total_minor' => fake()->numberBetween(11000, 120000),
        ]);
    }
}
