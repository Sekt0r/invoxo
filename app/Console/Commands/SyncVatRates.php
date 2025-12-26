<?php

namespace App\Console\Commands;

use App\Services\VatRatesSyncService;
use Illuminate\Console\Command;

class SyncVatRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vat:sync-eu-rates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync EU VAT standard rates from euvatrates.com';

    /**
     * Execute the console command.
     */
    public function handle(VatRatesSyncService $service): int
    {
        try {
            $result = $service->sync();

            $this->info("VAT rates sync completed successfully.");
            $this->line("Fetched: {$result['fetched']} rates");
            $this->line("Upserted: {$result['upserted']} rates");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("VAT rates sync failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}

