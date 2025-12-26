<?php

namespace App\Services;

use App\Models\VatIdentity;
use App\Support\VatId;

class VatIdentityLinker
{
    /**
     * Resolve or create a VatIdentity record for the given country code and VAT ID.
     *
     * Normalizes inputs and performs find-or-create by (country_code, vat_id).
     * No side effects beyond returning the vat_identity row.
     *
     * @param string $countryCode ISO country code
     * @param string $vatId VAT ID
     * @return VatIdentity
     */
    public function resolveOrCreate(string $countryCode, string $vatId): VatIdentity
    {
        // Normalize inputs
        $normalizedCountryCode = VatId::normalizeCountry($countryCode);
        $normalizedVatId = VatId::normalizeVatId($vatId);

        // Find or create vat identity
        return VatIdentity::firstOrCreate(
            [
                'country_code' => $normalizedCountryCode,
                'vat_id' => $normalizedVatId,
            ],
            [
                'status' => 'pending',
            ]
        );
    }
}

