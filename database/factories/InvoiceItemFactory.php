<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->text(),
            'quantity' => fake()->randomFloat(2, 0, 99999999.99),
            'unit_price_minor' => fake()->numberBetween(-100000, 100000),
            'line_total_minor' => fake()->numberBetween(-100000, 100000),
        ];
    }
}
