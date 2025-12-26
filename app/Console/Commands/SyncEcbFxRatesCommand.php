<?php

namespace App\Console\Commands;

use App\Services\EcbFxRatesService;
use Illuminate\Console\Command;

class SyncEcbFxRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fx:sync-ecb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync daily FX rates from ECB';

    /**
     * Execute the console command.
     */
    public function handle(EcbFxRatesService $service): int
    {
        $this->info('Syncing FX rates from ECB...');

        try {
            $result = $service->syncDaily();

            $this->info("Successfully synced FX rates for {$result['as_of_date']}.");
            $this->info("  Inserted: {$result['inserted']} rates");
            $this->info("  Updated: {$result['updated']} rates");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to sync FX rates: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
