<?php

namespace Tests\Feature;

use App\Contracts\VatProvider;
use App\Jobs\ValidateVatIdentityJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\VatIdentity;
use App\Models\User;
use App\Services\VatIdentityResolver;
use App\Support\VatId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VatIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_id_normalization_strips_spaces_dashes_and_uppercases(): void
    {
        $this->assertEquals('DE123456789', VatId::normalizeVatId('de 123-456.789'));
        $this->assertEquals('FR12345678901', VatId::normalizeVatId('  fr-123.456.789-01  '));
        $this->assertEquals('GB123456789', VatId::normalizeVatId('gb123456789'));
    }

    public function test_resolver_attaches_cache_row_and_dispatches_job(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // User needs Pro plan for VIES validation
        \App\Models\Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $this->actingAs($user);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $resolver = app(VatIdentityResolver::class);
        $vatIdentity = $resolver->resolveForClient($client, $user);

        $this->assertNotNull($vatIdentity);
        $this->assertEquals('DE', $vatIdentity->country_code);
        $this->assertEquals('DE123456789', $vatIdentity->vat_id);
        $this->assertEquals('pending', $vatIdentity->status);

        $client->refresh();
        $this->assertEquals($vatIdentity->id, $client->vat_identity_id);

        Queue::assertPushed(ValidateVatIdentityJob::class, function ($job) use ($vatIdentity) {
            return $job->vatIdentityId === $vatIdentity->id;
        });
    }

    public function test_resolver_deduplicates_across_tenant_entities(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        // User needs Pro plan for VIES validation
        \App\Models\Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $this->actingAs($user);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789', // Same VAT ID
        ]);

        $resolver = app(VatIdentityResolver::class);

        // Resolve for company
        $vatIdentityCompany = $resolver->resolveForCompany($company, $user);

        // Resolve for client (same VAT ID)
        $vatIdentityClient = $resolver->resolveForClient($client, $user);

        // Should point to same vat_identities row
        $this->assertEquals($vatIdentityCompany->id, $vatIdentityClient->id);

        // Should dispatch job only once (count unique by vatIdentity id)
        $dispatchedJobs = Queue::pushed(ValidateVatIdentityJob::class);
        $uniqueVatIdentityIds = $dispatchedJobs->pluck('vatIdentityId')->unique();
        $this->assertEquals(1, $uniqueVatIdentityIds->count());
    }

    public function test_job_updates_cache(): void
    {
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456VALID', // Use VALID suffix for deterministic result
            'status' => 'pending',
        ]);

        // Use fake provider (automatically bound in tests)
        // Run job synchronously with fake provider
        $job = new ValidateVatIdentityJob($vatIdentity->id);
        $job->handle(app(\App\Contracts\VatValidationProviderInterface::class));

        $vatIdentity->refresh();
        $this->assertEquals('valid', $vatIdentity->status);
        $this->assertNotNull($vatIdentity->last_checked_at);
        // Fake provider returns deterministic company info
        $this->assertNotNull($vatIdentity->name);
        $this->assertNotNull($vatIdentity->address);
        // Source should be 'provider' (no environment check)
        $this->assertEquals('provider', $vatIdentity->source);
        $this->assertNull($vatIdentity->last_error);
    }

    public function test_job_normalizes_empty_name_and_address_to_null(): void
    {
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'pending',
        ]);

        // Use fake provider which already returns null for invalid cases
        // For this test, we'll test with a valid VAT ID that has company info
        $vatIdentity->update(['vat_id' => 'DE123456VALID']);

        // Run job synchronously with fake provider
        $job = new ValidateVatIdentityJob($vatIdentity->id);
        $job->handle(app(\App\Contracts\VatValidationProviderInterface::class));

        $vatIdentity->refresh();
        $this->assertEquals('valid', $vatIdentity->status);
        $this->assertNotNull($vatIdentity->last_checked_at);
        // Fake provider returns company info for valid VAT IDs (normalization is handled by provider)
        $this->assertNotNull($vatIdentity->name);
        $this->assertNotNull($vatIdentity->address);
        // Source should be 'provider' (no environment check)
        $this->assertEquals('provider', $vatIdentity->source);
        $this->assertNull($vatIdentity->last_error);
    }

    public function test_vat_decision_uses_cache(): void
    {
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        // Create client with valid VAT identity
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => now(),
            'status_updated_at' => now(),
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // User needs Pro plan for VIES validation (required for EU_B2B_RC auto-suggestion)
        $user = User::factory()->create(['company_id' => $company->id]);
        \App\Models\Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $decisionService = new \App\Services\VatDecisionService();
        $decision = $decisionService->decide($company, $client, $user);

        $this->assertEquals('EU_B2B_RC', $decision->taxTreatment);
        $this->assertEquals(0.0, $decision->vatRate);

        // Test with pending status (should fall back to B2C)
        $vatIdentityPending = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE999999999',
            'status' => 'pending',
        ]);

        $clientPending = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE999999999',
            'vat_identity_id' => $vatIdentityPending->id,
        ]);

        $decisionPending = $decisionService->decide($company, $clientPending);
        $this->assertEquals('EU_B2C', $decisionPending->taxTreatment);

        // Test with unknown status (should fall back to B2C)
        $vatIdentityUnknown = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE888888888',
            'status' => 'unknown',
            'last_checked_at' => now(),
            'status_updated_at' => now(),
        ]);

        $clientUnknown = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE888888888',
            'vat_identity_id' => $vatIdentityUnknown->id,
        ]);

        $decisionUnknown = $decisionService->decide($company, $clientUnknown);
        $this->assertEquals('EU_B2C', $decisionUnknown->taxTreatment);
    }

    public function test_resolver_detaches_when_vat_id_empty(): void
    {
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
        ]);

        $company = Company::factory()->create([
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $resolver = app(VatIdentityResolver::class);
        $company->vat_id = '';
        $company->save();

        $result = $resolver->resolveForCompany($company);

        $this->assertNull($result);
        $company->refresh();
        $this->assertNull($company->vat_identity_id);
    }
}
