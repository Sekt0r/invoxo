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

        // Domestic
        if ($buyer === $seller) {
            return new VatDecision(
                taxTreatment: 'DOMESTIC',
                vatRate: (float)$company->default_vat_rate,
                reasonText: null
            );
        }

        $buyerInEu = in_array($buyer, self::EU, true);

        // Intra-EU B2B (reverse charge) - MVP assumes "VAT ID present == B2B"
        if ($buyerInEu && !empty($client->vat_id)) {
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
                vatRate: (float)$company->default_vat_rate,
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
}
