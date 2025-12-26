<?php

namespace App\Observers;

use App\Jobs\ValidateVatIdentityJob;
use App\Models\Company;
use App\Models\VatIdentity;
use App\Services\VatIdentityLinker;
use Illuminate\Support\Facades\DB;

class CompanyObserver
{
    public function __construct(
        private VatIdentityLinker $vatIdentityLinker
    ) {
    }

    /**
     * Handle the Company "saving" event.
     * Set vat_identity_id in-memory before save (no DB writes to Company here).
     */
    public function saving(Company $company): void
    {
        if ($company->exists && ! $company->isDirty(['country_code', 'vat_id'])) {
            return;
        }

        $vatId = $company->vat_id !== null ? trim((string) $company->vat_id) : '';

        if ($vatId === '') {
            $company->vat_identity_id = null;
            return;
        }

        $vatIdentity = $this->vatIdentityLinker->resolveOrCreate(
            strtoupper((string) $company->country_code),
            $vatId
        );

        $company->vat_identity_id = $vatIdentity->id;
    }

    /**
     * Handle the Company "saved" event.
     * Dispatch validation jobs asynchronously (no DB writes to Company here).
     *
     * Scheduling rules:
     * - Only if VAT fields changed or company was created
     * - Only if vat_identity exists
     * - Only if stale (last_checked_at null or older than 30 days)
     * - Dedupe/throttle: only one enqueue per 10 minutes using atomic compare-and-set update
     */
    public function saved(Company $company): void
    {
        // Check if VAT fields changed OR if vat_identity_id changed (which indicates VAT ID changed)
        $vatFieldsChanged = $company->wasChanged(['country_code', 'vat_id']);
        $vatIdentityIdChanged = $company->wasChanged(['vat_identity_id']);
        $isNewModel = $company->wasRecentlyCreated;

        if (! $vatFieldsChanged && ! $vatIdentityIdChanged && ! $isNewModel) {
            return;
        }

        $vatIdentityId = (int) ($company->vat_identity_id ?? 0);
        if ($vatIdentityId <= 0) {
            return;
        }

        /** @var VatIdentity|null $vatIdentity */
        $vatIdentity = VatIdentity::query()->find($vatIdentityId);
        if ($vatIdentity === null) {
            return;
        }

        $now = now();
        $isStale = $vatIdentity->last_checked_at === null || $vatIdentity->last_checked_at->lt($now->copy()->subDays(30));

        if (! $isStale) {
            return;
        }

        // Atomic throttle: update last_enqueued_at only if it is NULL or <= cutoff.
        // If no row updated, someone already enqueued within the last 10 minutes.
        $cutoff = $now->copy()->subMinutes(10);

        $updated = DB::table('vat_identities')
            ->where('id', $vatIdentityId)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_enqueued_at')
                    ->orWhere('last_enqueued_at', '<=', $cutoff);
            })
            ->update(['last_enqueued_at' => $now]);

        if ($updated !== 1) {
            return;
        }

        ValidateVatIdentityJob::dispatch($vatIdentityId);
    }
}
