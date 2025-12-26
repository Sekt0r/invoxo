<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicInvoiceShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_share_requires_token_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $response = $this->get(route('invoices.share', $invoice->public_id));

        $response->assertStatus(404);
    }

    public function test_public_share_with_invalid_token_returns_404(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
        ]);

        $response = $this->get(route('invoices.share', $invoice->public_id) . '?t=invalid-token');

        $response->assertStatus(404);
    }

    public function test_public_share_with_valid_token_returns_200_and_shows_totals(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Client',
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => 'INV-2025-000001',
            'status' => 'issued',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => 2.0,
            'unit_price_minor' => 5000, // €50.00
            'line_total_minor' => 0,
        ]);

        $invoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        $response = $this->get(route('invoices.share', $invoice->public_id) . '?t=' . $invoice->share_token);

        $response->assertStatus(200);
        $response->assertViewIs('invoice.share');
        $response->assertSee('Test Company', false);
        $response->assertSee('INV-2025-000001', false);
        $response->assertSee('€100.00', false); // 2 * 50.00 = 100.00 total (formatted)
    }

    public function test_public_share_with_draft_shows_draft_text(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'number' => null,
            'status' => 'draft',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $invoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        $response = $this->get(route('invoices.share', $invoice->public_id) . '?t=' . $invoice->share_token);

        $response->assertStatus(200);
        $response->assertSee('Draft', false);
        $response->assertSee('Test Company', false);
    }
}

