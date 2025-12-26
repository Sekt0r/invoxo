<?php

namespace App\Support;

final class CompanyIdentityLabels
{
    /**
     * Get labels and hints for a country, merging country-specific overrides with defaults.
     *
     * @param string $countryCode 2-letter ISO country code
     * @return array Array with 'registration_number' and 'tax_identifier' keys, each containing 'label' and 'hint'
     */
    public static function forCountry(string $countryCode): array
    {
        $normalizedCode = strtoupper(trim($countryCode));
        $labels = config('company_identity_labels', []);
        $default = $labels['default'] ?? [];

        $countryLabels = $labels[$normalizedCode] ?? [];

        return [
            'registration_number' => [
                'label' => $countryLabels['registration_number']['label'] ?? $default['registration_number']['label'] ?? 'Registration number',
                'hint' => $countryLabels['registration_number']['hint'] ?? $default['registration_number']['hint'] ?? '',
            ],
            'tax_identifier' => [
                'label' => $countryLabels['tax_identifier']['label'] ?? $default['tax_identifier']['label'] ?? 'Tax identifier',
                'hint' => $countryLabels['tax_identifier']['hint'] ?? $default['tax_identifier']['hint'] ?? '',
            ],
        ];
    }
}
