<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceMoneyInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_decimal_input_is_converted_to_minor_units(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => 12.50, // Decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));

        $invoice = Invoice::where('company_id', $company->id)->first();
        $this->assertNotNull($invoice);

        $item = $invoice->invoiceItems()->first();
        $this->assertNotNull($item);
        $this->assertEquals(1250, $item->unit_price_minor); // 12.50 * 100 = 1250
    }

    public function test_rounding_is_consistent(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Test rounding half-up: 10.005 should round to 10.01 -> 1001 minor units
        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => 10.005,
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));

        $invoice = Invoice::where('company_id', $company->id)->first();
        $item = $invoice->invoiceItems()->first();
        // 10.005 * 100 = 1000.5, rounded half-up = 1001
        $this->assertEquals(1001, $item->unit_price_minor);
    }

    public function test_totals_match_expected_minor_units(): void
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

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 2.0,
                    'unit_price' => 19.99, // Decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));

        $invoice = Invoice::where('company_id', $company->id)->first();
        $invoice->load('invoiceItems');

        $item = $invoice->invoiceItems()->first();
        // 19.99 * 100 = 1999 minor units
        $this->assertEquals(1999, $item->unit_price_minor);
        // Line total: 2.0 * 1999 = 3998 minor units
        $this->assertEquals(3998, $item->line_total_minor);

        // Invoice totals
        $this->assertEquals(3998, $invoice->subtotal_minor); // 2 * 19.99 = 39.98
        // VAT: 3998 * 0.19 = 759.62, rounded half-up = 760
        $this->assertEquals(760, $invoice->vat_minor);
        $this->assertEquals(4758, $invoice->total_minor); // 3998 + 760 = 4758
    }

    public function test_invalid_decimal_rejected(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Test negative price
        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => -1.0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.unit_price');
    }

    public function test_update_converts_decimal_to_minor_units(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'unit_price_minor' => 1000, // Old value in minor units
        ]);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Updated Item',
                    'quantity' => 1.0,
                    'unit_price' => 25.75, // Decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $item = $invoice->invoiceItems()->first();
        // 25.75 * 100 = 2575 minor units
        $this->assertEquals(2575, $item->unit_price_minor);
    }
}







