<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;

final class VatDecision
{
    public function __construct(
        public readonly string  $taxTreatment,   // DOMESTIC|EU_B2B_RC|EU_B2C|NON_EU
        public readonly float   $vatRate,          // percentage, e.g. 19.00
        public readonly ?string $reasonText,
    )
    {
    }
}

final class VatDecisionService
{
    // Minimal EU set. Extend later.
    private const EU = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT',
        'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    public function decide(Company $company, Client $client): VatDecision
    {
        $seller = strtoupper($company->country_code);
        $buyer = strtoupper($client->country_code);

        // Get seller VAT rate (override -> official standard -> company default)
        $sellerVatRate = $this->getSellerVatRate($company);

        // Domestic
        if ($buyer === $seller) {
            return new VatDecision(
                taxTreatment: 'DOMESTIC',
                vatRate: $sellerVatRate,
                reasonText: null
            );
        }

        $buyerInEu = in_array($buyer, self::EU, true);

        // Intra-EU B2B (reverse charge) - requires valid VAT ID
        if ($buyerInEu && !empty($client->vat_id) && $client->vatIdentity?->status === 'valid') {
            return new VatDecision(
                taxTreatment: 'EU_B2B_RC',
                vatRate: 0.0,
                reasonText: 'Reverse charge (EU B2B).'
            );
        }

        // Intra-EU B2C (general services rule for MVP: seller VAT)
        if ($buyerInEu) {
            return new VatDecision(
                taxTreatment: 'EU_B2C',
                vatRate: $sellerVatRate,
                reasonText: null
            );
        }

        // Non-EU
        return new VatDecision(
            taxTreatment: 'NON_EU',
            vatRate: 0.0,
            reasonText: 'Outside EU VAT scope.'
        );
    }

    /**
     * Get the seller VAT rate using: override -> official standard -> company default
     *
     * @param Company $company
     * @return float
     */
    private function getSellerVatRate(Company $company): float
    {
        // Check if override is enabled
        if ($company->vat_override_enabled && $company->vat_override_rate !== null) {
            return (float)$company->vat_override_rate;
        }

        // Check for official standard rate from tax_rates table
        $taxRate = \App\Models\TaxRate::where('country_code', strtoupper($company->country_code))->first();
        if ($taxRate && $taxRate->standard_rate !== null) {
            return (float)$taxRate->standard_rate;
        }

        // Fallback to company default
        return (float)$company->default_vat_rate;
    }
}
