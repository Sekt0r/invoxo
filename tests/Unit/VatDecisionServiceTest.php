<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\TaxRate;
use App\Services\VatDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    private VatDecisionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VatDecisionService();
    }

    public function test_effective_rate_uses_override_when_enabled(): void
    {
        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'default_vat_rate' => 15.00, // Fallback rate
            'vat_override_enabled' => true,
            'vat_override_rate' => 25.00, // Override rate
        ]);

        $client = \App\Models\Client::factory()->create([
            'country_code' => 'DE',
            'company_id' => $company->id,
        ]);

        $decision = $this->service->decide($company, $client);

        // Should use override rate (25.00), not official (19.00) or fallback (15.00)
        $this->assertEquals(25.00, $decision->vatRate);
        $this->assertEquals('DOMESTIC', $decision->taxTreatment);
    }

    public function test_effective_rate_uses_default_when_no_override(): void
    {
        // Note: default_vat_rate is the baseline (country VAT rate), not tax_rates table
        // Official rates from tax_rates are informational only, not used for VAT calculation

        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'default_vat_rate' => 15.00, // Company's country VAT rate (baseline)
            'vat_override_enabled' => false,
            'vat_override_rate' => null,
        ]);

        $client = \App\Models\Client::factory()->create([
            'country_code' => 'DE',
            'company_id' => $company->id,
        ]);

        $decision = $this->service->decide($company, $client);

        // Should use company default_vat_rate (15.00), which represents the country VAT rate
        $this->assertEquals(15.00, $decision->vatRate);
        $this->assertEquals('DOMESTIC', $decision->taxTreatment);
    }

    public function test_effective_rate_uses_fallback_when_no_official_exists(): void
    {
        // No TaxRate for this country

        $company = Company::factory()->create([
            'country_code' => 'XX', // No official rate
            'default_vat_rate' => 15.00, // Fallback rate
            'vat_override_enabled' => false,
            'vat_override_rate' => null,
        ]);

        $client = \App\Models\Client::factory()->create([
            'country_code' => 'XX',
            'company_id' => $company->id,
        ]);

        $decision = $this->service->decide($company, $client);

        // Should use fallback rate (15.00)
        $this->assertEquals(15.00, $decision->vatRate);
        $this->assertEquals('DOMESTIC', $decision->taxTreatment);
    }
}

