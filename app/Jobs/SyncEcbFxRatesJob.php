<?php

namespace App\Jobs;

use App\Services\EcbFxRatesService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncEcbFxRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(EcbFxRatesService $service): void
    {
        $lock = Cache::lock('fx:sync:ecb', 300);

        if (!$lock->get()) {
            Log::info('SyncEcbFxRatesJob: Another sync is already in progress, skipping.');
            return;
        }

        try {
            $result = $service->syncDaily();

            Log::info("SyncEcbFxRatesJob: Successfully synced FX rates for {$result['as_of_date']}.", [
                'inserted' => $result['inserted'],
                'updated' => $result['updated'],
            ]);
        } catch (\Exception $e) {
            Log::error('SyncEcbFxRatesJob: Failed to sync FX rates: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }
}

