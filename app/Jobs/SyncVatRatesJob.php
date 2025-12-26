<?php

namespace App\Jobs;

use App\Services\VatRatesSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncVatRatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(VatRatesSyncService $syncService): void
    {
        $lock = Cache::lock('vat:sync:rates', 300);

        if (!$lock->get()) {
            Log::info('SyncVatRatesJob: Another sync is already in progress, skipping.');
            return;
        }

        try {
            $result = $syncService->syncAll();

            if ($result['success']) {
                Log::info("SyncVatRatesJob: Successfully synced {$result['synced']} VAT rates.");
            } else {
                Log::error('SyncVatRatesJob: Failed to sync VAT rates.', [
                    'errors' => $result['errors'],
                ]);
                throw new \RuntimeException('VAT rates sync failed: ' . implode(', ', $result['errors']));
            }
        } catch (\Exception $e) {
            Log::error('SyncVatRatesJob: Failed to sync VAT rates: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }
}

