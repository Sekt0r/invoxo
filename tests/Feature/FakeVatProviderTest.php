<?php

namespace Tests\Feature;

use App\Contracts\VatProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakeVatProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_vat_provider_is_bound_in_testing_environment(): void
    {
        $provider = app(VatProvider::class);

        $this->assertInstanceOf(\App\Services\Vat\FakeVatProvider::class, $provider);
    }

    public function test_fake_provider_validates_vat_deterministically(): void
    {
        $provider = app(VatProvider::class);

        // Valid: length > 5
        $result = $provider->validateVat('DE', '123456789');
        $this->assertTrue($result['valid']);
        $this->assertNotNull($result['company_name']);
        $this->assertNotNull($result['company_address']);

        // Invalid: length <= 5
        $result = $provider->validateVat('DE', '12345');
        $this->assertFalse($result['valid']);

        // Valid even with country prefix
        $result = $provider->validateVat('DE', 'DE123456789');
        $this->assertTrue($result['valid']);
    }

    public function test_fake_provider_returns_hardcoded_rates(): void
    {
        $provider = app(VatProvider::class);

        $result = $provider->rateList();

        $this->assertArrayHasKey('rates', $result);
        $this->assertEquals(21.0, $result['rates']['RO']['standard_rate']);
        $this->assertEquals(19.0, $result['rates']['DE']['standard_rate']);
        $this->assertEquals(20.0, $result['rates']['FR']['standard_rate']);
    }

    public function test_fake_provider_returns_specific_country_rate(): void
    {
        $provider = app(VatProvider::class);

        $result = $provider->rateList('RO');

        $this->assertArrayHasKey('rates', $result);
        $this->assertEquals(21.0, $result['rates']['RO']['standard_rate']);
        $this->assertCount(1, $result['rates']);
    }

    public function test_fake_provider_returns_default_rate_for_unknown_country(): void
    {
        $provider = app(VatProvider::class);

        $result = $provider->rateList('XX');

        $this->assertArrayHasKey('rates', $result);
        $this->assertEquals(20.0, $result['rates']['XX']['standard_rate']); // Default rate
    }
}
