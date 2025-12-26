<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceSellerSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_invoice_has_seller_details_snapshot(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Ensure company has complete seller details
        $company->update([
            'name' => 'Test Company Ltd',
            'country_code' => 'GB',
            'registration_number' => 'REG123456',
            'tax_identifier' => 'TAX789',
            'address_line1' => '123 Test Street',
            'address_line2' => 'Suite 100',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'vat_id' => 'GB123456789',
        ]);

        // Create bank account matching invoice currency
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'GBP',
            'iban' => 'GB82WEST12345698765432',
        ]);

        $client = \App\Models\Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => null, // B2C
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'GBP',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price_minor' => 10000,
        ]);

        // Issue invoice via controller
        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));
        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
        $this->assertNotNull($invoice->seller_details);
        $this->assertIsArray($invoice->seller_details);

        // Verify snapshot content
        $snapshot = $invoice->seller_details;
        $this->assertEquals('Test Company Ltd', $snapshot['company_name']);
        $this->assertEquals('GB', $snapshot['country_code']);
        $this->assertEquals('REG123456', $snapshot['registration_number']);
        $this->assertEquals('TAX789', $snapshot['tax_identifier']);
        $this->assertEquals('123 Test Street', $snapshot['address_line1']);
        $this->assertEquals('Suite 100', $snapshot['address_line2']);
        $this->assertEquals('London', $snapshot['city']);
        $this->assertEquals('SW1A 1AA', $snapshot['postal_code']);
        $this->assertEquals('GB123456789', $snapshot['vat_id']);
    }

    public function test_issued_invoice_snapshot_remains_unchanged_after_company_update(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Set initial company details
        $company->update([
            'name' => 'Original Company',
            'country_code' => 'GB',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Old Street',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
        ]);

        // Create bank account matching invoice currency
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'GBP',
            'iban' => 'GB82WEST12345698765432',
        ]);

        $client = \App\Models\Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => null,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'currency' => 'GBP',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price_minor' => 10000,
        ]);

        // Issue invoice via controller
        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));
        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $originalSnapshot = $invoice->seller_details;

        // Update company details
        $company->update([
            'name' => 'Updated Company',
            'address_line1' => '456 New Street',
            'city' => 'Manchester',
            'postal_code' => 'M1 1AA',
            'registration_number' => 'REG999',
        ]);

        // Invoice snapshot should remain unchanged
        $invoice->refresh();
        $this->assertEquals($originalSnapshot, $invoice->seller_details);
        $this->assertEquals('Original Company', $invoice->seller_details['company_name']);
        $this->assertEquals('123 Old Street', $invoice->seller_details['address_line1']);
        $this->assertEquals('London', $invoice->seller_details['city']);
        $this->assertEquals('REG123', $invoice->seller_details['registration_number']);
    }

    public function test_draft_invoice_uses_live_company_data(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $company->update([
            'name' => 'Live Company',
            'country_code' => 'GB',
            'registration_number' => 'REG123',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Live Street',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
        ]);

        $client = \App\Models\Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => null,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Draft should have no snapshot
        $this->assertNull($invoice->seller_details);

        // Update company
        $company->update([
            'name' => 'Updated Live Company',
            'address_line1' => '456 Updated Street',
        ]);

        // Draft invoice should reflect live changes (through relationship)
        $invoice->load('company');
        $this->assertEquals('Updated Live Company', $invoice->company->name);
        $this->assertEquals('456 Updated Street', $invoice->company->address_line1);
    }
}
