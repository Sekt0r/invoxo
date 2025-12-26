<?php

namespace App\Console\Commands;

use App\Jobs\SyncEcbFxRatesJob;
use Illuminate\Console\Command;

class SyncEcbFxRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fx:sync-ecb {--sync : Execute sync immediately instead of dispatching to queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync daily FX rates from ECB';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Executing FX rates sync synchronously...');
            try {
                $job = new SyncEcbFxRatesJob();
                $job->handle(app(\App\Services\EcbFxRatesService::class));
                $this->info('FX rates sync completed successfully.');

                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error('Failed to sync FX rates: ' . $e->getMessage());

                return Command::FAILURE;
            }
        }

        $this->info('Dispatching FX rates sync job to queue...');
        SyncEcbFxRatesJob::dispatch();
        $this->info('Job dispatched successfully.');

        return Command::SUCCESS;
    }
}
