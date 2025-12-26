<?php

namespace App\Services;

use App\Jobs\ValidateVatIdentityJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\VatIdentity;
use App\Support\VatId;
use Carbon\Carbon;

class VatIdentityResolver
{
    // Throttle period: don't enqueue if pending and recently enqueued (in minutes)
    private const PENDING_THROTTLE_MINUTES = 10;

    // Manual recheck throttle: minimum time between manual rechecks (in minutes)
    private const MANUAL_RECHECK_THROTTLE_MINUTES = 10;

    // Stale threshold: consider validation stale after this many days
    private const STALE_DAYS = 30;

    public function resolveForClient(Client $client): ?VatIdentity
    {
        if (empty($client->vat_id)) {
            $client->update(['vat_identity_id' => null]);
            return null;
        }

        $countryCode = VatId::normalizeCountry($client->country_code);
        $vatId = VatId::normalizeVatId($client->vat_id);

        $vatIdentity = VatIdentity::firstOrCreate(
            [
                'country_code' => $countryCode,
                'vat_id' => $vatId,
            ],
            [
                'status' => 'pending',
            ]
        );

        // Refresh to ensure we have the latest state (firstOrCreate might return cached instance)
        $vatIdentity->refresh();

        $client->update(['vat_identity_id' => $vatIdentity->id]);

        // Dispatch validation job if needed
        $this->enqueueIfNeeded($vatIdentity);

        // Refresh again to get updated last_enqueued_at if it was set
        $vatIdentity->refresh();

        return $vatIdentity;
    }

    public function resolveForCompany(Company $company): ?VatIdentity
    {
        if (empty($company->vat_id)) {
            $company->update(['vat_identity_id' => null]);
            return null;
        }

        $countryCode = VatId::normalizeCountry($company->country_code);
        $vatId = VatId::normalizeVatId($company->vat_id);

        $vatIdentity = VatIdentity::firstOrCreate(
            [
                'country_code' => $countryCode,
                'vat_id' => $vatId,
            ],
            [
                'status' => 'pending',
            ]
        );

        // Refresh to ensure we have the latest state (firstOrCreate might return cached instance)
        $vatIdentity->refresh();

        $company->update(['vat_identity_id' => $vatIdentity->id]);

        // Dispatch validation job if needed
        $this->enqueueIfNeeded($vatIdentity);

        // Refresh again to get updated last_enqueued_at if it was set
        $vatIdentity->refresh();

        return $vatIdentity;
    }

    /**
     * Manually trigger recheck of a VAT identity.
     * Forces enqueue even if fresh, but still respects a short throttle.
     *
     * @param VatIdentity $vatIdentity
     * @return bool True if job was enqueued, false if throttled
     */
    public function manualRecheck(VatIdentity $vatIdentity): bool
    {
        // Check if manual recheck is throttled
        if ($vatIdentity->last_enqueued_at !== null) {
            $minutesSinceEnqueued = $vatIdentity->last_enqueued_at->diffInMinutes(Carbon::now());
            if ($minutesSinceEnqueued < self::MANUAL_RECHECK_THROTTLE_MINUTES) {
                return false; // Still throttled
            }
        }

        // Force enqueue for manual recheck
        ValidateVatIdentityJob::dispatch($vatIdentity->id);
        $vatIdentity->update(['last_enqueued_at' => now()]);

        return true;
    }

    /**
     * Enqueue validation job if needed based on staleness and throttle rules.
     *
     * @param VatIdentity $vatIdentity
     * @return void
     */
    private function enqueueIfNeeded(VatIdentity $vatIdentity): void
    {
        // Refresh to get latest state
        $vatIdentity->refresh();

        // Check if validation is stale: last_checked_at is null OR older than 30 days
        $isStale = $vatIdentity->last_checked_at === null
            || $vatIdentity->last_checked_at->lt(Carbon::now()->subDays(self::STALE_DAYS));

        if (!$isStale) {
            return; // Validation is fresh, no need to enqueue
        }

        // Check throttle: not throttled if last_enqueued_at is null OR last_enqueued_at <= now()->subMinutes(10)
        $isThrottled = false;
        if ($vatIdentity->last_enqueued_at !== null) {
            $cutoffTime = Carbon::now()->subMinutes(self::PENDING_THROTTLE_MINUTES);
            if ($vatIdentity->last_enqueued_at->gt($cutoffTime)) {
                $isThrottled = true; // last_enqueued_at is more recent than 10 minutes ago
            }
        }

        if ($isThrottled) {
            return; // Recently enqueued, skip (within throttle window)
        }

        // Enqueue validation job
        ValidateVatIdentityJob::dispatch($vatIdentity->id);

        // Update last_enqueued_at atomically to prevent duplicate enqueues
        \Illuminate\Support\Facades\DB::table('vat_identities')
            ->where('id', $vatIdentity->id)
            ->update(['last_enqueued_at' => now()]);
    }
}
