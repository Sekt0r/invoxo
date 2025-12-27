<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;

/**
 * Service for automatic VAT decisioning on invoices.
 * Only applies automatic tax treatment selection and VAT rate application
 * when user has vat_rate_auto permission (Pro+).
 *
 * Starter users can still manually select tax_treatment and enter vat_rate.
 */
final class InvoiceVatResolver
{
    public function __construct(
        private readonly VatDecisionService $vatDecisionService
    ) {
    }

    /**
     * Apply automatic VAT decisioning to an invoice if user has permission.
     * Respects vat_rate_is_manual flag and only works on draft invoices.
     *
     * @param Invoice $invoice Invoice to process (must be draft)
     * @param User|null $user User performing the action (null = no permission check, auto-apply)
     * @param bool $forceManualTaxTreatment If true, don't auto-suggest tax_treatment (user manually selected)
     * @return bool True if VAT was automatically applied, false otherwise
     */
    public function applyAutomaticVatIfAllowed(Invoice $invoice, ?User $user = null, bool $forceManualTaxTreatment = false): bool
    {
        // Only process draft invoices
        if ($invoice->status !== 'draft') {
            return false;
        }

        // If user is provided, check permission
        if ($user !== null && !$user->hasPlanPermission('vat_rate_auto')) {
            return false;
        }

        // Ensure invoice has required relationships loaded
        // Always reload client to ensure we have latest data
        if (!$invoice->relationLoaded('client')) {
            $invoice->load('client');
        }

        // Only reload company if not already loaded (caller may have set a fresh instance)
        // This allows recompute job to pass a fresh company instance with latest override settings
        if (!$invoice->relationLoaded('company')) {
            $invoice->load('company');
        }

        // If vat_rate_is_manual = true, NEVER override vat_rate
        // If tax_treatment_is_manual = true, NEVER override tax_treatment
        $skipVatRateUpdate = $invoice->vat_rate_is_manual;
        $skipTaxTreatmentUpdate = $invoice->tax_treatment_is_manual || $forceManualTaxTreatment;

        // Auto-suggest tax_treatment if not manually set and permission granted
        if (!$skipTaxTreatmentUpdate) {
            $decision = $this->vatDecisionService->decide($invoice->company, $invoice->client, $user);
            $invoice->tax_treatment = $decision->taxTreatment;
            $invoice->vat_reason_text = $decision->reasonText;

            // Apply VAT rate only if not manually set
            if (!$skipVatRateUpdate) {
                $invoice->vat_rate = $decision->vatRate;
                $invoice->vat_decided_at = now();
            }
        } elseif (!$skipVatRateUpdate) {
            // User manually selected tax_treatment, but vat_rate is not manual
            // Apply VAT rate based on selected tax_treatment
            $invoice->vat_rate = $this->calculateVatRateForTreatment($invoice->company, $invoice->tax_treatment);
            $invoice->vat_reason_text = $this->getReasonTextForTreatment($invoice->tax_treatment);
            $invoice->vat_decided_at = now();
        }

        return true;
    }

    /**
     * Apply VAT rate based on current tax_treatment.
     * Used when user manually changes tax_treatment but vat_rate_is_manual is false.
     *
     * @param Invoice $invoice
     * @param User|null $user
     * @return void
     */
    public function applyVatRateForTreatment(Invoice $invoice, ?User $user = null): void
    {
        if ($invoice->status !== 'draft' || $invoice->vat_rate_is_manual) {
            return;
        }

        if (!$invoice->relationLoaded('company')) {
            $invoice->load('company');
        }

        // Enforce forced VAT rules based on tax_treatment
        // EU_B2B_RC and NON_EU always require vat_rate = 0
        // DOMESTIC and EU_B2C use company default (or override)
        $invoice->vat_rate = $this->calculateVatRateForTreatment(
            $invoice->company,
            $invoice->tax_treatment
        );
        $invoice->vat_reason_text = $this->getReasonTextForTreatment($invoice->tax_treatment);

        $invoice->vat_decided_at = now();
    }

    /**
     * Calculate VAT rate for a specific tax treatment.
     * Enforces forced VAT rules:
     * - EU_B2B_RC or NON_EU => vat_rate = 0
     * - DOMESTIC or EU_B2C => vat_rate = company default (or override)
     *
     * @param \App\Models\Company $company
     * @param string $taxTreatment
     * @return float
     */
    public function calculateVatRateForTreatment(\App\Models\Company $company, string $taxTreatment): float
    {
        $sellerVatRate = $this->getSellerVatRate($company);

        return match ($taxTreatment) {
            'DOMESTIC', 'EU_B2C' => $sellerVatRate,
            'EU_B2B_RC', 'NON_EU' => 0.0,
            default => 0.0,
        };
    }

    /**
     * Get reason text for a specific tax treatment.
     *
     * @param string $taxTreatment
     * @return string|null
     */
    private function getReasonTextForTreatment(string $taxTreatment): ?string
    {
        return match ($taxTreatment) {
            'EU_B2B_RC' => 'Reverse charge (EU B2B).',
            'NON_EU' => 'Outside EU VAT scope.',
            default => null,
        };
    }

    /**
     * Get the seller VAT rate using: override -> company default_vat_rate
     *
     * @param \App\Models\Company $company
     * @return float
     */
    private function getSellerVatRate(\App\Models\Company $company): float
    {
        if ($company->vat_override_enabled && $company->vat_override_rate !== null) {
            return (float)$company->vat_override_rate;
        }

        return (float)$company->default_vat_rate;
    }
}

