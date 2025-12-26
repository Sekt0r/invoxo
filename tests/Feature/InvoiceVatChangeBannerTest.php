<?php

namespace Tests\Feature;

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

class InvoiceVatChangeBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_banner_shows_when_vat_status_updated_after_vat_decided_at(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $sellerCompany = Company::factory()->create([
            'country_code' => 'RO',
            'vat_id' => null,
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $sellerCompany->id]);

        // Create VAT identity with status_updated_at in the future (after vat_decided_at)
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'status_updated_at' => now(),
            'last_checked_at' => now(),
        ]);

        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // Create invoice with vat_decided_at in the past
        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_decided_at' => now()->subDay(),
            'client_vat_status_snapshot' => 'pending',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Client VAT validation status changed since this draft was last computed');
        $response->assertSee('Previously:');
        $response->assertSee('pending', false);
        $response->assertSee('Current:');
        $response->assertSee('valid', false);
    }

    public function test_banner_hidden_when_no_vat_decided_at(): void
    {
        Queue::fake();

        $sellerCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $sellerCompany->id]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'status_updated_at' => now(),
        ]);

        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // Invoice with no vat_decided_at
        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_decided_at' => null,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('Client VAT validation status changed');
    }

    public function test_banner_hidden_when_status_updated_before_vat_decided_at(): void
    {
        Queue::fake();

        $sellerCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $sellerCompany->id]);

        // VAT identity updated in the past (before vat_decided_at)
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'status_updated_at' => now()->subDays(2),
            'last_checked_at' => now()->subDays(2),
        ]);

        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // Invoice with vat_decided_at more recent than status update
        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_decided_at' => now()->subDay(),
            'client_vat_status_snapshot' => 'pending',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('Client VAT validation status changed');
    }

    public function test_banner_shows_on_edit_page(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $sellerCompany = Company::factory()->create([
            'country_code' => 'RO',
            'vat_id' => null,
            'default_vat_rate' => 19.00,
        ]);
        $user = User::factory()->create(['company_id' => $sellerCompany->id]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'invalid',
            'status_updated_at' => now(),
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
            'vat_decided_at' => now()->subDay(),
            'client_vat_status_snapshot' => 'valid',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertSee('Client VAT validation status changed since this draft was last computed');
        $response->assertSee('Previously:');
        $response->assertSee('valid', false);
        $response->assertSee('Current:');
        $response->assertSee('invalid', false);
    }

    public function test_banner_hidden_for_issued_invoices(): void
    {
        Queue::fake();

        $sellerCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $sellerCompany->id]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'status_updated_at' => now(),
        ]);

        $client = Client::factory()->create([
            'company_id' => $sellerCompany->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // Issued invoice (banner should not show even if VAT changed)
        // Create as draft first, add items, then update to issued (respects InvoiceItem immutability)
        $invoice = Invoice::factory()->create([
            'company_id' => $sellerCompany->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_decided_at' => now()->subDay(),
            'client_vat_status_snapshot' => 'pending',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $invoice->update(['status' => 'issued', 'number' => 'INV-'.now()->year.'-000001']);

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('Client VAT validation status changed');
    }

    public function test_banner_uses_last_checked_at_when_status_updated_at_null(): void
    {
        Queue::fake();

        $sellerCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $sellerCompany->id]);

        // VAT identity with status_updated_at null but last_checked_at set
        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
            'status_updated_at' => null,
            'last_checked_at' => now(), // This should be used
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
            'vat_decided_at' => now()->subDay(),
            'client_vat_status_snapshot' => 'pending',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Client VAT validation status changed');
    }
}
