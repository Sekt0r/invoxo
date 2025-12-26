<?php

namespace Tests\Feature;

use App\Models\FxRate;
use App\Services\EcbFxRatesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EcbFxRatesSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_is_idempotent_no_duplicates_on_second_run(): void
    {
        // Mock ECB XML response
        $ecbXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <gesmes:subject>Reference rates</gesmes:subject>
    <gesmes:Sender>
        <gesmes:name>European Central Bank</gesmes:name>
    </gesmes:Sender>
    <Cube>
        <Cube time="2025-12-24">
            <Cube currency="USD" rate="1.1787"/>
            <Cube currency="RON" rate="4.9500"/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::response($ecbXml, 200),
        ]);

        $service = new EcbFxRatesService();

        // First run
        $result1 = $service->syncDaily();
        $this->assertEquals('2025-12-24', $result1['as_of_date']);
        $this->assertGreaterThan(0, $result1['inserted']);

        // Count rates for this date
        $firstRunCount = FxRate::where('as_of_date', '2025-12-24')
            ->where('source', 'ecb')
            ->count();
        $this->assertGreaterThan(0, $firstRunCount);

        // Second run with same data (idempotent)
        $result2 = $service->syncDaily();
        $this->assertEquals('2025-12-24', $result2['as_of_date']);

        // Count should remain the same (no duplicates)
        $secondRunCount = FxRate::where('as_of_date', '2025-12-24')
            ->where('source', 'ecb')
            ->count();
        $this->assertEquals($firstRunCount, $secondRunCount, 'Running sync twice should not create duplicates');

        // Verify specific rate exists exactly once
        $usdRate = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'USD')
            ->where('as_of_date', '2025-12-24')
            ->count();
        $this->assertEquals(1, $usdRate, 'EUR->USD rate should exist exactly once');
    }

    public function test_sync_updates_rate_on_second_run_with_different_value(): void
    {
        // Mock ECB XML response (first run)
        $ecbXml1 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube>
        <Cube time="2025-12-24">
            <Cube currency="USD" rate="1.1787"/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

        // Mock ECB XML response (second run with different rate)
        $ecbXml2 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube>
        <Cube time="2025-12-24">
            <Cube currency="USD" rate="1.1850"/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::sequence()
                ->push($ecbXml1, 200)
                ->push($ecbXml2, 200),
        ]);

        $service = new EcbFxRatesService();

        // First run
        $result1 = $service->syncDaily();
        $this->assertEquals('2025-12-24', $result1['as_of_date']);

        $firstRate = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'USD')
            ->where('as_of_date', '2025-12-24')
            ->first();
        $this->assertNotNull($firstRate);
        $this->assertEquals(1.1787, (float)$firstRate->rate);

        // Second run with updated rate
        $result2 = $service->syncDaily();
        $this->assertEquals('2025-12-24', $result2['as_of_date']);

        // Rate should be updated, not duplicated
        $ratesCount = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'USD')
            ->where('as_of_date', '2025-12-24')
            ->count();
        $this->assertEquals(1, $ratesCount, 'Should have exactly one rate, not duplicates');

        $updatedRate = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'USD')
            ->where('as_of_date', '2025-12-24')
            ->first();
        $this->assertNotNull($updatedRate);
        $this->assertEquals(1.1850, (float)$updatedRate->rate, 'Rate should be updated to new value');
    }

    public function test_sync_normalizes_date_to_canonical_format(): void
    {
        // Mock ECB XML response with date in various formats
        // ECB typically returns YYYY-MM-DD, but we ensure normalization
        $ecbXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube>
        <Cube time="2025-12-24">
            <Cube currency="USD" rate="1.1787"/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::response($ecbXml, 200),
        ]);

        $service = new EcbFxRatesService();

        $result = $service->syncDaily();

        // Verify date is normalized to YYYY-MM-DD
        $this->assertEquals('2025-12-24', $result['as_of_date']);

        // Verify stored date is also canonical format
        $rate = FxRate::where('as_of_date', '2025-12-24')->first();
        $this->assertNotNull($rate);
        $this->assertInstanceOf(\Carbon\Carbon::class, $rate->as_of_date);
        $this->assertEquals('2025-12-24', $rate->as_of_date->format('Y-m-d'));
    }

    public function test_sync_handles_eur_to_eur_rate(): void
    {
        $ecbXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube>
        <Cube time="2025-12-24">
            <Cube currency="USD" rate="1.1787"/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::response($ecbXml, 200),
        ]);

        $service = new EcbFxRatesService();

        $result = $service->syncDaily();

        // EUR->EUR should exist at 1.0
        $eurRate = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'EUR')
            ->where('as_of_date', '2025-12-24')
            ->first();

        $this->assertNotNull($eurRate);
        $this->assertEquals(1.0, (float)$eurRate->rate);
        $this->assertEquals('ecb', $eurRate->source);
    }

    public function test_sync_is_idempotent_for_eur_to_eur_rate(): void
    {
        $ecbXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<gesmes:Envelope xmlns:gesmes="http://www.gesmes.org/xml/2002-08-01" xmlns="http://www.ecb.int/vocabulary/2002-08-01/eurofxref">
    <Cube>
        <Cube time="2025-12-24">
            <Cube currency="USD" rate="1.1787"/>
        </Cube>
    </Cube>
</gesmes:Envelope>
XML;

        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::response($ecbXml, 200),
        ]);

        $service = new EcbFxRatesService();

        // Run twice
        $service->syncDaily();
        $service->syncDaily();

        // EUR->EUR should exist exactly once
        $eurRateCount = FxRate::where('base_currency', 'EUR')
            ->where('quote_currency', 'EUR')
            ->where('as_of_date', '2025-12-24')
            ->count();

        $this->assertEquals(1, $eurRateCount, 'EUR->EUR rate should exist exactly once after multiple runs');
    }
}

