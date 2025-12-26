<?php

namespace Tests\Feature;

use App\Jobs\ValidateVatIdentityJob;
use App\Models\Company;
use App\Models\VatIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompanyVatValidationTriggerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent real HTTP calls and ensure jobs are faked
        // FakeVatProvider is automatically used in testing environment, so Http::fake() not needed
        Queue::fake();
        Bus::fake();
    }

    public function test_creating_company_with_vat_id_links_vat_identity(): void
    {
        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // Assert company.vat_identity_id is set
        $this->assertNotNull($company->vat_identity_id);

        // Verify vat_identity exists
        $vatIdentity = VatIdentity::find($company->vat_identity_id);
        $this->assertNotNull($vatIdentity);
        $this->assertEquals('DE', $vatIdentity->country_code);
        $this->assertEquals('DE123456789', $vatIdentity->vat_id);

        // Note: Job scheduling is tested separately - this test only verifies linking
        // The job will be enqueued if vat_identity is stale (last_checked_at is null for new identities)
        // But we don't assert it here to keep this test focused on linking behavior
    }

    public function test_updating_company_vat_id_triggers_enqueue_when_stale(): void
    {
        // Create a stale vat_identity (never checked, so stale)
        // Use normalized VAT ID to ensure VatIdentityLinker finds it
        $vatIdentity = VatIdentity::factory()->stale()->create([
            'country_code' => 'DE',
            'vat_id' => \App\Support\VatId::normalizeVatId('DE987654321'),
            'status' => 'pending',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // Clear any jobs from creation by faking the queue again
        Queue::fake();

        // Update company VAT ID to link to the stale vat_identity
        // Use update() which properly triggers change detection in observers
        $company->update(['vat_id' => 'DE987654321']);

        // Refresh to ensure vat_identity_id is set by observer
        $company->refresh();

        // Debug assertions to diagnose why job isn't pushed
        $this->assertNotNull($company->vat_identity_id, 'vat_identity_id should be set');
        $this->assertEquals($vatIdentity->id, $company->vat_identity_id, 'vat_identity_id should match stale vat_identity');

        // Should have enqueued validation job exactly once for stale vat_identity
        Queue::assertPushed(ValidateVatIdentityJob::class, 1);
        Queue::assertPushed(ValidateVatIdentityJob::class, function ($job) use ($vatIdentity) {
            return $job->vatIdentityId === $vatIdentity->id;
        });

        // Company should be linked to vat_identity
        $this->assertEquals($vatIdentity->id, $company->vat_identity_id);
    }

    public function test_updating_company_vat_id_does_not_enqueue_when_fresh(): void
    {
        // Create a fresh vat_identity (checked within 30 days)
        $vatIdentity = VatIdentity::factory()->fresh()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE987654321',
            'status' => 'valid',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // Clear any jobs from creation
        Queue::fake();

        // Update company VAT ID to link to the fresh vat_identity
        $company->update([
            'vat_id' => 'DE987654321',
        ]);

        // Should NOT enqueue because validation is fresh (not stale)
        Queue::assertNotPushed(ValidateVatIdentityJob::class);

        // Company should still be linked to vat_identity
        $company->refresh();
        $this->assertEquals($vatIdentity->id, $company->vat_identity_id);
    }

    public function test_removing_vat_id_unlinks_vat_identity(): void
    {
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // Clear any jobs from creation
        Queue::fake();

        // Remove VAT ID (set to null)
        $company->update([
            'vat_id' => null,
        ]);

        // Should not enqueue validation job
        Queue::assertNothingPushed();

        // Company should be unlinked from vat_identity
        $company->refresh();
        $this->assertNull($company->vat_identity_id);
    }

    public function test_dedupe_prevents_repeated_enqueues_within_10_minutes(): void
    {
        // Create a stale vat_identity with recent last_enqueued_at (throttled)
        $vatIdentity = VatIdentity::factory()->stale()->recentlyEnqueued()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE987654321',
            'status' => 'pending',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // Clear any jobs from creation
        Queue::fake();

        // Update company VAT ID to link to the vat_identity
        $company->update([
            'vat_id' => 'DE987654321',
        ]);

        // Should NOT enqueue because last_enqueued_at is within 10 minutes (throttled)
        Queue::assertNotPushed(ValidateVatIdentityJob::class);

        // Company should still be linked to vat_identity
        $company->refresh();
        $this->assertEquals($vatIdentity->id, $company->vat_identity_id);
    }

    public function test_updating_company_non_vat_fields_does_not_trigger_enqueue(): void
    {
        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // Clear any jobs from creation
        Queue::fake();

        // Update non-VAT fields
        $company->update([
            'name' => 'New Company Name',
            'base_currency' => 'USD',
            'default_vat_rate' => 20.00,
            'invoice_prefix' => 'INV-NEW-',
        ]);

        // Should not enqueue validation job
        Queue::assertNothingPushed();
    }

    public function test_company_factory_create_returns_immediately(): void
    {
        $startTime = microtime(true);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete quickly (less than 1 second, but allow some margin for CI)
        $this->assertLessThan(2.0, $duration, 'Company::factory()->create() should return immediately');

        // Verify company was created
        $this->assertNotNull($company->id);
    }
}
