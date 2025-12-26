<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\VatIdentity;
use App\Services\VatDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceVatPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_endpoint_returns_decision_for_own_client(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => null,
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => null,
        ]);

        $response = $this->actingAs($user)->getJson(route('invoices.vat-preview') . '?client_id=' . $client->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'client_id',
            'client_vat_status',
            'tax_treatment',
            'vat_rate',
            'reason_text',
            'can_issue',
            'block_reason',
        ]);

        $data = $response->json();
        $this->assertEquals($client->id, $data['client_id']);
        $this->assertEquals('DOMESTIC', $data['tax_treatment']);
        $this->assertEquals('19.00', $data['vat_rate']);
        $this->assertTrue($data['can_issue']);
    }

    public function test_preview_endpoint_forbids_other_company_client(): void
    {
        Queue::fake();

        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($userA)->getJson(route('invoices.vat-preview', ['client_id' => $clientB->id]));

        $response->assertStatus(404);
    }

    public function test_preview_shows_correct_domestic_treatment(): void
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
            'vat_id' => null,
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => null,
        ]);

        // Verify using VatDecisionService directly
        $decisionService = new VatDecisionService();
        $decision = $decisionService->decide($company, $client);

        $response = $this->actingAs($user)->getJson(route('invoices.vat-preview') . '?client_id=' . $client->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals($decision->taxTreatment, $data['tax_treatment']);
        $this->assertEquals(number_format($decision->vatRate, 2, '.', ''), $data['vat_rate']);
    }

    public function test_preview_shows_reverse_charge_when_valid_vat_id(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'country_code' => 'RO',
            'vat_id' => null,
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => now(),
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // Refresh to ensure relationships are loaded
        $client->refresh();
        $client->load('vatIdentity');

        // Verify client belongs to correct company
        $this->assertEquals($company->id, $client->company_id);

        $response = $this->actingAs($user)->getJson(route('invoices.vat-preview') . '?client_id=' . $client->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('EU_B2B_RC', $data['tax_treatment']);
        $this->assertEquals('0.00', $data['vat_rate']);
        $this->assertEquals('valid', $data['client_vat_status']);
        $this->assertTrue($data['can_issue']);
    }

    public function test_preview_shows_warning_when_reverse_charge_needs_validation(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'country_code' => 'RO',
            'vat_id' => null,
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'pending',
            'last_checked_at' => null,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // Refresh to ensure relationships are loaded
        $client->refresh();
        $client->load('vatIdentity');

        // Verify client belongs to correct company
        $this->assertEquals($company->id, $client->company_id);

        $response = $this->actingAs($user)->getJson(route('invoices.vat-preview') . '?client_id=' . $client->id);

        $response->assertOk();
        $data = $response->json();
        // When VAT ID is pending, reverse charge cannot be applied, so it falls back to B2C
        $this->assertEquals('EU_B2C', $data['tax_treatment']);
        $this->assertEquals('pending', $data['client_vat_status']);
        // Can issue because it's not reverse charge
        $this->assertTrue($data['can_issue']);
    }

    public function test_preview_requires_client_id(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson(route('invoices.vat-preview'));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['client_id']);
    }
}

