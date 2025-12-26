<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceBuyerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);
    }

    public function test_issued_invoice_captures_buyer_details_snapshot(): void
    {
        $company = Company::factory()->create([
            'name' => 'Seller Company',
            'country_code' => 'RO',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => 'Seller Street 1',
            'city' => 'Bucharest',
            'postal_code' => '010101',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original Buyer Name',
            'country_code' => 'RO',
            'vat_id' => null,
            'registration_number' => 'BUYER123',
            'tax_identifier' => 'BTAX456',
            'address_line1' => 'Buyer Street 1',
            'address_line2' => 'Suite 100',
            'city' => 'Cluj',
            'postal_code' => '400000',
        ]);

        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
        ]);

        $invoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        // Issue the invoice
        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));
        $response->assertRedirect();

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);

        // Verify buyer_details snapshot was captured
        $this->assertNotNull($invoice->buyer_details);
        $this->assertEquals('Original Buyer Name', $invoice->buyer_details['client_name']);
        $this->assertEquals('RO', $invoice->buyer_details['country_code']);
        $this->assertNull($invoice->buyer_details['vat_id']);
        $this->assertEquals('BUYER123', $invoice->buyer_details['registration_number']);
        $this->assertEquals('BTAX456', $invoice->buyer_details['tax_identifier']);
        $this->assertEquals('Buyer Street 1', $invoice->buyer_details['address_line1']);
        $this->assertEquals('Suite 100', $invoice->buyer_details['address_line2']);
        $this->assertEquals('Cluj', $invoice->buyer_details['city']);
        $this->assertEquals('400000', $invoice->buyer_details['postal_code']);
        $this->assertArrayHasKey('captured_at', $invoice->buyer_details);
    }

    public function test_issued_invoice_buyer_details_remain_stable_after_client_update(): void
    {
        $company = Company::factory()->create([
            'name' => 'Seller Company',
            'country_code' => 'RO',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => 'Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original Buyer',
            'country_code' => 'RO',
            'vat_id' => null,
            'address_line1' => 'Original Address',
            'city' => 'Original City',
            'postal_code' => '111111',
        ]);

        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
        ]);

        $invoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        // Issue the invoice
        $this->actingAs($user)->post(route('invoices.issue', $invoice));
        $invoice->refresh();

        // Capture the original snapshot
        $originalBuyerDetails = $invoice->buyer_details;

        // Now update the client
        $client->update([
            'name' => 'Changed Buyer Name',
            'address_line1' => 'Changed Address',
            'city' => 'Changed City',
        ]);

        // Reload invoice and verify snapshot is unchanged
        $invoice->refresh();
        $this->assertEquals($originalBuyerDetails['client_name'], $invoice->buyer_details['client_name']);
        $this->assertEquals($originalBuyerDetails['address_line1'], $invoice->buyer_details['address_line1']);
        $this->assertEquals($originalBuyerDetails['city'], $invoice->buyer_details['city']);
        $this->assertEquals('Original Buyer', $invoice->buyer_details['client_name']);
        $this->assertEquals('Original Address', $invoice->buyer_details['address_line1']);
    }

    public function test_buyer_details_is_immutable_after_issue(): void
    {
        $company = Company::factory()->create([
            'name' => 'Seller Company',
            'country_code' => 'RO',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => 'Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original Buyer',
            'country_code' => 'RO',
            'vat_id' => null,
        ]);

        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
        ]);

        $invoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($invoice);
        $invoice->save();

        // Issue the invoice
        $this->actingAs($user)->post(route('invoices.issue', $invoice));
        $invoice->refresh();

        $originalBuyerDetails = $invoice->buyer_details;

        // Attempt to modify buyer_details (should throw)
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $invoice->buyer_details = ['client_name' => 'Hacked Buyer'];
        $invoice->save();
    }

    public function test_legacy_issued_invoice_without_snapshot_still_renders(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'name' => 'Test Client',
        ]);

        // Create a "legacy" issued invoice without buyer_details (simulates old data)
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Manually set to issued without buyer_details (legacy scenario)
        $invoice->update([
            'status' => 'issued',
            'number' => 'LEGACY-2025-001',
            'buyer_details' => null, // Legacy invoice without snapshot
        ]);

        // Should still be viewable without errors
        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));
        $response->assertOk();
    }
}
