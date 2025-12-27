<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceTotalsService;
use App\Services\InvoiceVatResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecomputeDraftInvoicesForCompanyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $companyId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(InvoiceVatResolver $vatResolver, InvoiceTotalsService $totalsService): void
    {
        // Query company fresh from database to ensure we have latest VAT override settings
        // Don't use find() which might return cached instance - use fresh query
        $company = Company::where('id', $this->companyId)->first();

        if (!$company) {
            Log::warning("RecomputeDraftInvoicesForCompanyJob: Company with ID {$this->companyId} not found.");
            return;
        }

        // Get a user from the company to check vat_rate_auto permission
        // All users in a company share the same plan, so any user will do
        $user = User::where('company_id', $this->companyId)->first();

        // Process draft invoices in chunks to avoid memory issues
        Invoice::where('company_id', $this->companyId)
            ->where('status', 'draft')
            ->with(['invoiceItems', 'client.vatIdentity'])
            ->chunkById(100, function ($invoices) use ($company, $vatResolver, $totalsService, $user) {
                foreach ($invoices as $invoice) {
                    try {
                        // Reload to ensure we have latest status (safety check)
                        $invoice->refresh();

                        // Only process if still draft (optimistic update safety)
                        if ($invoice->status !== 'draft') {
                            continue;
                        }

                        // Load relationships needed for VAT calculation
                        $invoice->load(['client.vatIdentity']);

                        // Reload company fresh from database for this invoice to ensure latest VAT override settings
                        // This is necessary because company may have been updated after job started
                        $invoice->load('company');
                        $invoice->company->refresh();

                        // Apply VAT decisioning (respects vat_rate_is_manual and permissions)
                        // If user has permission, use automatic decisioning; otherwise apply basic VAT correctness
                        $vatApplied = $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);

                        // If automatic VAT wasn't applied (user lacks permission), still apply basic VAT correctness
                        // VAT correctness is never gated by plan - recompute should always apply correct VAT
                        if (!$vatApplied && !$invoice->vat_rate_is_manual) {
                            // Get basic VAT decision and apply it
                            $decision = app(\App\Services\VatDecisionService::class)->decide($invoice->company, $invoice->client, null);
                            // Only update tax_treatment if it's not manually set
                            if (!$invoice->tax_treatment_is_manual) {
                                $invoice->tax_treatment = $decision->taxTreatment;
                                $invoice->vat_reason_text = $decision->reasonText;
                            }
                            // Always apply VAT rate (unless manually set, which we already checked)
                            $invoice->vat_rate = $decision->vatRate;
                            $invoice->vat_decided_at = now();
                        }

                        // Recompute totals from items (server authoritative)
                        $totalsService->recalculate($invoice);

                        // Save invoice (totalsService already updated subtotal_minor, vat_minor, total_minor)
                        $invoice->save();
                    } catch (\Exception $e) {
                        Log::error("RecomputeDraftInvoicesForCompanyJob: Failed to recompute invoice ID {$invoice->id}. Error: {$e->getMessage()}");
                        // Continue with next invoice instead of failing entire job
                    }
                }
            });
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 minute, 5 minutes, 15 minutes
    }
}

