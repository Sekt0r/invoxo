<?php

namespace App\Contracts;

interface VatProvider
{
    /**
     * Validate a VAT number
     *
     * @param string $countryCode 2-letter country code
     * @param string $vatId VAT ID (without country prefix)
     * @return array Response array with keys: valid, company_name, company_address, country_code, vat_number
     */
    public function validateVat(string $countryCode, string $vatId): array;

    /**
     * Get VAT rate list for all countries or a specific country
     *
     * @param string|null $countryCode Optional 2-letter country code
     * @return array Response array with 'rates' key containing country codes as keys
     */
    public function rateList(?string $countryCode = null): array;
}




