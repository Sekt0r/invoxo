<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_draft_items_and_totals_change(): void
    {
        $company = Company::factory()->create(['default_vat_rate' => 19.0]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => $company->country_code, // Same country for DOMESTIC VAT
        ]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'subtotal_minor' => 10000,
            'vat_minor' => 1900,
            'total_minor' => 11900,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Old Item',
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'due_date' => '2025-12-31',
            'items' => [
                [
                    'description' => 'New Item',
                    'quantity' => 2.0,
                    'unit_price' => 150.00, // €150.00 decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('status');

        $invoice->refresh();
        $invoice->load('invoiceItems');

        // Verify old item is deleted
        $this->assertEquals(1, $invoice->invoiceItems->count());
        $this->assertEquals('New Item', $invoice->invoiceItems->first()->description);

        // Verify totals are recalculated (2 * 15000 = 30000 subtotal, 19% VAT = 5700, total = 35700)
        $expectedSubtotal = 30000;
        $vatRate = (float)$invoice->vat_rate;
        $expectedVat = (int)round($expectedSubtotal * ($vatRate / 100.0), 0, PHP_ROUND_HALF_UP);
        $expectedTotal = $expectedSubtotal + $expectedVat;

        $this->assertEquals($expectedSubtotal, $invoice->subtotal_minor);
        $this->assertEquals($expectedVat, $invoice->vat_minor);
        $this->assertEquals($expectedTotal, $invoice->total_minor);
    }

    public function test_cannot_update_issued_invoice(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'due_date' => '2025-12-31',
            'items' => [
                [
                    'description' => 'New Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
    }

    public function test_cannot_set_client_from_other_company(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create(['company_id' => $companyA->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'draft',
        ]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($userA)->put(route('invoices.update', $invoice), [
            'client_id' => $clientB->id,
            'currency' => 'EUR',
            'due_date' => null,
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_cannot_update_another_company_invoice(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);
        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'status' => 'draft',
        ]);

        // Pass a valid client_id for companyA, but invoiceB belongs to companyB
        // The 403 check should happen before validation (but validation happens first in Laravel)
        // So we check that validation fails because client_id must belong to user's company
        // However, the controller check should also prevent this, but validation runs first
        // Let's use clientA which is valid for userA, but invoiceB belongs to companyB
        // The route model binding will still allow the request through, but controller checks invoice ownership
        $response = $this->actingAs($userA)->put(route('invoices.update', $invoiceB), [
            'client_id' => $clientA->id, // Valid for userA
            'currency' => 'EUR',
            'due_date' => null,
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

        $response->assertStatus(403);
    }
}

