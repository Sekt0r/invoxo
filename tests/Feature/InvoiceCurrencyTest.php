<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_invoice_in_usd(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create bank account with USD currency
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'USD',
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00, // $100.00 decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));

        $invoice = Invoice::where('company_id', $company->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('USD', $invoice->currency);
        $this->assertGreaterThan(0, $invoice->total_minor);
        $this->assertEquals(10000, $invoice->subtotal_minor);
    }

    public function test_currency_must_be_allowed(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create bank account with valid currency (EUR)
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'JPY', // JPY is not in allowed currencies AND not in bank accounts
            'items' => [
                [
                    'description' => 'Test Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

        $response->assertSessionHasErrors('currency');
    }

    public function test_currency_can_be_changed_while_draft(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create bank accounts for both currencies
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'USD',
            'items' => [
                [
                    'description' => 'Updated Item',
                    'quantity' => 2.0,
                    'unit_price' => 50.00, // $50.00 decimal input
                ],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('USD', $invoice->currency);
    }

    public function test_currency_cannot_be_changed_after_issue(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'EUR',
        ]);

        // Issue the invoice
        $invoice->status = 'issued';
        $invoice->number = 'INV-001';
        $invoice->save();

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $client->id,
            'currency' => 'USD',
            'items' => [
                [
                    'description' => 'Updated Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00,
                ],
            ],
        ]);

        // Should redirect to show (because status != draft)
        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('EUR', $invoice->currency); // Currency unchanged
    }

    public function test_currency_is_tenant_scoped_through_invoice_access(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'currency' => 'USD',
            'status' => 'draft',
        ]);

        // Attempt to view
        $response = $this->actingAs($userA)->get(route('invoices.show', $invoiceB));
        $response->assertStatus(403);

        // Attempt to edit
        $response = $this->actingAs($userA)->get(route('invoices.edit', $invoiceB));
        $response->assertStatus(403);

        // Attempt to update (use clientA so validation passes, but tenant check should still 403)
        $response = $this->actingAs($userA)->put(route('invoices.update', $invoiceB), [
            'client_id' => $clientA->id,
            'currency' => 'EUR',
            'items' => [
                [
                    'description' => 'Hacked Item',
                    'quantity' => 1.0,
                    'unit_price' => 100.00,
                ],
            ],
        ]);
        $response->assertStatus(403);

        $invoiceB->refresh();
        $this->assertEquals('USD', $invoiceB->currency); // Unchanged
    }
}
