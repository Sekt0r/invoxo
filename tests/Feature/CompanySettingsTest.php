<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_from_settings(): void
    {
        $response = $this->get(route('settings.company.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_view_settings_page(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('settings.company.edit'));

        $response->assertOk();
        $response->assertViewIs('settings.company');
        $response->assertViewHas('company', $company);
        $response->assertSee('Test Company');
    }

    public function test_user_can_update_own_company_and_see_success_banner(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'name' => 'Old Company',
            'country_code' => 'DE',
            'vat_id' => null,
            'default_vat_rate' => 19.0,
            'invoice_prefix' => 'OLD',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => 'New Company Name',
            'country_code' => 'fr', // Lowercase to test normalization
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Main St',
            'city' => 'Paris',
            'postal_code' => '75001',
            'vat_id' => 'FR12345678901',
            'default_vat_rate' => 20.0,
            'invoice_prefix' => ' INV- ', // With spaces to test normalization
        ]);

        $response->assertRedirect(route('settings.company.edit'));
        $response->assertSessionHas('status', 'saved');

        // Verify values persist in database
        $this->assertDatabaseHas('companies', [
            'id' => $user->company_id,
            'name' => 'New Company Name',
            'country_code' => 'FR', // Should be uppercased
            'vat_id' => 'FR12345678901',
            'default_vat_rate' => 20.0,
            'invoice_prefix' => 'INV-', // Should be trimmed
        ]);

        // Verify company object is updated
        $company->refresh();
        $this->assertEquals('New Company Name', $company->name);
        $this->assertEquals('FR', $company->country_code);
        $this->assertEquals('FR12345678901', $company->vat_id);
        $this->assertEquals(20.0, (float)$company->default_vat_rate);
        $this->assertEquals('INV-', $company->invoice_prefix);
    }

    public function test_user_cannot_update_other_company(): void
    {
        $companyA = Company::factory()->create([
            'name' => 'Company A',
            'country_code' => 'DE',
            'default_vat_rate' => 19.0,
            'invoice_prefix' => 'INVA',
        ]);
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create([
            'name' => 'Company B',
            'country_code' => 'FR',
            'default_vat_rate' => 20.0,
            'invoice_prefix' => 'INVB',
        ]);

        // User A tries to update, but controller uses $request->user()->company
        // So it will update Company A (user's own company), not Company B
        $response = $this->actingAs($userA)->put(route('settings.company.update'), [
            'name' => 'Hacked Company',
            'country_code' => 'GB',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'vat_id' => null,
            'default_vat_rate' => 25.0,
            'invoice_prefix' => 'HACK',
        ]);

        $response->assertRedirect(route('settings.company.edit'));
        $response->assertSessionHas('status', 'saved');

        // Verify Company A was updated (user's own company)
        $companyA->refresh();
        $this->assertEquals('Hacked Company', $companyA->name);
        $this->assertEquals('GB', $companyA->country_code);

        // Verify Company B was NOT updated
        $companyB->refresh();
        $this->assertEquals('Company B', $companyB->name);
        $this->assertEquals('FR', $companyB->country_code);
        $this->assertEquals(20.0, (float)$companyB->default_vat_rate);
        $this->assertEquals('INVB', $companyB->invoice_prefix);
    }

    public function test_when_tax_rate_exists_default_rate_input_is_not_rendered_and_value_displayed(): void
    {
        $taxRate = TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
            'fetched_at' => now(),
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('settings.company.edit'));

        $response->assertOk();
        $response->assertSee('Official Standard VAT Rate');
        $response->assertSee('19.00%');
        $response->assertSee('Source: vatlayer');
        // Assert editable input is not rendered (hidden input exists, but no visible input with id)
        $html = $response->getContent();
        $this->assertStringContainsString('type="hidden" name="default_vat_rate"', $html);
        $this->assertStringNotContainsString('id="default_vat_rate"', $html);
    }

    public function test_when_no_tax_rate_exists_default_rate_input_is_editable(): void
    {
        $company = Company::factory()->create([
            'country_code' => 'XX', // Non-existent country code
            'default_vat_rate' => 15.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('settings.company.edit'));

        $response->assertOk();
        $response->assertSee('Standard VAT Rate (Manual Fallback)');
        $response->assertSee('name="default_vat_rate"', false); // Assert input is rendered
        $response->assertSee('No official VAT rate available yet for this country');
    }

    public function test_posting_update_with_override_disabled_nulls_override_rate(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_override_enabled' => true,
            'vat_override_rate' => 25.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        // Explicitly disable override (send 0/false)
        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => $company->country_code,
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Main St',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'vat_id' => $company->vat_id,
            'invoice_prefix' => $company->invoice_prefix,
            'vat_override_enabled' => '0', // Explicitly disable override
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        $company->refresh();
        $this->assertFalse($company->vat_override_enabled);
        $this->assertNull($company->vat_override_rate);
    }

    public function test_tampering_default_vat_rate_is_ignored_when_official_exists(): void
    {
        Queue::fake();

        $taxRate = TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $originalRate = $company->default_vat_rate;

        // Try to tamper with default_vat_rate
        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => 'DE',
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Main St',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'vat_id' => $company->vat_id,
            'default_vat_rate' => 99.99, // Tampered value
            'invoice_prefix' => $company->invoice_prefix,
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        $company->refresh();
        // Should remain at official rate (19.00), not the tampered value
        $this->assertEquals(19.00, (float)$company->default_vat_rate);
        $this->assertNotEquals(99.99, (float)$company->default_vat_rate);
    }

    public function test_company_settings_rejects_invalid_country_code(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'country_code' => 'DE',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => 'XX', // Invalid country code
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Main St',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'vat_id' => $company->vat_id,
            'invoice_prefix' => $company->invoice_prefix,
        ]);

        $response->assertSessionHasErrors('country_code');
        $response->assertRedirect();

        // Verify company was not updated
        $company->refresh();
        $this->assertEquals('DE', $company->country_code);
    }

    public function test_company_settings_view_renders_datalist(): void
    {
        $company = Company::factory()->create(['country_code' => 'DE']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('settings.company.edit'));

        $response->assertOk();
        $response->assertSee('id="country-options"', false);
        $response->assertSee('value="RO"', false);
        $response->assertSee('Romania', false);
        $response->assertSee('value="DE"', false);
        $response->assertSee('Germany', false);
    }
}
