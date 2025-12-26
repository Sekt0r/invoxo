<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\VatIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceIssuanceVatGateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a company with all required fields and a bank account
     */
    private function createCompanyWithBankAccount(array $companyOverrides = []): array
    {
        $company = Company::factory()->create(array_merge([
            'country_code' => 'RO',
            'vat_id' => null,
            'default_vat_rate' => 19.00,
            'registration_number' => 'RO123456',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Main St',
            'city' => 'Bucharest',
            'postal_code' => '12345',
        ], $companyOverrides));

        $user = User::factory()->create(['company_id' => $company->id]);

        // Create bank account for invoice currency
        $bankAccount = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        return [$company, $user, $bankAccount];
    }

    public function test_cannot_issue_when_client_vat_pending(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        [$sellerCompany, $user] = $this->createCompanyWithBankAccount();

        // Use factory default (null last_checked_at = stale/pending)
        $vatIdentity = VatIdentity::factory()->create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'pending',
        ]);

        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR', // Must match bank account currency
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
        $this->assertNull($invoice->number);
    }

    public function test_cannot_issue_when_client_vat_unknown(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        [$sellerCompany, $user] = $this->createCompanyWithBankAccount();

        // Client has vat_id but no vat_identity_id (unknown status)
        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => null,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR', // Must match bank account currency
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
        $this->assertNull($invoice->number);
    }

    public function test_invalid_vat_id_forces_b2c_and_allows_issue(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        [$sellerCompany, $user] = $this->createCompanyWithBankAccount();

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'invalid',
            'last_checked_at' => now(),
        ]);

        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR', // Must match bank account currency
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        $this->assertNotNull($invoice->number);
        // Should be EU_B2C (seller VAT applies) since VAT ID is invalid
        $this->assertEquals('EU_B2C', $invoice->tax_treatment);
        $this->assertEquals(19.00, (float)$invoice->vat_rate);
        $this->assertEquals('invalid', $invoice->client_vat_status_snapshot);
        $this->assertEquals('DE123456789', $invoice->client_vat_id_snapshot);
        $this->assertNotNull($invoice->vat_decided_at);
    }

    public function test_missing_vat_id_forces_b2c_and_allows_issue(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        [$sellerCompany, $user] = $this->createCompanyWithBankAccount();

        // Client with no VAT ID
        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => null,
            'vat_identity_id' => null,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR', // Must match bank account currency
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        // Should be EU_B2C since no VAT ID
        $this->assertEquals('EU_B2C', $invoice->tax_treatment);
        $this->assertEquals(19.00, (float)$invoice->vat_rate);
        $this->assertEquals('invalid', $invoice->client_vat_status_snapshot);
        $this->assertNull($invoice->client_vat_id_snapshot);
    }

    public function test_valid_vat_id_allows_b2b(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        [$sellerCompany, $user] = $this->createCompanyWithBankAccount();

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'last_checked_at' => now(),
        ]);

        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR', // Must match bank account currency
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        // Should be EU_B2B_RC (reverse charge) since VAT ID is valid
        $this->assertEquals('EU_B2B_RC', $invoice->tax_treatment);
        $this->assertEquals(0.00, (float)$invoice->vat_rate);
        $this->assertEquals('valid', $invoice->client_vat_status_snapshot);
        $this->assertEquals('DE123456789', $invoice->client_vat_id_snapshot);
        $this->assertNotNull($invoice->vat_decided_at);
    }
}
