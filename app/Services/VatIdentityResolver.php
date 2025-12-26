<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\VatIdentity;
use App\Support\VatId;

class VatIdentityResolver
{
    public function __construct(
        private readonly VatIdentityEnqueuer $enqueuer
    ) {
    }

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

        // Dispatch validation job if needed (atomic CAS via shared service)
        $this->enqueuer->enqueueIfStaleAndNotThrottled($vatIdentity->id);

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

        // Dispatch validation job if needed (atomic CAS via shared service)
        $this->enqueuer->enqueueIfStaleAndNotThrottled($vatIdentity->id);

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
        return $this->enqueuer->forceEnqueueWithThrottle($vatIdentity->id);
    }
}
