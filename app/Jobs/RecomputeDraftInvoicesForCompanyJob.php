<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\InvoiceTotalsService;
use App\Services\VatDecisionService;
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
    public function handle(VatDecisionService $vatDecisionService, InvoiceTotalsService $totalsService): void
    {
        $company = Company::find($this->companyId);

        if (!$company) {
            Log::warning("RecomputeDraftInvoicesForCompanyJob: Company with ID {$this->companyId} not found.");
            return;
        }

        // Process draft invoices in chunks to avoid memory issues
        Invoice::where('company_id', $this->companyId)
            ->where('status', 'draft')
            ->with(['invoiceItems', 'client.vatIdentity'])
            ->chunkById(100, function ($invoices) use ($company, $vatDecisionService, $totalsService) {
                foreach ($invoices as $invoice) {
                    try {
                        // Reload to ensure we have latest status (safety check)
                        $invoice->refresh();

                        // Only process if still draft (optimistic update safety)
                        if ($invoice->status !== 'draft') {
                            continue;
                        }

                        // Recompute VAT decision based on current seller company and client
                        $decision = $vatDecisionService->decide($company, $invoice->client);

                        // Update VAT-related fields
                        $invoice->tax_treatment = $decision->taxTreatment;
                        $invoice->vat_rate = $decision->vatRate;
                        $invoice->vat_reason_text = $decision->reasonText;

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





