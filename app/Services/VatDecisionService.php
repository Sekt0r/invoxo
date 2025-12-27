<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;

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

    public function decide(Company $company, Client $client, ?User $user = null): VatDecision
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

        // Check permissions (separate concerns)
        $hasCrossBorderB2B = $user && $user->hasPlanPermission('cross_border_b2b');
        $hasViesValidation = $user && $user->hasPlanPermission('vies_validation');

        // Intra-EU B2B (reverse charge) automation
        // Requires cross_border_b2b permission for auto-suggestion
        // VIES validation permission allows checking validation status, but is not required
        // Check vat_id presence: must be non-null, non-empty string (trimmed)
        $vatIdPresent = !empty($client->vat_id) && trim($client->vat_id) !== '';
        if ($buyerInEu && $vatIdPresent && $hasCrossBorderB2B) {
            // If user has VIES validation, use validation status for additional confidence
            // If not, still suggest EU_B2B_RC based on presence of VAT ID
            $vatStatus = $client->vatIdentity?->status ?? null;
            $canSuggestRC = true;

            // If we have validation status and it's invalid, don't auto-suggest (user can still select manually)
            if ($hasViesValidation && $vatStatus === 'invalid') {
                $canSuggestRC = false;
            }

            // If we have validation status and it's valid, definitely suggest
            // If status is pending/unknown but user has cross_border_b2b, still suggest (automation enabled)
            if ($canSuggestRC) {
                return new VatDecision(
                    taxTreatment: 'EU_B2B_RC',
                    vatRate: 0.0,
                    reasonText: 'Reverse charge (EU B2B).'
                );
            }
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
     * Get the seller VAT rate using: override -> company default_vat_rate
     *
     * Company.default_vat_rate represents the country VAT rate (baseline, always valid).
     * Override is an optional manual escape hatch.
     *
     * @param Company $company
     * @return float
     */
    private function getSellerVatRate(Company $company): float
    {
        // Check if override is enabled (optional manual escape hatch)
        if ($company->vat_override_enabled && $company->vat_override_rate !== null) {
            return (float)$company->vat_override_rate;
        }

        // Use company default_vat_rate as baseline (represents country VAT rate)
        return (float)$company->default_vat_rate;
    }
}
