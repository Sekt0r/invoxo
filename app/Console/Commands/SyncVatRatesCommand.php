<?php

namespace App\Console\Commands;

use App\Jobs\SyncVatRatesJob;
use Illuminate\Console\Command;

class SyncVatRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vat:sync-rates {--sync : Execute sync immediately instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync VAT rates from VATlayer API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Executing VAT rates sync synchronously...');
            try {
                $job = new SyncVatRatesJob();
                $job->handle(app(\App\Services\VatRatesSyncService::class));
                $this->info('VAT rates sync completed successfully.');

                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error('Failed to sync VAT rates: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $this->info('Dispatching VAT rates sync job to queue...');
        SyncVatRatesJob::dispatch();
        $this->info('Job dispatched successfully.');

        return Command::SUCCESS;
    }
}
