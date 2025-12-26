<?php

namespace App\Services;

use App\Jobs\ValidateVatIdentityJob;
use App\Models\VatIdentity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Centralized service for enqueueing VAT validation jobs.
 *
 * Uses atomic compare-and-swap (CAS) to prevent duplicate job dispatch
 * under concurrent requests.
 */
final class VatIdentityEnqueuer
{
    // Stale threshold: consider validation stale after this many days
    public const STALE_DAYS = 30;

    // Throttle period: don't enqueue if recently enqueued (in minutes)
    public const THROTTLE_MINUTES = 10;

    /**
     * Attempt to enqueue a validation job if the VAT identity is stale and not throttled.
     *
     * Uses atomic CAS to ensure only one job is dispatched even under concurrent requests.
     *
     * @param int $vatIdentityId
     * @return bool True if job was dispatched, false otherwise
     */
    public function enqueueIfStaleAndNotThrottled(int $vatIdentityId): bool
    {
        $now = now();

        // First, check staleness without locking (optimization)
        $vatIdentity = VatIdentity::find($vatIdentityId);
        if (!$vatIdentity) {
            return false;
        }

        $isStale = $vatIdentity->last_checked_at === null
            || $vatIdentity->last_checked_at->lt($now->copy()->subDays(self::STALE_DAYS));

        if (!$isStale) {
            return false;
        }

        // Atomic CAS: update last_enqueued_at only if it is NULL or older than throttle cutoff.
        // If no row updated, someone else already enqueued within the throttle window.
        $cutoff = $now->copy()->subMinutes(self::THROTTLE_MINUTES);

        $updated = DB::table('vat_identities')
            ->where('id', $vatIdentityId)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_enqueued_at')
                    ->orWhere('last_enqueued_at', '<=', $cutoff);
            })
            ->update(['last_enqueued_at' => $now]);

        if ($updated !== 1) {
            return false; // Throttled or not found
        }

        // Dispatch job only after successful CAS
        ValidateVatIdentityJob::dispatch($vatIdentityId);

        return true;
    }

    /**
     * Force enqueue for manual recheck.
     * Respects a shorter throttle window but ignores staleness.
     *
     * @param int $vatIdentityId
     * @return bool True if job was dispatched, false if throttled
     */
    public function forceEnqueueWithThrottle(int $vatIdentityId): bool
    {
        $now = now();
        $cutoff = $now->copy()->subMinutes(self::THROTTLE_MINUTES);

        $updated = DB::table('vat_identities')
            ->where('id', $vatIdentityId)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_enqueued_at')
                    ->orWhere('last_enqueued_at', '<=', $cutoff);
            })
            ->update(['last_enqueued_at' => $now]);

        if ($updated !== 1) {
            return false; // Throttled
        }

        ValidateVatIdentityJob::dispatch($vatIdentityId);

        return true;
    }
}
