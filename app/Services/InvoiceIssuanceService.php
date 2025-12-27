<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;

final class IssueResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly ?VatDecision $vatDecision = null,
        public readonly ?string $clientVatStatus = null,
        public readonly ?string $clientVatId = null,
    ) {
    }

    public static function success(VatDecision $vatDecision, string $clientVatStatus, ?string $clientVatId): self
    {
        return new self(
            success: true,
            vatDecision: $vatDecision,
            clientVatStatus: $clientVatStatus,
            clientVatId: $clientVatId,
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }
}

final class InvoiceIssuanceService
{
    public function __construct(
        private readonly VatDecisionService $vatDecisionService
    ) {
    }

    /**
     * Issue an invoice with VAT gating and snapshot
     *
     * @param Invoice $invoice
     * @param User $user
     * @return IssueResult
     */
    public function issue(Invoice $invoice, User $user): IssueResult
    {
        // Assert tenant: invoice must belong to user's company
        if ((int)$invoice->company_id !== (int)$user->company_id) {
            return IssueResult::failure('Invoice does not belong to your company.');
        }

        // Assert invoice is draft (idempotent)
        if ($invoice->status !== 'draft') {
            return IssueResult::failure('Invoice is not in draft status.');
        }

        // Load required relationships
        $invoice->load('invoiceItems', 'client.vatIdentity');
        $sellerCompany = $user->company;
        $sellerCompany->load('bankAccounts');

        // Validate company has bank accounts
        if ($sellerCompany->bankAccounts->isEmpty()) {
            return IssueResult::failure(
                'Add at least one bank account before issuing invoices.'
            );
        }

        // Validate invoice has currency
        if (empty($invoice->currency)) {
            return IssueResult::failure(
                'Invoice must have a currency before issuing. Select a currency from your bank account currencies.'
            );
        }

        // Validate company has bank accounts for the selected currency
        $matchingAccounts = $sellerCompany->bankAccounts->where('currency', $invoice->currency);
        if ($matchingAccounts->isEmpty()) {
            return IssueResult::failure(
                "No bank accounts found for currency {$invoice->currency}. Please select a different currency or add a bank account for this currency."
            );
        }

        // Validate seller legal identity completeness (before VAT checks)
        if (empty($sellerCompany->name)
            || empty($sellerCompany->country_code)
            || empty($sellerCompany->registration_number)
            || empty($sellerCompany->tax_identifier)
            || empty($sellerCompany->address_line1)
            || empty($sellerCompany->city)
            || empty($sellerCompany->postal_code)) {
            return IssueResult::failure(
                'Complete your company details (name, country, registration number, tax identifier, and address) before issuing invoices.'
            );
        }

        // Determine client VAT status
        $clientVatStatus = $this->determineClientVatStatus($invoice->client);
        $clientVatId = $invoice->client->vat_id;

        // Issue gate: block if pending or unknown
        if (in_array($clientVatStatus, ['pending', 'unknown'], true)) {
            $statusText = $clientVatStatus === 'pending' ? 'pending' : 'unknown';
            return IssueResult::failure(
                "Client VAT validation is {$statusText}. Please wait for validation before issuing."
            );
        }

        // Determine VAT decision
        // Pass user to check VIES validation permission for EU_B2B_RC auto-suggestion
        // If invalid or no VAT ID, VatDecisionService will automatically return B2C
        // (because it checks vatIdentity->status === 'valid' for reverse charge)
        $vatDecision = $this->vatDecisionService->decide($sellerCompany, $invoice->client, $user);

        // Force B2C if client has invalid VAT or no VAT ID (extra safety check)
        if (($clientVatStatus === 'invalid' || empty($clientVatId)) && $vatDecision->taxTreatment === 'EU_B2B_RC') {
            // This should not happen if VatDecisionService is correct, but add safety
            $sellerVatRate = $this->getSellerVatRate($sellerCompany);
            $vatDecision = new VatDecision(
                taxTreatment: 'EU_B2C',
                vatRate: $sellerVatRate,
                reasonText: null
            );
        }

        return IssueResult::success(
            vatDecision: $vatDecision,
            clientVatStatus: $clientVatStatus,
            clientVatId: $clientVatId,
        );
    }

    /**
     * Determine client VAT status based on vat_id and vat_identity
     *
     * @param \App\Models\Client $client
     * @return string 'valid'|'invalid'|'pending'|'unknown'
     */
    private function determineClientVatStatus(\App\Models\Client $client): string
    {
        // If client has no VAT ID, treat as invalid (B2C will apply)
        if (empty($client->vat_id)) {
            return 'invalid';
        }

        // If vat_identity is missing, status is unknown
        if (!$client->vatIdentity) {
            return 'unknown';
        }

        // Return status from vat_identity
        return $client->vatIdentity->status;
    }

    /**
     * Get seller VAT rate (helper method, similar to VatDecisionService)
     *
     * @param \App\Models\Company $company
     * @return float
     */
    private function getSellerVatRate(\App\Models\Company $company): float
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
