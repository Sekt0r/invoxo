<?php

namespace App\Services\Vat;

use App\Contracts\VatProvider;

class FakeVatProvider implements VatProvider
{
    /**
     * Hardcoded VAT rates for deterministic test behavior
     */
    private const VAT_RATES = [
        'RO' => 21.0,
        'DE' => 19.0,
        'FR' => 20.0,
    ];

    private const DEFAULT_RATE = 20.0;

    /**
     * Validate a VAT number
     * Deterministic logic: valid if vatId starts with countryCode and length > 5
     *
     * @param string $countryCode
     * @param string $vatId
     * @return array
     */
    public function validateVat(string $countryCode, string $vatId): array
    {
        $normalizedCountryCode = strtoupper($countryCode);
        $normalizedVatId = strtoupper(trim($vatId));

        // Remove country code prefix if present
        if (str_starts_with($normalizedVatId, $normalizedCountryCode)) {
            $normalizedVatId = substr($normalizedVatId, strlen($normalizedCountryCode));
        }

        // Deterministic validation: valid if length > 5
        $isValid = strlen($normalizedVatId) > 5;

        return [
            'valid' => $isValid,
            'company_name' => $isValid ? "Test Company {$normalizedCountryCode}" : null,
            'company_address' => $isValid ? "Test Address {$normalizedCountryCode}" : null,
            'country_code' => $normalizedCountryCode,
            'vat_number' => $normalizedCountryCode . $normalizedVatId,
        ];
    }

    /**
     * Get VAT rate list
     * Returns hardcoded, deterministic data
     *
     * @param string|null $countryCode
     * @return array
     */
    public function rateList(?string $countryCode = null): array
    {
        if ($countryCode) {
            $normalizedCode = strtoupper($countryCode);
            $rate = self::VAT_RATES[$normalizedCode] ?? self::DEFAULT_RATE;
            return [
                'rates' => [
                    $normalizedCode => [
                        'standard_rate' => $rate,
                    ],
                ],
            ];
        }

        // Return all hardcoded rates
        $rates = [];
        foreach (self::VAT_RATES as $code => $rate) {
            $rates[$code] = [
                'standard_rate' => $rate,
            ];
        }

        // Add default rate for any other common countries
        $additionalCountries = ['GB', 'US', 'ES', 'IT', 'PL'];
        foreach ($additionalCountries as $code) {
            if (!isset($rates[$code])) {
                $rates[$code] = [
                    'standard_rate' => self::DEFAULT_RATE,
                ];
            }
        }

        return [
            'rates' => $rates,
        ];
    }
}






