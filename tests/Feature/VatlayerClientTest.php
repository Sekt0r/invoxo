<?php

namespace Tests\Feature;

use App\Services\Vat\ApilayerVatProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VatlayerClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Temporarily bind ApilayerVatProvider for these tests
        config(['services.vatlayer.key' => 'test-api-key']);
    }

    public function test_apilayer_provider_validate_vat_returns_valid_status(): void
    {
        Http::fake([
            'apilayer.net/api/validate*' => Http::response([
                'valid' => true,
                'company_name' => 'Test Company',
                'company_address' => '123 Test St',
            ]),
        ]);

        $provider = new ApilayerVatProvider();
        $result = $provider->validateVat('DE', '123456789');

        $this->assertTrue($result['valid']);
        $this->assertEquals('Test Company', $result['company_name']);
        $this->assertEquals('123 Test St', $result['company_address']);
    }

    public function test_apilayer_provider_validate_vat_returns_invalid_status(): void
    {
        Http::fake([
            'apilayer.net/api/validate*' => Http::response([
                'valid' => false,
            ]),
        ]);

        $provider = new ApilayerVatProvider();
        $result = $provider->validateVat('DE', 'INVALID');

        $this->assertFalse($result['valid']);
    }

    public function test_apilayer_provider_rate_list_returns_rates(): void
    {
        Http::fake([
            'apilayer.net/api/rate_list*' => Http::response([
                'rates' => [
                    'DE' => [
                        'standard_rate' => 19.0,
                        'reduced_rates' => [
                            'food' => 7.0,
                        ],
                    ],
                    'FR' => [
                        'standard_rate' => 20.0,
                    ],
                ],
            ]),
        ]);

        $provider = new ApilayerVatProvider();
        $result = $provider->rateList();

        $this->assertArrayHasKey('rates', $result);
        $this->assertEquals(19.0, $result['rates']['DE']['standard_rate']);
        $this->assertEquals(20.0, $result['rates']['FR']['standard_rate']);
    }
}

