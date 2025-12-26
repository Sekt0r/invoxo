<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCurrencyConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_create_shows_no_currency_select_when_no_bank_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Ensure no bank accounts
        $company->bankAccounts()->delete();

        $response = $this->actingAs($user)->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('No bank accounts configured');
        $response->assertDontSee('name="currency"');
    }

    public function test_invoice_create_shows_only_bank_account_currencies(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
        ]);

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
        ]);

        // Should not show GBP even if it's in config
        $response = $this->actingAs($user)->get(route('invoices.create'));

        $response->assertOk();
        // Check for currency options in the select dropdown
        $response->assertSee('EUR', false);
        $response->assertSee('USD', false);
        $response->assertDontSee('GBP', false);
    }

    public function test_invoice_create_defaults_to_default_account_currency(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'is_default' => false,
        ]);

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.create'));

        $response->assertOk();
        $response->assertViewHas('defaultCurrency', 'USD');
    }

    public function test_invoice_create_defaults_to_first_currency_if_no_default_account(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'is_default' => false,
        ]);

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.create'));

        $response->assertOk();
        // Should default to first currency (sorted)
        $response->assertViewHas('defaultCurrency', 'EUR');
    }

    public function test_invoice_validation_rejects_currency_not_in_bank_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'GBP', // Not in bank accounts
            'items' => [
                [
                    'description' => 'Test item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['currency']);
    }

    public function test_invoice_validation_allows_null_currency_when_no_bank_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Ensure no bank accounts
        $company->bankAccounts()->delete();

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'items' => [
                [
                    'description' => 'Test item',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
        ]);

        // Should succeed (draft allowed without currency)
        $response->assertRedirect();
        $this->assertDatabaseHas('invoices', [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'currency' => null,
            'status' => 'draft',
        ]);
    }

    public function test_invoice_issue_blocks_when_no_bank_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Ensure company has all required seller fields
        $company->update([
            'name' => 'Test Company',
            'country_code' => 'GB',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);

        // Ensure no bank accounts
        $company->bankAccounts()->delete();

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => null,
        ]);

        \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors(['vat']);

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
    }

    public function test_invoice_issue_blocks_when_currency_has_no_matching_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Ensure company has all required seller fields
        $company->update([
            'name' => 'Test Company',
            'country_code' => 'GB',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);

        BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'GBP', // Not in bank accounts
        ]);

        \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        // Should have error in session
        $response->assertSessionHasErrors();

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
    }

    public function test_invoice_issue_snapshots_all_matching_currency_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => null, // B2C
        ]);

        // Ensure company has all required seller fields
        $company->update([
            'name' => 'Test Company',
            'country_code' => 'GB',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);

        $account1 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'GB82WEST12345698765432',
            'nickname' => 'Main EUR',
        ]);

        $account2 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'GB99WEST98765432123456',
            'nickname' => 'Secondary EUR',
        ]);

        $account3 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'iban' => 'GB11WEST11111111111111',
            'nickname' => 'USD Account',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'EUR',
        ]);

        \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        $this->assertNotNull($invoice->payment_details);

        // Verify payment_details contains all EUR accounts
        $accounts = $invoice->payment_details['accounts'] ?? [];
        $this->assertCount(2, $accounts);

        $ibans = collect($accounts)->pluck('iban')->all();
        $this->assertContains('GB82WEST12345698765432', $ibans);
        $this->assertContains('GB99WEST98765432123456', $ibans);
        $this->assertNotContains('GB11WEST11111111111111', $ibans); // USD account

        // Verify snapshot is immutable
        $account3->delete(); // Delete one EUR account after issue

        $invoice->refresh();
        $snapshotAccounts = $invoice->payment_details['accounts'] ?? [];
        $this->assertCount(2, $snapshotAccounts); // Should still have 2 in snapshot
    }
}
