<?php

namespace App\Observers;

use App\Models\Company;
use App\Services\VatIdentityEnqueuer;
use App\Services\VatIdentityLinker;

class CompanyObserver
{
    public function __construct(
        private VatIdentityLinker $vatIdentityLinker,
        private VatIdentityEnqueuer $enqueuer
    ) {
    }

    /**
     * Handle the Company "saving" event.
     * Set vat_identity_id in-memory before save (no DB writes to Company here).
     */
    public function saving(Company $company): void
    {
        // Capture VAT intent BEFORE mutation
        $company->vatIntentChanged = !$company->exists || $company->isDirty(['country_code', 'vat_id']);

        if ($company->exists && !$company->vatIntentChanged) {
            return;
        }

        $vatId = $company->vat_id !== null ? trim((string)$company->vat_id) : '';

        if ($vatId === '') {
            $company->vat_identity_id = null;
            return;
        }

        $vatIdentity = $this->vatIdentityLinker->resolveOrCreate(
            strtoupper((string)$company->country_code),
            $vatId
        );

        $company->vat_identity_id = $vatIdentity->id;
    }

    /**
     * Handle the Company "saved" event.
     * Dispatch validation jobs asynchronously using shared VatIdentityEnqueuer.
     *
     * Scheduling rules:
     * - Only if VAT fields changed or company was created
     * - Only if vat_identity exists
     * - Staleness and throttle are handled atomically by VatIdentityEnqueuer
     */
    public function saved(Company $company): void
    {
        // Check if VAT fields changed (using transient flag set in saving())
        if (!($company->vatIntentChanged ?? false)) {
            return;
        }

        $vatIdentityId = (int)($company->vat_identity_id ?? 0);
        if ($vatIdentityId <= 0) {
            return;
        }

        // Use shared enqueuer with atomic CAS logic
        $this->enqueuer->enqueueIfStaleAndNotThrottled($vatIdentityId);
    }
}
