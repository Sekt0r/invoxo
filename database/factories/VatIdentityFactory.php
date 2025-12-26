<?php

namespace Database\Factories;

use App\Models\VatIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

class VatIdentityFactory extends Factory
{
    protected $model = VatIdentity::class;

    public function definition(): array
    {
        $country = fake()->randomElement(['DE', 'FR', 'IT', 'ES', 'GB']);
        return [
            'country_code' => $country,
            'vat_id' => $country . fake()->numerify('#########'),
            // deterministic defaults
            'status' => 'pending',
            'status_updated_at' => null,
            'last_checked_at' => null,
            'last_enqueued_at' => null,

            'name' => null,
            'address' => null,
            'source' => 'vatlayer',
        ];
    }

    /**
     * VAT was checked recently → NOT stale
     */
    public function fresh(): static
    {
        return $this->state(fn() => [
            'last_checked_at' => now()->subDays(5),
        ]);
    }

    /**
     * VAT check is old → stale
     */
    public function stale(): static
    {
        return $this->state(fn() => [
            'last_checked_at' => now()->subDays(31),
        ]);
    }

    /**
     * Recently enqueued → throttled
     */
    public function recentlyEnqueued(): static
    {
        return $this->state(fn() => [
            'last_enqueued_at' => now()->subMinutes(5),
        ]);
    }

    /**
     * Enqueued long ago → allowed again
     */
    public function enqueuedLongAgo(): static
    {
        return $this->state(fn() => [
            'last_enqueued_at' => now()->subMinutes(30),
        ]);
    }
}
