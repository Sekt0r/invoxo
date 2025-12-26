<?php

namespace Tests\Feature;

use App\Data\ViesResult;
use App\Jobs\ValidateVatIdentityJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Models\VatIdentity;
use App\Services\VatDecisionService;
use App\Services\ViesVatValidationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ViesClientValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_client_store_sets_valid_status_when_service_returns_valid(): void
    {

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::where('vat_id', 'DE123456789')->first();
        $this->assertNotNull($client->vat_identity_id);

        // Check that vat_identities row was created
        $vatIdentity = VatIdentity::find($client->vat_identity_id);
        $this->assertNotNull($vatIdentity);
        $this->assertEquals('DE', $vatIdentity->country_code);
        $this->assertEquals('DE123456789', $vatIdentity->vat_id);
        $this->assertEquals('pending', $vatIdentity->status);

        // Check that validation job was dispatched
        Queue::assertPushed(ValidateVatIdentityJob::class);
    }

    public function test_client_store_sets_invalid_status(): void
    {

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'DE',
            'vat_id' => 'DE999999999',
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::where('vat_id', 'DE999999999')->first();
        $this->assertNotNull($client->vat_identity_id);

        // Check that vat_identities row was created with pending status
        $vatIdentity = VatIdentity::find($client->vat_identity_id);
        $this->assertEquals('pending', $vatIdentity->status);

        Queue::assertPushed(ValidateVatIdentityJob::class);
    }

    public function test_client_store_sets_unknown_when_service_fails(): void
    {

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::where('vat_id', 'DE123456789')->first();
        $this->assertNotNull($client->vat_identity_id);

        // Check that vat_identities row was created with pending status
        $vatIdentity = VatIdentity::find($client->vat_identity_id);
        $this->assertEquals('pending', $vatIdentity->status);

        Queue::assertPushed(ValidateVatIdentityJob::class);
    }

    public function test_client_store_clears_validation_when_vat_id_empty(): void
    {
        $company = Company::factory()->create(['vat_id' => null]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('clients.store'), [
            'name' => 'Test Client',
            'country_code' => 'DE',
            'vat_id' => '',
        ]);

        $response->assertRedirect(route('clients.index'));

        $client = Client::where('name', 'Test Client')->first();
        $this->assertNull($client->vat_identity_id);

        Queue::assertNotPushed(ValidateVatIdentityJob::class);
    }

    public function test_client_update_refreshes_validation_when_stale(): void
    {

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $vatIdentity = VatIdentity::factory()->stale()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $response = $this->actingAs($user)->put(route('clients.update', $client), [
            'name' => 'Updated Client',
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $response->assertRedirect(route('clients.index'));

        // Job should be dispatched because last_checked_at is older than 30 days
        Queue::assertPushed(ValidateVatIdentityJob::class);
    }

    public function test_vat_decision_requires_valid_status_for_reverse_charge(): void
    {
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        // Test with valid VAT ID
        $vatIdentityValid = VatIdentity::factory()->fresh()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
        ]);

        $clientValid = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentityValid->id,
        ]);
        $clientValid->load('vatIdentity');

        $decisionService = new VatDecisionService();
        $decision = $decisionService->decide($company, $clientValid);

        $this->assertEquals('EU_B2B_RC', $decision->taxTreatment);
        $this->assertEquals(0.0, $decision->vatRate);
        $this->assertStringContainsString('Reverse charge', $decision->reasonText);

        // Test with unknown VAT ID status (should fall back to B2C)
        $vatIdentityUnknown = VatIdentity::factory()->fresh()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE888888888',
            'status' => 'unknown',
        ]);

        $clientUnknown = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE888888888',
            'vat_identity_id' => $vatIdentityUnknown->id,
        ]);
        $clientUnknown->load('vatIdentity');

        $decisionUnknown = $decisionService->decide($company, $clientUnknown);

        $this->assertEquals('EU_B2C', $decisionUnknown->taxTreatment);
        $this->assertEquals(19.00, $decisionUnknown->vatRate);

        // Test with invalid VAT ID (should fall back to B2C)
        $vatIdentityInvalid = VatIdentity::factory()->fresh()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE999999999',
            'status' => 'invalid',
        ]);

        $clientInvalid = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE999999999',
            'vat_identity_id' => $vatIdentityInvalid->id,
        ]);
        $clientInvalid->load('vatIdentity');

        $decisionInvalid = $decisionService->decide($company, $clientInvalid);

        $this->assertEquals('EU_B2C', $decisionInvalid->taxTreatment);
        $this->assertEquals(19.00, $decisionInvalid->vatRate);
    }
}

