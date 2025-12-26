<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoiceTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_requires_auth(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
        ]);

        $response = $this->get(route('invoices.pdf', $invoice));

        $response->assertRedirect(route('login'));
    }

    public function test_pdf_other_company_forbidden(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);
        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($userA)->get(route('invoices.pdf', $invoiceB));

        $response->assertStatus(403);
    }

    public function test_pdf_draft_redirects_with_error(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('pdf');
    }

    public function test_pdf_issued_returns_pdf(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Client',
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'number' => 'INV-2025-000001',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => 2.0,
            'unit_price_minor' => 5000,
            'line_total_minor' => 0,
        ]);

        $invoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        $response = $this->actingAs($user)->get(route('invoices.pdf', $invoice));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}

