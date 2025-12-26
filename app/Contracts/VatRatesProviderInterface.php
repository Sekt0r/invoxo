<?php

namespace App\Contracts;

interface VatRatesProviderInterface
{
    /**
     * Get the standard VAT rate for a country
     *
     * @param string $countryCode 2-letter ISO country code
     * @return float|null Standard VAT rate (percentage, e.g. 19.00) or null if not available
     */
    public function getStandardRate(string $countryCode): ?float;

    /**
     * Get all VAT rates for a country (standard + reduced if available)
     *
     * @param string $countryCode 2-letter ISO country code
     * @return array Array with 'standard_rate' (float) and optionally 'reduced_rates' (array)
     */
    public function getAllRates(string $countryCode): array;
}
