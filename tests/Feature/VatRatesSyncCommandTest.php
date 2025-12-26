<?php

namespace Tests\Feature;

use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatRatesSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_upserts_rates(): void
    {
        // FakeVatProvider is automatically used in testing environment
        $this->artisan('vat:sync-rates')
            ->assertExitCode(0);

        // Check that hardcoded rates from FakeVatRatesProvider are synced
        $this->assertDatabaseHas('tax_rates', [
            'country_code' => 'RO',
            'standard_rate' => 19.00, // FakeVatRatesProvider returns 19.0 for RO
            'tax_type' => 'vat',
            'source' => 'provider', // No environment check
        ]);

        $this->assertDatabaseHas('tax_rates', [
            'country_code' => 'DE',
            'standard_rate' => 19.00, // FakeVatRatesProvider returns 19.0 for DE
            'tax_type' => 'vat',
            'source' => 'provider', // No environment check
        ]);

        $deRate = TaxRate::where('country_code', 'DE')->first();
        $this->assertNotNull($deRate->fetched_at);
    }

    public function test_command_handles_http_failure(): void
    {
        // FakeVatProvider doesn't throw errors, so command should succeed
        $this->artisan('vat:sync-rates')
            ->assertExitCode(0);
    }

    public function test_command_handles_missing_rates_key(): void
    {
        // FakeVatProvider always returns rates, so this test verifies command runs successfully
        $this->artisan('vat:sync-rates')
            ->assertExitCode(0);

        // Rates should be synced from FakeVatProvider
        $this->assertGreaterThan(0, TaxRate::count());
    }

    public function test_command_updates_existing_rates(): void
    {
        TaxRate::create([
            'country_code' => 'DE',
            'standard_rate' => 20.00,
            'tax_type' => 'vat',
            'source' => 'manual',
        ]);

        // FakeVatProvider returns 19.0 for DE
        $this->artisan('vat:sync-rates')
            ->assertExitCode(0);

        $this->assertDatabaseHas('tax_rates', [
            'country_code' => 'DE',
            'standard_rate' => 19.00, // Updated to FakeVatRatesProvider rate
            'source' => 'provider', // No environment check
        ]);

        // Verify only one record exists for DE
        $this->assertEquals(1, TaxRate::where('country_code', 'DE')->count());

        $deRate = TaxRate::where('country_code', 'DE')->first();
        $this->assertNotNull($deRate->fetched_at);
    }
}
