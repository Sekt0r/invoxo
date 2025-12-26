<?php

namespace Tests\Feature;

use App\Contracts\VatRatesProviderInterface;
use App\Contracts\VatValidationProviderInterface;
use App\Data\VatValidationResult;
use App\Jobs\ValidateVatIdentityJob;
use App\Models\VatIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VatProviderAbstractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_vat_validation_tests_do_not_call_external_api(): void
    {
        Queue::fake();

        // No API key required - should use fake provider
        $provider = app(VatValidationProviderInterface::class);

        $result = $provider->validate('RO', '123VALID');

        $this->assertInstanceOf(VatValidationResult::class, $result);
        $this->assertEquals('valid', $result->status);
        $this->assertNotNull($result->companyName);
        $this->assertNotNull($result->checkedAt);
    }

    public function test_deterministic_validation_results_valid(): void
    {
        Queue::fake();

        $provider = app(VatValidationProviderInterface::class);

        $result = $provider->validate('RO', '123VALID');

        $this->assertEquals('valid', $result->status);
        $this->assertNotNull($result->companyName);
        $this->assertNotNull($result->companyAddress);
    }

    public function test_deterministic_validation_results_invalid(): void
    {
        Queue::fake();

        $provider = app(VatValidationProviderInterface::class);

        $result = $provider->validate('RO', '123INVALID');

        $this->assertEquals('invalid', $result->status);
        $this->assertNull($result->companyName);
        $this->assertNull($result->companyAddress);
    }

    public function test_deterministic_validation_results_pending(): void
    {
        Queue::fake();

        $provider = app(VatValidationProviderInterface::class);

        $result = $provider->validate('RO', '123PENDING');

        $this->assertEquals('pending', $result->status);
    }

    public function test_deterministic_validation_results_unknown(): void
    {
        Queue::fake();

        $provider = app(VatValidationProviderInterface::class);

        $result = $provider->validate('RO', '123OTHER');

        $this->assertEquals('unknown', $result->status);
    }

    public function test_deterministic_vat_rates(): void
    {
        $provider = app(VatRatesProviderInterface::class);

        $this->assertEquals(19.00, $provider->getStandardRate('RO'));
        $this->assertEquals(19.00, $provider->getStandardRate('DE'));
        $this->assertEquals(20.00, $provider->getStandardRate('FR'));
        $this->assertEquals(20.00, $provider->getStandardRate('XX')); // Default rate
    }

    public function test_queue_tests_remain_deterministic(): void
    {
        Queue::fake();

        $vatIdentity = VatIdentity::create([
            'country_code' => 'RO',
            'vat_id' => '123VALID',
            'status' => 'pending',
        ]);

        ValidateVatIdentityJob::dispatch($vatIdentity->id);

        Queue::assertPushed(ValidateVatIdentityJob::class, function ($job) use ($vatIdentity) {
            return $job->vatIdentityId === $vatIdentity->id;
        });

        // Process the job
        Queue::assertPushed(ValidateVatIdentityJob::class, 1);
    }

    public function test_validate_vat_identity_job_uses_provider_interface(): void
    {
        Queue::fake();

        $vatIdentity = VatIdentity::create([
            'country_code' => 'RO',
            'vat_id' => '123VALID',
            'status' => 'pending',
        ]);

        $job = new ValidateVatIdentityJob($vatIdentity->id);
        $job->handle(app(VatValidationProviderInterface::class));

        $vatIdentity->refresh();
        $this->assertEquals('valid', $vatIdentity->status);
        $this->assertNotNull($vatIdentity->name);
        $this->assertEquals('provider', $vatIdentity->source); // Should not have environment check
    }
}

