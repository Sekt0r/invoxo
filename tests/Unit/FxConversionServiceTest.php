<?php

namespace Tests\Unit;

use App\Models\FxRate;
use App\Services\FxConversionService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FxConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FxConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FxConversionService();
    }

    public function test_convert_same_currency_returns_same_amount(): void
    {
        $result = $this->service->convertMinor(10000, 'EUR', 'EUR');

        $this->assertEquals(10000, $result);
    }

    public function test_convert_eur_to_usd(): void
    {
        // Seed rate: EUR -> USD = 1.10 (meaning 1 EUR = 1.10 USD)
        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.10,
            'as_of_date' => '2025-12-25',
            'source' => 'ecb',
        ]);

        // 100.00 EUR * 1.10 = 110.00 USD
        // 10000 minor units * 1.10 = 11000 minor units
        $result = $this->service->convertMinor(10000, 'EUR', 'USD', Carbon::parse('2025-12-25'));

        $this->assertEquals(11000, $result);
    }

    public function test_convert_usd_to_eur(): void
    {
        // Seed rate: EUR -> USD = 1.10
        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.10,
            'as_of_date' => '2025-12-25',
            'source' => 'ecb',
        ]);

        // 110.00 USD / 1.10 = 100.00 EUR
        // 11000 minor units / 1.10 = 10000 minor units
        $result = $this->service->convertMinor(11000, 'USD', 'EUR', Carbon::parse('2025-12-25'));

        $this->assertEquals(10000, $result);
    }

    public function test_convert_usd_to_ron_via_eur(): void
    {
        // Seed rates
        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.10,
            'as_of_date' => '2025-12-25',
            'source' => 'ecb',
        ]);

        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'RON',
            'rate' => 4.95,
            'as_of_date' => '2025-12-25',
            'source' => 'ecb',
        ]);

        // 110.00 USD -> EUR: 110 / 1.10 = 100 EUR
        // 100 EUR -> RON: 100 * 4.95 = 495 RON
        // Formula: rate(EUR->RON) / rate(EUR->USD) = 4.95 / 1.10 = 4.5
        // 11000 * 4.5 = 49500 minor units
        $result = $this->service->convertMinor(11000, 'USD', 'RON', Carbon::parse('2025-12-25'));

        $this->assertEquals(49500, $result);
    }

    public function test_convert_throws_when_rate_missing(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('FX rate not available');

        $this->service->convertMinor(10000, 'EUR', 'USD', Carbon::parse('2025-12-25'));
    }

    public function test_get_latest_date_uses_prior_date_when_exact_not_found(): void
    {
        // Create rate for 2025-12-24
        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.10,
            'as_of_date' => '2025-12-24',
            'source' => 'ecb',
        ]);

        // Request conversion for 2025-12-25 (weekend/holiday), should use 2025-12-24
        $result = $this->service->convertMinor(10000, 'EUR', 'USD', Carbon::parse('2025-12-25'));

        $this->assertEquals(11000, $result);
    }

    public function test_get_latest_date_returns_latest_available_date(): void
    {
        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.10,
            'as_of_date' => '2025-12-24',
            'source' => 'ecb',
        ]);

        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.11,
            'as_of_date' => '2025-12-23',
            'source' => 'ecb',
        ]);

        $latestDate = $this->service->getLatestDate();

        $this->assertEquals('2025-12-24', $latestDate);
    }

    public function test_get_latest_date_returns_null_when_no_rates(): void
    {
        $latestDate = $this->service->getLatestDate();

        $this->assertNull($latestDate);
    }

    public function test_convert_uses_latest_rate_when_asof_null(): void
    {
        // Create multiple dates, latest should be used
        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.10,
            'as_of_date' => '2025-12-24',
            'source' => 'ecb',
        ]);

        FxRate::create([
            'base_currency' => 'EUR',
            'quote_currency' => 'USD',
            'rate' => 1.12,
            'as_of_date' => '2025-12-25',
            'source' => 'ecb',
        ]);

        // Should use 1.12 from 2025-12-25
        $result = $this->service->convertMinor(10000, 'EUR', 'USD', null);

        $this->assertEquals(11200, $result);
    }
}
