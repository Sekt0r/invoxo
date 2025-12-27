<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\Invoice;
use App\Services\FxConversionService;
use App\Services\InvoiceIssuanceService;
use App\Services\InvoiceNumberService;
use App\Services\InvoiceTotalsService;
use App\Services\VatDecisionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request, FxConversionService $fxConversion): View
    {
        $companyId = auth()->user()->company_id;
        $invoices = Invoice::where('company_id', $companyId)
            ->with('client')
            ->orderByDesc('id')
            ->get();

        // Compute EUR conversions for display
        $convertedTotals = [];
        $latestFxDate = $fxConversion->getLatestDate();

        foreach ($invoices as $invoice) {
            // Skip invoices without currency (should not happen, but defensive)
            if (empty($invoice->currency)) {
                $convertedTotals[$invoice->id] = null;
                continue;
            }

            if ($invoice->currency === 'EUR') {
                $convertedTotals[$invoice->id] = [
                    'amount_minor' => $invoice->total_minor,
                    'fx_date' => $latestFxDate,
                ];
            } else {
                try {
                    $asOf = $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date) : null;
                    $convertedMinor = $fxConversion->convertMinor(
                        $invoice->total_minor,
                        $invoice->currency,
                        'EUR',
                        $asOf
                    );
                    $convertedTotals[$invoice->id] = [
                        'amount_minor' => $convertedMinor,
                        'fx_date' => $asOf ? $asOf->format('Y-m-d') : $latestFxDate,
                    ];
                } catch (\DomainException $e) {
                    // FX rate not available - will show "—" in view
                    $convertedTotals[$invoice->id] = null;
                }
            }
        }

        return view('invoice.index', [
            'invoices' => $invoices,
            'convertedTotals' => $convertedTotals,
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = auth()->user()->company_id;
        $company = auth()->user()->company;
        $company->load('bankAccounts');

        // Compute allowed currencies from bank accounts
        $bankAccounts = $company->bankAccounts;
        $allowedCurrencies = $bankAccounts->pluck('currency')->unique()->sort()->values()->all();
        $defaultCurrency = null;

        // Determine default currency: default account currency, or first currency
        $defaultAccount = $bankAccounts->where('is_default', true)->first();
        if ($defaultAccount) {
            $defaultCurrency = $defaultAccount->currency;
        } elseif (!empty($allowedCurrencies)) {
            $defaultCurrency = $allowedCurrencies[0];
        }

        return view('invoice.create', [
            'clients' => \App\Models\Client::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'bankAccounts' => $bankAccounts,
            'allowedCurrencies' => $allowedCurrencies,
            'defaultCurrency' => $defaultCurrency,
            'hasBankAccounts' => $bankAccounts->isNotEmpty(),
        ]);
    }


    public function store(InvoiceStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $company = auth()->user()->company;

        // Currency is nullable for drafts if no bank accounts exist
        $currency = $data['currency'] ?? null;

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $data['client_id'],
            'currency' => $currency,
            'issue_date' => $data['issue_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'draft',
        ]);

        // Load relationships needed for VAT calculation
        $invoice->load('client', 'company');

        // Apply automatic VAT decisioning if user has permission
        $vatResolver = app(\App\Services\InvoiceVatResolver::class);
        $vatApplied = $vatResolver->applyAutomaticVatIfAllowed($invoice, auth()->user());

        // If automatic VAT wasn't applied (user lacks permission), still apply basic VAT correctness
        // VAT correctness is never gated by plan - Starter users should get correct VAT for DOMESTIC/EU_B2C
        if (!$vatApplied && !$invoice->tax_treatment) {
            // Get basic VAT decision without automation (no cross_border_b2b logic, just correctness)
            $decision = app(\App\Services\VatDecisionService::class)->decide($invoice->company, $invoice->client, null);
            $invoice->tax_treatment = $decision->taxTreatment;
            $invoice->vat_rate = $decision->vatRate;
            $invoice->vat_reason_text = $decision->reasonText;
            $invoice->vat_decided_at = now();
        }

        $invoice->save();

        foreach ($data['items'] as $item) {
            // Convert decimal unit_price (major units) to integer minor units
            // Use safe string-based conversion to avoid float rounding issues
            // Minor unit assumed to be 2 decimals; extend later using ISO 4217 map if needed.
            $unitPriceStr = (string)$item['unit_price'];

            // Use bcmul if available, otherwise use string-safe conversion
            if (function_exists('bcmul')) {
                $unitPriceMinor = (int)round((float)bcmul($unitPriceStr, '100', 10), 0, PHP_ROUND_HALF_UP);
            } else {
                // Fallback: parse as float and multiply (acceptable for currency with 2 decimals)
                $unitPriceMinor = (int)round((float)$item['unit_price'] * 100, 0, PHP_ROUND_HALF_UP);
            }

            $invoice->invoiceItems()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price_minor' => $unitPriceMinor,
                'line_total_minor' => 0,
            ]);
        }

        app(\App\Services\InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        $request->session()->flash('invoice.id', $invoice->id);

        return redirect()->route('invoices.index');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        // Load client with vatIdentity for VAT change detection, and company for seller details
        $invoice->load('client.vatIdentity', 'company');

        // Check if VAT status changed since invoice was last computed
        $vatChanged = false;
        $previousStatus = null;
        $currentStatus = null;
        $changedAt = null;

        if ($invoice->status === 'draft' && $invoice->vat_decided_at !== null && $invoice->client->vatIdentity) {
            $vatIdentity = $invoice->client->vatIdentity;
            $statusChangedAt = $vatIdentity->status_updated_at ?? $vatIdentity->last_checked_at;

            if ($statusChangedAt && $statusChangedAt->isAfter($invoice->vat_decided_at)) {
                $vatChanged = true;
                $previousStatus = $invoice->client_vat_status_snapshot ?? 'unknown';
                $currentStatus = $vatIdentity->status;
                $changedAt = $statusChangedAt;
            }
        }

        // Load invoice events for activity timeline (tenant scoped)
        $events = \App\Models\InvoiceEvent::where('company_id', auth()->user()->company_id)
            ->where('invoice_id', $invoice->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('invoice.show', [
            'invoice' => $invoice,
            'vatChanged' => $vatChanged,
            'previousVatStatus' => $previousStatus,
            'currentVatStatus' => $currentStatus,
            'vatStatusChangedAt' => $changedAt,
            'events' => $events,
        ]);
    }

    public function edit(Request $request, Invoice $invoice): View|\Illuminate\Http\RedirectResponse
    {
        if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        if ($invoice->status !== 'draft') {
            return redirect()->route('invoices.show', $invoice);
        }

        $companyId = auth()->user()->company_id;
        $invoice->load('invoiceItems', 'client.vatIdentity');

        $company = auth()->user()->company;

        // Check if VAT status changed since invoice was last computed (only for drafts)
        $vatChanged = false;
        $previousStatus = null;
        $currentStatus = null;
        $changedAt = null;

        if ($invoice->status === 'draft' && $invoice->vat_decided_at !== null && $invoice->client->vatIdentity) {
            $vatIdentity = $invoice->client->vatIdentity;
            $statusChangedAt = $vatIdentity->status_updated_at ?? $vatIdentity->last_checked_at;

            if ($statusChangedAt && $statusChangedAt->isAfter($invoice->vat_decided_at)) {
                $vatChanged = true;
                $previousStatus = $invoice->client_vat_status_snapshot ?? 'unknown';
                $currentStatus = $vatIdentity->status;
                $changedAt = $statusChangedAt;
            }
        }

        $company->load('bankAccounts');

        // Compute allowed currencies from bank accounts
        $bankAccounts = $company->bankAccounts;
        $allowedCurrencies = $bankAccounts->pluck('currency')->unique()->sort()->values()->all();
        $defaultCurrency = $invoice->currency ?? null;

        // If no default yet, use default account currency or first currency
        if ($defaultCurrency === null) {
            $defaultAccount = $bankAccounts->where('is_default', true)->first();
            if ($defaultAccount) {
                $defaultCurrency = $defaultAccount->currency;
            } elseif (!empty($allowedCurrencies)) {
                $defaultCurrency = $allowedCurrencies[0];
            }
        }

        return view('invoice.edit', [
            'invoice' => $invoice,
            'clients' => \App\Models\Client::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'bankAccounts' => $bankAccounts,
            'allowedCurrencies' => $allowedCurrencies,
            'defaultCurrency' => $defaultCurrency,
            'hasBankAccounts' => $bankAccounts->isNotEmpty(),
            'vatChanged' => $vatChanged,
            'previousVatStatus' => $previousStatus,
            'currentVatStatus' => $currentStatus,
            'vatStatusChangedAt' => $changedAt,
        ]);
    }

    public function update(InvoiceUpdateRequest $request, Invoice $invoice): RedirectResponse
    {
        if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        if ($invoice->status !== 'draft') {
            return redirect()->route('invoices.show', $invoice);
        }

        $data = $request->validated();

        $company = auth()->user()->company;

        // Update invoice fields (ignore company_id/status/totals from request)
        $invoice->client_id = $data['client_id'];
        // Currency is nullable for drafts if no bank accounts exist
        $invoice->currency = $data['currency'] ?? null;
        $invoice->issue_date = $data['issue_date'] ?? null;
        $invoice->due_date = $data['due_date'] ?? null;
        // payment_details is only set on issue, not during draft edits
        $invoice->save();

        // Handle VAT calculation (only for draft invoices)
        // Use InvoiceVatResolver to handle automatic decisioning with permission checks
        $invoice->load('client', 'company');

        $vatResolver = app(\App\Services\InvoiceVatResolver::class);
        $user = auth()->user();

        // Check if tax_treatment was manually set in request
        $manualTaxTreatment = $request->has('tax_treatment') ? $request->input('tax_treatment') : null;
        $manualVatRate = $request->has('vat_rate') ? $request->input('vat_rate') : null;
        $vatRateReset = $request->has('vat_rate_reset') && $request->input('vat_rate_reset') === '1';
        $taxTreatmentReset = $request->has('tax_treatment_reset') && $request->input('tax_treatment_reset') === '1';

        // Handle VAT rate reset (only clears vat_rate_is_manual, recomputes VAT rate)
        if ($vatRateReset) {
            if ($invoice->status !== 'draft') {
                return redirect()->route('invoices.show', $invoice);
            }
            $invoice->vat_rate_is_manual = false;
            // Recompute VAT rate based on current tax_treatment
            $vatResolver->applyVatRateForTreatment($invoice, $user);
        }
        // Handle tax treatment reset (only clears tax_treatment_is_manual, recomputes tax treatment if permitted)
        elseif ($taxTreatmentReset) {
            if ($invoice->status !== 'draft') {
                return redirect()->route('invoices.show', $invoice);
            }
            $invoice->tax_treatment_is_manual = false;
            // Recompute tax_treatment if user has automation permissions
            $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);
            // If VAT rate is not manual, also recompute VAT rate after tax treatment change
            if (!$invoice->vat_rate_is_manual) {
                $vatResolver->applyVatRateForTreatment($invoice, $user);
            }
        }
        // Handle manual tax_treatment selection
        elseif ($manualTaxTreatment !== null && in_array($manualTaxTreatment, ['DOMESTIC', 'EU_B2B_RC', 'EU_B2C', 'NON_EU'], true)) {
            // User manually selected tax_treatment - mark as manual override
            $invoice->tax_treatment_is_manual = true;
            $invoice->tax_treatment = $manualTaxTreatment;
            $invoice->vat_reason_text = match ($manualTaxTreatment) {
                'EU_B2B_RC' => 'Reverse charge (EU B2B).',
                'NON_EU' => 'Outside EU VAT scope.',
                default => null,
            };

            // If user manually selected EU_B2B_RC, force vat_rate = 0 and clear vat_rate_is_manual
            if ($manualTaxTreatment === 'EU_B2B_RC') {
                $invoice->vat_rate = 0.0;
                $invoice->vat_rate_is_manual = false;
            } elseif ($manualVatRate !== null) {
                // User also manually set VAT rate
                $invoice->vat_rate_is_manual = true;
                $invoice->vat_rate = (float)$manualVatRate;
            } else {
                // Apply VAT rate based on selected tax_treatment (vat_rate not manual)
                $vatResolver->applyVatRateForTreatment($invoice, $user);
            }
        } elseif ($manualVatRate !== null) {
            // User manually edited VAT rate only (tax_treatment not manually set)
            $invoice->vat_rate_is_manual = true;
            $invoice->vat_rate = (float)$manualVatRate;
            // Still auto-suggest tax_treatment if user has permission
            $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);
        } else {
            // No manual input - apply automatic VAT decisioning if user has permission
            $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);
        }

        // Replace invoice items (delete old, create new)
        $invoice->invoiceItems()->delete();

        foreach ($data['items'] as $item) {
            // Convert decimal unit_price (major units) to integer minor units
            // Use safe string-based conversion to avoid float rounding issues
            // Minor unit assumed to be 2 decimals; extend later using ISO 4217 map if needed.
            $unitPriceStr = (string)$item['unit_price'];

            // Use bcmul if available, otherwise use string-safe conversion
            if (function_exists('bcmul')) {
                $unitPriceMinor = (int)round((float)bcmul($unitPriceStr, '100', 10), 0, PHP_ROUND_HALF_UP);
            } else {
                // Fallback: parse as float and multiply (acceptable for currency with 2 decimals)
                $unitPriceMinor = (int)round((float)$item['unit_price'] * 100, 0, PHP_ROUND_HALF_UP);
            }

            $invoice->invoiceItems()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price_minor' => $unitPriceMinor,
                'line_total_minor' => 0,
            ]);
        }

        // Reload items and recalculate totals
        $invoice->load('invoiceItems');
        app(\App\Services\InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        return redirect()->route('invoices.show', $invoice)
            ->with('status', 'Invoice updated successfully.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        $invoice->delete();

        return redirect()->route('invoices.index');
    }

    public function issue(Request $request, Invoice $invoice, InvoiceIssuanceService $issuanceService): RedirectResponse
    {
        // Wrap everything in a transaction to ensure row locks work properly
        return DB::transaction(function () use ($invoice, $issuanceService) {
            // Lock invoice row to prevent concurrent issues
            $invoice = Invoice::whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Tenant check after lock
            if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
                abort(403);
            }

            // Assert invoice is still draft (idempotent check)
            if ($invoice->status !== 'draft') {
                // Already issued, redirect without error (idempotent)
                return redirect()->route('invoices.show', $invoice);
            }

            // Determine effective issue_date BEFORE calling issuance service
            // Use invoice.issue_date if set, otherwise today
            // This ensures the year is correct for number assignment
            // Handle both string and Carbon instance from date cast
            if ($invoice->issue_date === null) {
                $effectiveIssueDate = now()->toDateString();
                $invoice->issue_date = $effectiveIssueDate;
            } else {
                $effectiveIssueDate = is_string($invoice->issue_date)
                    ? $invoice->issue_date
                    : $invoice->issue_date->toDateString();
            }

            // Use InvoiceIssuanceService for VAT gating and decision
            $result = $issuanceService->issue($invoice, auth()->user());

            if (!$result->success) {
                return redirect()->route('invoices.show', $invoice)
                    ->withErrors(['vat' => $result->errorMessage]);
            }

            // Load relationships for totals calculation
            $invoice->load('invoiceItems', 'company');

            // Recalculate totals
            app(InvoiceTotalsService::class)->recalculate($invoice);

            // Assign invoice number if empty (using issue_date year)
            if (empty($invoice->number)) {
                $invoice->number = app(InvoiceNumberService::class)->nextNumber($invoice->company, $effectiveIssueDate);
            }

            // Set status to issued
            $invoice->status = 'issued';

            // Store VAT snapshot
            $invoice->tax_treatment = $result->vatDecision->taxTreatment;
            $invoice->vat_rate = $result->vatDecision->vatRate;
            $invoice->vat_reason_text = $result->vatDecision->reasonText;
            $invoice->vat_decided_at = now();
            $invoice->client_vat_status_snapshot = $result->clientVatStatus;
            $invoice->client_vat_id_snapshot = $result->clientVatId;

            // Store seller details snapshot for immutability
            $company = $invoice->company;
            $company->load('bankAccounts');

            // Get ALL bank accounts matching the invoice currency
            $matchingAccounts = $company->bankAccounts->where('currency', $invoice->currency);
            $paymentAccounts = $matchingAccounts->map(function ($account) {
                return [
                    'iban' => $account->iban,
                    'nickname' => $account->nickname,
                ];
            })->values()->all();

            $invoice->seller_details = [
                'company_name' => $company->name,
                'country_code' => $company->country_code,
                'registration_number' => $company->registration_number,
                'tax_identifier' => $company->tax_identifier,
                'address_line1' => $company->address_line1,
                'address_line2' => $company->address_line2,
                'city' => $company->city,
                'postal_code' => $company->postal_code,
                'vat_id' => $company->vat_id,
            ];

            // Store payment details snapshot with ALL matching currency accounts
            $invoice->payment_details = [
                'company_name' => $company->name,
                'currency' => $invoice->currency,
                'accounts' => $paymentAccounts,
                'captured_at' => now()->toIso8601String(),
            ];

            // Store buyer/client details snapshot for immutability
            $client = $invoice->client;
            $invoice->buyer_details = [
                'client_name' => $client->name,
                'country_code' => $client->country_code,
                'vat_id' => $client->vat_id,
                'registration_number' => $client->registration_number,
                'tax_identifier' => $client->tax_identifier,
                'address_line1' => $client->address_line1,
                'address_line2' => $client->address_line2,
                'city' => $client->city,
                'postal_code' => $client->postal_code,
                'captured_at' => now()->toIso8601String(),
            ];

            // Save once (all changes in same transaction)
            $invoice->save();

            // Log the issue event
            app(\App\Services\InvoiceEventLogger::class)->logIssued($invoice, auth()->user());

            return redirect()->route('invoices.show', $invoice);
        });
    }

    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        if ($invoice->status !== 'issued') {
            return redirect()->route('invoices.show', $invoice);
        }

        $oldStatus = $invoice->status;
        $invoice->status = 'paid';
        $invoice->save();

        // Log status change
        app(\App\Services\InvoiceEventLogger::class)->logStatusChanged($invoice, auth()->user(), $oldStatus, 'paid');

        return redirect()->route('invoices.show', $invoice);
    }

    public function changeStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'status' => ['required', 'string', 'in:issued,paid,voided'],
        ]);

        $newStatus = $request->input('status');
        $oldStatus = $invoice->status;

        // Validate allowed transitions
        $allowedTransitions = [
            'issued' => ['paid', 'voided'],
            'paid' => ['issued'], // Allow reverting paid back to issued
            'voided' => ['issued'], // Allow reverting voided back to issued
            'draft' => [], // Draft cannot transition directly to paid/voided (must issue first)
        ];

        if (!isset($allowedTransitions[$oldStatus]) || !in_array($newStatus, $allowedTransitions[$oldStatus])) {
            return redirect()->route('invoices.show', $invoice)
                ->withErrors(['status' => "Cannot change status from {$oldStatus} to {$newStatus}."]);
        }

        $invoice->status = $newStatus;
        $invoice->save();

        // Log status change
        app(\App\Services\InvoiceEventLogger::class)->logStatusChanged($invoice, auth()->user(), $oldStatus, $newStatus);

        return redirect()->route('invoices.show', $invoice)
            ->with('status', "Invoice status changed to {$newStatus}.");
    }

    public function pdf(Request $request, Invoice $invoice): Response|RedirectResponse
    {
        // Tenant check
        if ((int)$invoice->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        // Status check - only issued invoices can be exported as PDF
        if ($invoice->status !== 'issued') {
            return redirect()->route('invoices.show', $invoice)
                ->withErrors(['pdf' => 'Invoice must be issued before exporting PDF.']);
        }

        // Load relationships
        $invoice->load('invoiceItems', 'company', 'client');

        // Generate PDF
        $pdf = Pdf::loadView('invoice.pdf', ['invoice' => $invoice]);

        // Determine filename
        $filename = $invoice->number
            ? 'invoice-' . $invoice->number . '.pdf'
            : 'invoice-' . $invoice->id . '.pdf';

        return $pdf->download($filename);
    }

    public function vatPreview(Request $request, VatDecisionService $vatDecisionService): JsonResponse
    {
        $request->validate([
            'client_id' => ['required', 'integer'],
        ]);

        $companyId = auth()->user()->company_id;
        $client = \App\Models\Client::where('id', $request->input('client_id'))
            ->where('company_id', $companyId)
            ->with('vatIdentity')
            ->first();

        if (!$client) {
            abort(404);
        }

        $sellerCompany = auth()->user()->company;
        // Pass user to check VIES validation permission for EU_B2B_RC auto-suggestion
        $decision = $vatDecisionService->decide($sellerCompany, $client, auth()->user());

        // Determine if invoice can be issued based on client VAT validation status
        $canIssue = true;
        $blockReason = null;

        if ($decision->taxTreatment === 'EU_B2B_RC') {
            // Reverse charge requires valid VAT ID
            $vatStatus = $client->vatIdentity?->status ?? 'unknown';
            if ($vatStatus !== 'valid') {
                $canIssue = false;
                if ($vatStatus === 'pending') {
                    $blockReason = 'Client VAT ID validation is pending. Invoice cannot be issued until VAT ID is validated.';
                } elseif ($vatStatus === 'invalid') {
                    $blockReason = 'Client VAT ID is invalid. Reverse charge cannot be applied.';
                } else {
                    $blockReason = 'Client VAT ID validation status is unknown. Invoice cannot be issued until VAT ID is validated.';
                }
            }
        }

        return response()->json([
            'client_id' => $client->id,
            'client_vat_status' => $client->vatIdentity?->status ?? 'unknown',
            'tax_treatment' => $decision->taxTreatment,
            'vat_rate' => number_format($decision->vatRate, 2, '.', ''),
            'reason_text' => $decision->reasonText,
            'can_issue' => $canIssue,
            'block_reason' => $blockReason,
        ]);
    }

    /**
     * Calculate VAT rate for a specific tax treatment.
     *
     * @param \App\Models\Company $company
     * @param \App\Models\Client $client
     * @param string $taxTreatment
     * @return float
     */
    private function calculateVatRateForTreatment(\App\Models\Company $company, \App\Models\Client $client, string $taxTreatment): float
    {
        // Get seller VAT rate (override -> company default_vat_rate)
        $sellerVatRate = $company->vat_override_enabled && $company->vat_override_rate !== null
            ? (float)$company->vat_override_rate
            : (float)$company->default_vat_rate;

        return match ($taxTreatment) {
            'DOMESTIC' => $sellerVatRate,
            'EU_B2B_RC' => 0.0,
            'EU_B2C' => $sellerVatRate,
            'NON_EU' => 0.0,
            default => $sellerVatRate,
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
}
