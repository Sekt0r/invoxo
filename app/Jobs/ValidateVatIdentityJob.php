<?php

namespace App\Jobs;

use App\Contracts\VatValidationProviderInterface;
use App\Models\VatIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ValidateVatIdentityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $vatIdentityId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(VatValidationProviderInterface $vatProvider): void
    {
        $vatIdentity = VatIdentity::find($this->vatIdentityId);

        if (!$vatIdentity) {
            Log::warning("ValidateVatIdentityJob: VatIdentity with ID {$this->vatIdentityId} not found.");
            return;
        }

        try {
            $result = $vatProvider->validate($vatIdentity->country_code, $vatIdentity->vat_id);

            $oldStatus = $vatIdentity->status;
            $now = now();

            $vatIdentity->update([
                'status' => $result->status,
                'last_checked_at' => $result->checkedAt ?? $now, // Use provider's checked_at or now
                'status_updated_at' => $oldStatus !== $result->status ? $now : $vatIdentity->status_updated_at, // Update only if status changed
                'name' => $result->companyName,
                'address' => $result->companyAddress,
                'source' => 'provider', // No environment check - determined by provider binding
                'provider_metadata' => [
                    'status' => $result->status,
                    'checked_at' => $result->checkedAt?->toIso8601String(),
                ],
                'last_error' => null, // Clear previous errors on successful attempt
            ]);
        } catch (\Exception $e) {
            Log::error("ValidateVatIdentityJob: Failed to validate VAT ID {$vatIdentity->vat_id} for country {$vatIdentity->country_code}. Error: {$e->getMessage()}");
            $oldStatus = $vatIdentity->status;
            $now = now();

            $vatIdentity->update([
                'status' => 'unknown',
                'last_checked_at' => $now, // Set last_checked_at to rate-limit retries
                'status_updated_at' => $oldStatus !== 'unknown' ? $now : $vatIdentity->status_updated_at, // Update only if status changed
                'source' => 'provider',
                'last_error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [600, 1800, 7200]; // 10 minutes, 30 minutes, 2 hours
    }
}
