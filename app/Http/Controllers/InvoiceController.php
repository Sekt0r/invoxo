<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Models\Invoice;
use App\Services\InvoiceTotalsService;
use App\Services\VatDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = auth()->user()->company_id;
        $invoices = Invoice::where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        return view('invoice.index', [
            'invoices' => $invoices,
        ]);
    }

    public function create(Request $request): View
    {
        return view('invoice.create');
    }

    public function store(InvoiceStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $invoice = Invoice::create([
            'company_id' => $data['company_id'],
            'client_id' => $data['client_id'],
            'issue_date' => $data['issue_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'status' => 'draft',
        ]);

        $company = auth()->user()->company;
        $decision = app(\App\Services\VatDecisionService::class)->decide($company, $invoice->client);

        $invoice->tax_treatment = $decision->taxTreatment;
        $invoice->vat_rate = $decision->vatRate;
        $invoice->vat_reason_text = $decision->reasonText;
        $invoice->save();

        foreach ($data['items'] as $item) {
            $invoice->invoiceItems()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price_minor' => $item['unit_price_minor'],
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
        return view('invoice.show', [
            'invoice' => $invoice,
        ]);
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        return view('invoice.edit', [
            'invoice' => $invoice,
        ]);
    }

    public function update(InvoiceUpdateRequest $request, Invoice $invoice): RedirectResponse
    {
        $invoice->update($request->validated());

        $request->session()->flash('invoice.id', $invoice->id);

        return redirect()->route('invoices.index');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $invoice->delete();

        return redirect()->route('invoices.index');
    }
}
