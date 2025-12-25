<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['code' => 'starter'], [
            'name' => 'Starter',
            'monthly_price_eur' => 900,
            'invoice_monthly_limit' => 20,
        ]);

        Plan::updateOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'monthly_price_eur' => 1500,
            'invoice_monthly_limit' => null,
        ]);

        Plan::updateOrCreate(['code' => 'growth'], [
            'name' => 'Growth',
            'monthly_price_eur' => 2900,
            'invoice_monthly_limit' => null,
        ]);
    }
}
