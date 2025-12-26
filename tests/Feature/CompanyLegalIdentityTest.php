<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyLegalIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_settings_required_fields_enforced_registration_number(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => $company->country_code,
            'registration_number' => '', // Missing
            'tax_identifier' => 'TEST123',
            'address_line1' => 'Test Street',
            'city' => 'Test City',
            'postal_code' => '12345',
            'invoice_prefix' => 'INV-',
        ]);

        $response->assertSessionHasErrors('registration_number');
    }

    public function test_company_settings_required_fields_enforced_tax_identifier(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => $company->country_code,
            'registration_number' => 'TEST123',
            'tax_identifier' => '', // Missing
            'address_line1' => 'Test Street',
            'city' => 'Test City',
            'postal_code' => '12345',
            'invoice_prefix' => 'INV-',
        ]);

        $response->assertSessionHasErrors('tax_identifier');
    }

    public function test_company_settings_required_fields_enforced_address_line1(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => $company->country_code,
            'registration_number' => 'TEST123',
            'tax_identifier' => 'TEST456',
            'address_line1' => '', // Missing
            'city' => 'Test City',
            'postal_code' => '12345',
            'invoice_prefix' => 'INV-',
        ]);

        $response->assertSessionHasErrors('address_line1');
    }

    public function test_company_settings_required_fields_enforced_city(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => $company->country_code,
            'registration_number' => 'TEST123',
            'tax_identifier' => 'TEST456',
            'address_line1' => 'Test Street',
            'city' => '', // Missing
            'postal_code' => '12345',
            'invoice_prefix' => 'INV-',
        ]);

        $response->assertSessionHasErrors('city');
    }

    public function test_company_settings_required_fields_enforced_postal_code(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => $company->country_code,
            'registration_number' => 'TEST123',
            'tax_identifier' => 'TEST456',
            'address_line1' => 'Test Street',
            'city' => 'Test City',
            'postal_code' => '', // Missing
            'invoice_prefix' => 'INV-',
        ]);

        $response->assertSessionHasErrors('postal_code');
    }

    public function test_client_fields_optional(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'RO',
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'country_code' => 'RO',
        ]);
    }

    public function test_dynamic_label_plumbing_present_company_settings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('settings.company.edit'));

        $response->assertSee('window.IDENTITY_LABELS');
        $response->assertSee('label_registration_number');
        $response->assertSee('label_tax_identifier');
        $response->assertSee('updateIdentityLabels');
    }

    public function test_dynamic_label_plumbing_present_client_create(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('clients.create'));

        $response->assertSee('window.IDENTITY_LABELS');
        $response->assertSee('label_registration_number');
        $response->assertSee('label_tax_identifier');
        $response->assertSee('updateIdentityLabels');
    }

    public function test_company_persistence(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Ensure company has a default VAT rate for validation
        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'provider',
        ]);

        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => 'Test Company',
            'country_code' => 'RO',
            'registration_number' => 'J12/123/2020',
            'tax_identifier' => 'RO12345678',
            'address_line1' => 'Str. Test 1',
            'address_line2' => 'Building A',
            'city' => 'Bucharest',
            'postal_code' => '010001',
            'invoice_prefix' => 'INV-',
        ]);

        $response->assertRedirect();
        $company->refresh();

        $this->assertEquals('J12/123/2020', $company->registration_number);
        $this->assertEquals('RO12345678', $company->tax_identifier);
        $this->assertEquals('Str. Test 1', $company->address_line1);
        $this->assertEquals('Building A', $company->address_line2);
        $this->assertEquals('Bucharest', $company->city);
        $this->assertEquals('010001', $company->postal_code);
    }

    public function test_client_persistence(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'RO',
            'registration_number' => 'J12/123/2020',
            'tax_identifier' => 'RO12345678',
            'address_line1' => 'Str. Client 1',
            'city' => 'Bucharest',
            'postal_code' => '010001',
        ]);

        $response->assertRedirect(route('clients.index'));

        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'country_code' => 'RO',
            'registration_number' => 'J12/123/2020',
            'tax_identifier' => 'RO12345678',
            'address_line1' => 'Str. Client 1',
            'city' => 'Bucharest',
            'postal_code' => '010001',
        ]);
    }
}
