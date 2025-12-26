<?php

namespace Tests\Feature;

use App\Jobs\SyncEcbFxRatesJob;
use Illuminate\Bus\PendingDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncEcbFxRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_job_async_by_default(): void
    {
        Bus::fake();

        $this->artisan('fx:sync-ecb')
            ->expectsOutput('Dispatching FX rates sync job to queue...')
            ->expectsOutput('Job dispatched successfully.')
            ->assertSuccessful();

        Bus::assertDispatched(SyncEcbFxRatesJob::class);
    }

    public function test_command_runs_sync_mode_when_flag_provided(): void
    {
        Bus::fake();

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
        </Cube>
    </Cube>
</gesmes:Envelope>';

        Http::fake([
            'ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml' => Http::response($xmlResponse, 200),
        ]);

        // Ensure lock is available
        Cache::lock('fx:sync:ecb', 300)->release();

        $this->artisan('fx:sync-ecb --sync')
            ->expectsOutput('Executing FX rates sync synchronously...')
            ->expectsOutput('FX rates sync completed successfully.')
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncEcbFxRatesJob::class);
    }
}

