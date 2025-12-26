<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => \App\Models\Company::factory(),
            'currency' => 'EUR',
            'iban' => fake()->regexify('[A-Z]{2}[0-9]{2}[A-Z0-9]{20}'),
            'nickname' => fake()->optional()->words(2, true),
            'is_default' => false,
        ];
    }
}
