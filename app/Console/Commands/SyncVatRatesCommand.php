<?php

namespace App\Console\Commands;

use App\Services\VatRatesSyncService;
use Illuminate\Console\Command;

class SyncVatRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vat:sync-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync VAT rates from VATlayer API';

    /**
     * Execute the console command.
     */
    public function handle(VatRatesSyncService $syncService): int
    {
        $this->info('Syncing VAT rates from VATlayer...');

        $result = $syncService->syncAll();

        if ($result['success']) {
            $this->info("Successfully synced {$result['synced']} VAT rates.");
            return Command::SUCCESS;
        }

        $this->error('Failed to sync VAT rates:');
        foreach ($result['errors'] as $error) {
            $this->error("  - {$error}");
        }

        return Command::FAILURE;
    }
}
