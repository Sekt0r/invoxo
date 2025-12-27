<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompanyVatOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Prevent actual job execution
    }

    public function test_country_change_does_not_auto_update_default_rate(): void
    {
        // Note: default_vat_rate is always user-editable and never auto-updated on country change
        // This ensures users maintain control over their country VAT rate setting

        // Create a tax rate for the new country
        TaxRate::create([
            'country_code' => 'FR',
            'tax_type' => 'vat',
            'standard_rate' => 20.00,
            'source' => 'vatlayer',
        ]);

        $user = User::factory()->create();
        $company = $user->company;
        $company->update([
            'country_code' => 'DE',
            'default_vat_rate' => 19.00,
            'vat_override_enabled' => false,
        ]);

        // Update company country to FR
        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => 'FR',
            'vat_id' => $company->vat_id,
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Street',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'default_vat_rate' => $company->default_vat_rate, // User-provided, not auto-updated
            'invoice_prefix' => $company->invoice_prefix,
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        // Assert default_vat_rate remains user-provided (not auto-updated to FR's official rate)
        $company->refresh();
        $this->assertEquals('FR', $company->country_code);
        $this->assertEquals(19.00, (float)$company->default_vat_rate); // Preserved, not auto-updated
        $this->assertFalse($company->vat_override_enabled);
    }

    public function test_country_change_does_not_change_override_rate_when_override_enabled(): void
    {
        TaxRate::create([
            'country_code' => 'FR',
            'tax_type' => 'vat',
            'standard_rate' => 20.00,
            'source' => 'vatlayer',
        ]);

        $user = User::factory()->create();
        $company = $user->company;
        $company->update([
            'country_code' => 'DE',
            'default_vat_rate' => 19.00,
            'vat_override_enabled' => true,
            'vat_override_rate' => 25.00,
        ]);

        $originalOverrideRate = $company->vat_override_rate;

        // Update company country to FR
        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => 'FR',
            'vat_id' => $company->vat_id,
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Street',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'default_vat_rate' => $company->default_vat_rate,
            'vat_override_enabled' => true,
            'vat_override_rate' => $company->vat_override_rate,
            'invoice_prefix' => $company->invoice_prefix,
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        // Assert override rate unchanged and banner flash present
        $company->refresh();
        $this->assertEquals('FR', $company->country_code);
        $this->assertEquals($originalOverrideRate, $company->vat_override_rate);
        $this->assertTrue($company->vat_override_enabled);

        // Follow redirect and check for banner flash
        $response = $this->actingAs($user)->get(route('settings.company.edit'));
        $response->assertSee('Country changed from DE to FR');
        $response->assertSee('Keep override enabled?');
    }

    public function test_override_decision_disable_sets_official_rate_and_disables_override(): void
    {
        TaxRate::create([
            'country_code' => 'FR',
            'tax_type' => 'vat',
            'standard_rate' => 20.00,
            'source' => 'vatlayer',
        ]);

        $user = User::factory()->create();
        $company = $user->company;
        $company->update([
            'country_code' => 'FR',
            'default_vat_rate' => 19.00,
            'vat_override_enabled' => true,
            'vat_override_rate' => 25.00,
        ]);

        // Set session flags to simulate country change
        $this->actingAs($user)->withSession([
            'company.override_country_changed' => true,
            'company.override_country_changed_from' => 'DE',
            'company.override_country_changed_to' => 'FR',
        ]);

        // POST disable decision
        $response = $this->actingAs($user)->post(route('settings.company.override-decision'), [
            'decision' => 'disable',
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        // Assert override disabled and default_vat_rate set to official standard
        $company->refresh();
        $this->assertFalse($company->vat_override_enabled);
        $this->assertNull($company->vat_override_rate);
        $this->assertEquals(20.00, (float)$company->default_vat_rate);
    }

    public function test_override_decision_keep_maintains_override(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $company->update([
            'country_code' => 'FR',
            'default_vat_rate' => 19.00,
            'vat_override_enabled' => true,
            'vat_override_rate' => 25.00,
        ]);

        $originalOverrideRate = $company->vat_override_rate;
        $originalDefaultRate = $company->default_vat_rate;

        // Set session flags to simulate country change
        $this->actingAs($user)->withSession([
            'company.override_country_changed' => true,
            'company.override_country_changed_from' => 'DE',
            'company.override_country_changed_to' => 'FR',
        ]);

        // POST keep decision
        $response = $this->actingAs($user)->post(route('settings.company.override-decision'), [
            'decision' => 'keep',
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        // Assert override still enabled and rates unchanged
        $company->refresh();
        $this->assertTrue($company->vat_override_enabled);
        $this->assertEquals($originalOverrideRate, $company->vat_override_rate);
        $this->assertEquals($originalDefaultRate, $company->default_vat_rate);
    }

    public function test_vat_decision_uses_override_when_enabled(): void
    {
        $company = Company::factory()->create([
            'country_code' => 'DE',
            'default_vat_rate' => 19.00,
            'vat_override_enabled' => true,
            'vat_override_rate' => 25.00,
        ]);

        $client = \App\Models\Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE', // Same country (domestic)
        ]);

        $decisionService = new \App\Services\VatDecisionService();
        $decision = $decisionService->decide($company, $client);

        // Should use override rate (25.00) for domestic transaction
        $this->assertEquals('DOMESTIC', $decision->taxTreatment);
        $this->assertEquals(25.00, $decision->vatRate);
    }

    public function test_vat_decision_uses_default_rate_when_override_disabled(): void
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
            'default_vat_rate' => 18.00, // Company's country VAT rate (baseline)
            'vat_override_enabled' => false,
            'vat_override_rate' => null,
        ]);

        $client = \App\Models\Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE', // Same country (domestic)
        ]);

        $decisionService = new \App\Services\VatDecisionService();
        $decision = $decisionService->decide($company, $client);

        // Should use company default_vat_rate (18.00), which represents the country VAT rate
        $this->assertEquals('DOMESTIC', $decision->taxTreatment);
        $this->assertEquals(18.00, $decision->vatRate);
    }

    public function test_vat_decision_falls_back_to_default_when_no_official_rate(): void
    {
        // No tax rate in database for this country
        $company = Company::factory()->create([
            'country_code' => 'XX', // Country without official rate
            'default_vat_rate' => 15.00,
            'vat_override_enabled' => false,
            'vat_override_rate' => null,
        ]);

        $client = \App\Models\Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'XX',
        ]);

        $decisionService = new \App\Services\VatDecisionService();
        $decision = $decisionService->decide($company, $client);

        // Should fall back to company default_vat_rate
        $this->assertEquals('DOMESTIC', $decision->taxTreatment);
        $this->assertEquals(15.00, $decision->vatRate);
    }
}
