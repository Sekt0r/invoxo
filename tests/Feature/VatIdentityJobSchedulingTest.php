<?php

namespace Tests\Feature;

use App\Jobs\ValidateVatIdentityJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\VatIdentity;
use App\Services\VatIdentityResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VatIdentityJobSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_repeated_triggers_in_short_time_do_not_enqueue_duplicates_for_pending(): void
    {

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'pending',
            'last_enqueued_at' => now(), // Recently enqueued (within throttle period)
        ]);

        $resolver = new VatIdentityResolver();

        // Try to resolve multiple times in quick succession
        $company = Company::factory()->create(['vat_id' => null]); // No VAT ID to avoid observer job
        $client1 = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);
        $client2 = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $resolver->resolveForClient($client1);
        $resolver->resolveForClient($client2);

        // Should not enqueue additional jobs (throttled - last_enqueued_at is recent)
        Queue::assertNotPushed(ValidateVatIdentityJob::class);
    }

    public function test_repeated_triggers_enqueue_if_pending_but_old_enqueue(): void
    {

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'pending',
            'last_enqueued_at' => Carbon::now()->subMinutes(15), // Old enough to allow re-enqueue
        ]);

        $resolver = new VatIdentityResolver();

        $company = Company::factory()->create(['vat_id' => null]); // No VAT ID to avoid observer job
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $resolver->resolveForClient($client);

        // Should enqueue because last_enqueued_at is old enough
        Queue::assertPushed(ValidateVatIdentityJob::class, 1);
    }

    public function test_manual_recheck_forces_enqueue_when_allowed(): void
    {

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => now(), // Fresh validation
            'last_enqueued_at' => Carbon::now()->subMinutes(15), // Old enough to allow manual recheck
        ]);

        $resolver = new VatIdentityResolver();

        // Manual recheck should force enqueue
        $result = $resolver->manualRecheck($vatIdentity);

        $this->assertTrue($result);
        Queue::assertPushed(ValidateVatIdentityJob::class, 1);

        // Verify last_enqueued_at was updated
        $vatIdentity->refresh();
        $this->assertNotNull($vatIdentity->last_enqueued_at);
        $this->assertTrue($vatIdentity->last_enqueued_at->isAfter(Carbon::now()->subMinute()));
    }

    public function test_manual_recheck_respects_throttle(): void
    {

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => now(),
            'last_enqueued_at' => Carbon::now()->subMinutes(5), // Too recent (within throttle period)
        ]);

        $resolver = new VatIdentityResolver();

        // Manual recheck should be throttled
        $result = $resolver->manualRecheck($vatIdentity);

        $this->assertFalse($result);
        Queue::assertNotPushed(ValidateVatIdentityJob::class);

        // last_enqueued_at should not be updated
        $vatIdentity->refresh();
        $this->assertTrue($vatIdentity->last_enqueued_at->isBefore(Carbon::now()->subMinutes(4)));
    }

    public function test_manual_recheck_works_if_never_enqueued(): void
    {

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => now(),
            'last_enqueued_at' => null, // Never enqueued
        ]);

        $resolver = new VatIdentityResolver();

        // Manual recheck should work if never enqueued
        $result = $resolver->manualRecheck($vatIdentity);

        $this->assertTrue($result);
        Queue::assertPushed(ValidateVatIdentityJob::class, 1);
    }

    public function test_resolve_enqueues_if_stale_validation(): void
    {

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => Carbon::now()->subDays(31), // Stale (older than 30 days)
            'last_enqueued_at' => null,
        ]);

        $resolver = new VatIdentityResolver();

        $company = Company::factory()->create(['vat_id' => null]); // No VAT ID to avoid observer job
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $result = $resolver->resolveForClient($client);

        // Should enqueue because validation is stale
        Queue::assertPushed(ValidateVatIdentityJob::class, 1);

        // Verify last_enqueued_at was updated (use returned instance which should have been refreshed)
        $this->assertNotNull($result->last_enqueued_at);
    }

    public function test_resolve_does_not_enqueue_if_fresh_validation(): void
    {

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => Carbon::now()->subDays(5), // Fresh (less than 30 days)
            'last_enqueued_at' => null,
        ]);

        $resolver = new VatIdentityResolver();

        $company = Company::factory()->create(['vat_id' => null]); // No VAT ID to avoid observer job
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789', // Same as vatIdentity above - should find existing
        ]);

        $result = $resolver->resolveForClient($client);

        // Should not enqueue because validation is fresh (not stale)
        Queue::assertNotPushed(ValidateVatIdentityJob::class);

        // Should have found and linked to existing vatIdentity
        $this->assertEquals($vatIdentity->id, $result->id);
    }
}

