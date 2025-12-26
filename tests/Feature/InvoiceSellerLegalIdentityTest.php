<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceSellerLegalIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_issue_without_seller_vat_id_if_registration_number_present(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
            'vat_id' => null, // No VAT ID
            'registration_number' => 'RO123456', // Has registration number
            'tax_identifier' => 'RO12345678',
            'address_line1' => 'Test Street 1',
            'city' => 'Bucharest',
            'postal_code' => '010001',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO',
            'vat_id' => null, // B2C client (no VAT ID)
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        $this->assertNotNull($invoice->number);
    }

    public function test_cannot_issue_if_registration_number_missing(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
            'registration_number' => null, // Missing registration number
            'tax_identifier' => null,
            'address_line1' => null,
            'city' => null,
            'postal_code' => null,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO',
            'vat_id' => null,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
        $this->assertNull($invoice->number);

        // Verify error message mentions legal identity
        $errors = $response->getSession()->get('errors');
        $this->assertStringContainsString('company details', $errors->first('vat'));
    }

    public function test_cannot_issue_if_company_name_missing(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'name' => '', // Empty name
            'country_code' => 'RO',
            'registration_number' => 'RO123456',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
    }

    public function test_cannot_issue_if_country_code_missing(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'name' => 'Test Company',
            'country_code' => '', // Empty country
            'registration_number' => 'RO123456',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create bank account for issuance requirement
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
    }
}

