<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Services\EcbFxRatesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FxRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecb_sync_parses_and_persists_rates(): void
    {
        // Sample ECB XML response structure
        $xmlResponse = '<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <gesmes:subject>Reference rates</gesmes:subject>
    <gesmes:Sender>
        <gesmes:name>European Central Bank</gesmes:name>
    </gesmes:Sender>
    <Cube>
        <Cube time="2025-12-25">
            <Cube currency="USD" rate="1.1000"/>
            <Cube currency="RON" rate="4.9500"/>
            <Cube currency="PLN" rate="4.2500"/>
        </Cube>
    </Cube>
</gesmes:Envelope>';

        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::response($xmlResponse, 200),
        ]);

        $service = app(EcbFxRatesService::class);
        $result = $service->syncDaily();

        $this->assertEquals('2025-12-25', $result['as_of_date']);
        $this->assertGreaterThan(0, $result['inserted'] + $result['updated']);

        // Check USD rate
        $usdRate = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'USD')
            ->whereDate('as_of_date', '2025-12-25')
            ->first();

        $this->assertNotNull($usdRate);
        $this->assertEquals(1.1000, (float)$usdRate->rate);

        // Check RON rate
        $ronRate = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'RON')
            ->whereDate('as_of_date', '2025-12-25')
            ->first();

        $this->assertNotNull($ronRate);
        $this->assertEquals(4.9500, (float)$ronRate->rate);

        // Check EUR->EUR rate exists at 1.0
        $eurRate = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'EUR')
            ->whereDate('as_of_date', '2025-12-25')
            ->first();

        $this->assertNotNull($eurRate);
        $this->assertEquals(1.0, (float)$eurRate->rate);
        $this->assertEquals('ecb', $eurRate->source);
    }

    public function test_ecb_sync_handles_http_failure(): void
    {
        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::response([], 500),
        ]);

        $this->expectException(\RuntimeException::class);

        $service = app(EcbFxRatesService::class);
        $service->syncDaily();
    }
}

