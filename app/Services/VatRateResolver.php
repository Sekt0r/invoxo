<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TaxRate;
use App\Models\VatRateOverride;

class VatRateResolver
{
    /**
     * Resolve the VAT rate for a country code using override -> feed -> company default
     *
     * @param string $countryCode
     * @param Company $company
     * @return array{rate: float, source: string}
     */
    public function resolve(string $countryCode, Company $company): array
    {
        // Check override first
        $override = VatRateOverride::where('country_code', strtoupper($countryCode))->first();
        if ($override) {
            return [
                'rate' => (float)$override->standard_rate,
                'source' => 'override',
            ];
        }

        // Check feed (tax_rates table)
        $taxRate = TaxRate::where('country_code', strtoupper($countryCode))->first();
        if ($taxRate && $taxRate->standard_rate) {
            return [
                'rate' => (float)$taxRate->standard_rate,
                'source' => 'feed',
                'fetched_at' => $taxRate->fetched_at,
            ];
        }

        // Fallback to company default
        return [
            'rate' => (float)$company->default_vat_rate,
            'source' => 'fallback',
        ];
    }
}






