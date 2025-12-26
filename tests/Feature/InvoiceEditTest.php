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

class InvoiceEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_draft_invoice_and_totals_update(): void
    {
        $company = Company::factory()->create([
            'default_vat_rate' => 19.0,
            'country_code' => 'DE',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE', // Same country = DOMESTIC = 19% VAT
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate' => 19.0,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Old Item',
            'quantity' => 1.0,
            'unit_price_minor' => 10000, // €100.00
            'line_total_minor' => 10000,
        ]);

        $invoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        $oldTotal = $invoice->total_minor;

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'issue_date' => '2025-01-15',
            'due_date' => '2025-02-15',
            'items' => [
                [
                    'description' => 'New Item 1',
                    'quantity' => 2.0,
                    'unit_price' => 50.00, // €50.00 decimal input
                ],
                [
                    'description' => 'New Item 2',
                    'quantity' => 1.0,
                    'unit_price' => 30.00, // €30.00 decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('status');

        $invoice->refresh();
        $this->assertEquals('2025-01-15', $invoice->issue_date->format('Y-m-d'));
        $this->assertEquals('2025-02-15', $invoice->due_date->format('Y-m-d'));

        // Totals should be recalculated: (2 * 5000 + 1 * 3000) = 13000 subtotal, + 19% VAT = 2470, total = 15470
        $this->assertEquals(13000, $invoice->subtotal_minor);
        $this->assertEquals(2470, $invoice->vat_minor); // 19% of 13000 = 2470
        $this->assertEquals(15470, $invoice->total_minor);
        $this->assertNotEquals($oldTotal, $invoice->total_minor);

        // Verify items were replaced
        $this->assertEquals(2, $invoice->invoiceItems()->count());
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'New Item 1',
            'quantity' => 2.0,
            'unit_price_minor' => 5000,
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'New Item 2',
            'quantity' => 1.0,
            'unit_price_minor' => 3000,
        ]);
        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Old Item',
        ]);
    }

    public function test_cannot_update_issued_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->issued()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'number' => 'INV-2025-000001',
        ]);

        $oldIssueDate = $invoice->issue_date;
        $oldDueDate = $invoice->due_date;

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'issue_date' => '2025-01-20',
            'due_date' => '2025-02-20',
            'items' => [
                [
                    'description' => 'New Item',
                    'quantity' => 1.0,
                    'unit_price' => 50.00,
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals($oldIssueDate->format('Y-m-d'), $invoice->issue_date->format('Y-m-d'));
        $this->assertEquals($oldDueDate?->format('Y-m-d'), $invoice->due_date?->format('Y-m-d'));
    }

    public function test_cannot_set_client_from_other_company(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($userA)->put(route('invoices.update', $invoice), [
            'client_id' => $clientB->id, // Trying to use client from company B
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Item',
                    'quantity' => 1.0,
                    'unit_price' => 50.00,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('client_id');

        $invoice->refresh();
        $this->assertEquals($clientA->id, $invoice->client_id);
    }

    public function test_other_company_invoice_update_forbidden(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'status' => 'draft',
        ]);

        $oldClientId = $invoiceB->client_id;

        $response = $this->actingAs($userA)->put(route('invoices.update', $invoiceB), [
            'client_id' => $clientA->id, // Use client from companyA so validation passes
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Hacked Item',
                    'quantity' => 1.0,
                    'unit_price' => 50.00,
                ],
            ],
        ]);

        $response->assertStatus(403);

        $invoiceB->refresh();
        $this->assertEquals($oldClientId, $invoiceB->client_id);
    }

    public function test_update_replaces_items_for_draft_invoice(): void
    {
        $company = Company::factory()->create([
            'default_vat_rate' => 19.0,
            'country_code' => 'DE',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Create old items
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Old Item 1',
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Old Item 2',
            'quantity' => 2.0,
            'unit_price_minor' => 5000,
        ]);

        $this->assertEquals(2, $invoice->invoiceItems()->count());

        // Update with new items
        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'New Item 1',
                    'quantity' => 3.0,
                    'unit_price' => '15.50', // String format
                ],
                [
                    'description' => 'New Item 2',
                    'quantity' => 1.0,
                    'unit_price' => '25.00', // String format
                ],
                [
                    'description' => 'New Item 3',
                    'quantity' => 2.0,
                    'unit_price' => '10.25', // String format
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();

        // Old items should be deleted
        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Old Item 1',
        ]);
        $this->assertDatabaseMissing('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Old Item 2',
        ]);

        // New items should exist with correct minor unit conversion
        $this->assertEquals(3, $invoice->invoiceItems()->count());
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'New Item 1',
            'quantity' => 3.0,
            'unit_price_minor' => 1550, // 15.50 * 100
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'New Item 2',
            'quantity' => 1.0,
            'unit_price_minor' => 2500, // 25.00 * 100
        ]);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'New Item 3',
            'quantity' => 2.0,
            'unit_price_minor' => 1025, // 10.25 * 100
        ]);
    }
}
