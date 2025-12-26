<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_lists_only_company_clients(): void
    {
        $companyA = Company::factory()->create(['name' => 'Company A']);
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA1 = Client::factory()->create([
            'company_id' => $companyA->id,
            'name' => 'Client A1',
        ]);
        $clientA2 = Client::factory()->create([
            'company_id' => $companyA->id,
            'name' => 'Client A2',
        ]);

        $companyB = Company::factory()->create(['name' => 'Company B']);
        $clientB1 = Client::factory()->create([
            'company_id' => $companyB->id,
            'name' => 'Client B1',
        ]);

        $response = $this->actingAs($userA)->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('Client A1');
        $response->assertSee('Client A2');
        $response->assertDontSee('Client B1');
    }

    public function test_user_can_create_invoice_for_own_client(): void
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
                    'unit_price' => 100.00, // Decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));

        $invoice = Invoice::where('company_id', $company->id)
            ->where('client_id', $client->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals($company->id, $invoice->company_id);
        $this->assertEquals($client->id, $invoice->client_id);
        $this->assertEquals('draft', $invoice->status);
    }

    public function test_user_cannot_create_invoice_for_other_company_client(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        $response = $this->actingAs($userA)->post(route('invoices.store'), [
            'client_id' => $clientB->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00, // Decimal input
                ],
            ],
        ]);

        $response->assertSessionHasErrors('client_id');
    }

    public function test_store_persists_multiple_items_and_converts_prices_to_minor(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Item 1',
                    'quantity' => 1.0,
                    'unit_price' => '12.34', // String format with 2 decimals
                ],
                [
                    'description' => 'Item 2',
                    'quantity' => 2.0,
                    'unit_price' => '0.99', // String format with 2 decimals
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));

        $invoice = Invoice::where('company_id', $company->id)
            ->where('client_id', $client->id)
            ->first();

        $this->assertNotNull($invoice);

        $items = $invoice->invoiceItems;
        $this->assertEquals(2, $items->count());

        // Check Item 1: unit_price_minor should be 1234 (12.34 * 100)
        $item1 = $items->firstWhere('description', 'Item 1');
        $this->assertNotNull($item1);
        $this->assertEquals(1234, $item1->unit_price_minor);

        // Check Item 2: unit_price_minor should be 99 (0.99 * 100)
        $item2 = $items->firstWhere('description', 'Item 2');
        $this->assertNotNull($item2);
        $this->assertEquals(99, $item2->unit_price_minor);
    }

    public function test_blank_rows_are_ignored_but_at_least_one_item_required(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Test: one valid row + one blank row -> only one persisted
        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Valid Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00,
                ],
                [
                    'description' => '',
                    'quantity' => '',
                    'unit_price' => '',
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));

        $invoice = Invoice::where('company_id', $company->id)
            ->where('client_id', $client->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(1, $invoice->invoiceItems()->count());
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'description' => 'Valid Item',
        ]);

        // Test: all blank rows -> validation fails
        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => '',
                    'quantity' => '',
                    'unit_price' => '',
                ],
                [
                    'description' => '',
                    'quantity' => '',
                    'unit_price' => '',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('items');
    }
}
