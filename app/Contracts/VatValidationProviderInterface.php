<?php

namespace App\Contracts;

use App\Data\VatValidationResult;

interface VatValidationProviderInterface
{
    /**
     * Validate a VAT ID for a country
     *
     * @param string $countryCode 2-letter ISO country code
     * @param string $vatId VAT ID (without country prefix)
     * @return VatValidationResult Result with status, company_name, company_address, checked_at
     */
    public function validate(string $countryCode, string $vatId): VatValidationResult;
}

