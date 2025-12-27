<?php

namespace Tests\Feature;

use App\Jobs\RecomputeDraftInvoicesForCompanyJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceTotalsService;
use App\Services\VatDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DraftInvoiceRecomputeTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_override_recomputes_draft_totals(): void
    {
        Queue::fake();

        // Create company with initial VAT settings
        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_override_enabled' => false,
            'vat_override_rate' => null,
            'default_vat_rate' => 19.00,
        ]);

        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => null,
        ]);

        // Create draft invoice with items
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate' => 19.00, // Initial rate
            'tax_treatment' => 'DOMESTIC',
        ]);

        $item1 = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 2.0,
            'unit_price_minor' => 10000, // 100.00 EUR
            'line_total_minor' => 20000, // 200.00 EUR
        ]);

        $item2 = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 5000, // 50.00 EUR
            'line_total_minor' => 5000, // 50.00 EUR
        ]);

        // Calculate initial totals manually (250.00 EUR subtotal, 19% VAT = 47.50 EUR, total = 297.50 EUR)
        $totalsService = new InvoiceTotalsService();
        $totalsService->recalculate($invoice);
        $invoice->save();

        $originalSubtotal = $invoice->subtotal_minor;
        $originalVat = $invoice->vat_minor;
        $originalTotal = $invoice->total_minor;
        $originalVatRate = $invoice->vat_rate;

        $user = User::factory()->create(['company_id' => $company->id]);

        // Update company VAT override via settings endpoint
        $response = $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => 'DE',
            'vat_id' => $company->vat_id,
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Street',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'default_vat_rate' => $company->default_vat_rate, // Required field
            'invoice_prefix' => $company->invoice_prefix,
            'vat_override_enabled' => '1',
            'vat_override_rate' => 25.00, // New override rate
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        // Assert job was dispatched
        Queue::assertPushed(RecomputeDraftInvoicesForCompanyJob::class, function ($job) use ($company) {
            return $job->companyId === $company->id;
        });

        // Run the job synchronously for this test
        $job = new RecomputeDraftInvoicesForCompanyJob($company->id);
        $job->handle(app(\App\Services\InvoiceVatResolver::class), new InvoiceTotalsService());

        // Refresh invoice and verify totals changed
        $invoice->refresh();
        $this->assertEquals(25.00, (float)$invoice->vat_rate, 'VAT rate should be updated to override rate');
        $this->assertEquals('DOMESTIC', $invoice->tax_treatment);

        // Verify totals were recalculated with new VAT rate
        // Subtotal: 250.00 EUR (unchanged)
        // VAT: 250.00 * 0.25 = 62.50 EUR (changed from 47.50)
        // Total: 312.50 EUR (changed from 297.50)
        $this->assertEquals($originalSubtotal, $invoice->subtotal_minor, 'Subtotal should remain unchanged');
        $this->assertNotEquals($originalVat, $invoice->vat_minor, 'VAT should be recalculated');
        $this->assertNotEquals($originalTotal, $invoice->total_minor, 'Total should be recalculated');

        // Verify exact values
        $expectedVat = (int)round($originalSubtotal * 0.25, 0, PHP_ROUND_HALF_UP);
        $expectedTotal = $originalSubtotal + $expectedVat;
        $this->assertEquals($expectedVat, $invoice->vat_minor);
        $this->assertEquals($expectedTotal, $invoice->total_minor);
    }

    public function test_issued_invoices_unchanged(): void
    {
        Queue::fake();

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_override_enabled' => false,
            'default_vat_rate' => 19.00,
        ]);

        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
        ]);

        // Create issued invoice (should not be touched)
        // Create as draft first, add items, then update to issued (respects InvoiceItem immutability)
        $issuedInvoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate' => 19.00,
            'tax_treatment' => 'DOMESTIC',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $issuedInvoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $issuedInvoice->update([
            'status' => 'issued',
            'subtotal_minor' => 10000,
            'vat_minor' => 1900,
            'total_minor' => 11900,
            'number' => 'INV-'.now()->year.'-000001',
        ]);

        // Also create a draft invoice that should be updated
        $draftInvoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate' => 19.00,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $draftInvoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);

        // Update company VAT settings
        $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => 'DE',
            'vat_id' => $company->vat_id,
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Street',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'default_vat_rate' => $company->default_vat_rate, // Required field
            'invoice_prefix' => $company->invoice_prefix,
            'vat_override_enabled' => '1',
            'vat_override_rate' => 25.00,
        ]);

        // Run the job
        $job = new RecomputeDraftInvoicesForCompanyJob($company->id);
        $job->handle(app(\App\Services\InvoiceVatResolver::class), new InvoiceTotalsService());

        // Verify issued invoice unchanged
        $issuedInvoice->refresh();
        $this->assertEquals(19.00, (float)$issuedInvoice->vat_rate);
        $this->assertEquals(10000, $issuedInvoice->subtotal_minor);
        $this->assertEquals(1900, $issuedInvoice->vat_minor);
        $this->assertEquals(11900, $issuedInvoice->total_minor);

        // Verify draft invoice was updated
        $draftInvoice->refresh();
        $this->assertEquals(25.00, (float)$draftInvoice->vat_rate);
    }

    public function test_tenant_scoping(): void
    {
        Queue::fake();

        // Create Company A
        $companyA = Company::factory()->create([
            'country_code' => 'DE',
            'vat_override_enabled' => false,
            'default_vat_rate' => 19.00,
        ]);

        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $clientA = Client::factory()->create([
            'company_id' => $companyA->id,
            'country_code' => 'DE',
        ]);

        $invoiceA = Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'draft',
            'vat_rate' => 19.00,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoiceA->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // Create Company B
        $companyB = Company::factory()->create([
            'country_code' => 'FR',
            'vat_override_enabled' => false,
            'default_vat_rate' => 20.00,
        ]);

        $clientB = Client::factory()->create([
            'company_id' => $companyB->id,
            'country_code' => 'FR',
        ]);

        $invoiceB = Invoice::factory()->create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'status' => 'draft',
            'vat_rate' => 20.00,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoiceB->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $userA = User::factory()->create(['company_id' => $companyA->id]);

        // Update Company A VAT settings
        $this->actingAs($userA)->put(route('settings.company.update'), [
            'name' => $companyA->name,
            'country_code' => 'DE',
            'vat_id' => $companyA->vat_id,
            'registration_number' => $companyA->registration_number ?? 'REG123',
            'tax_identifier' => $companyA->tax_identifier ?? 'TAX123',
            'address_line1' => $companyA->address_line1 ?? '123 Street',
            'city' => $companyA->city ?? 'City',
            'postal_code' => $companyA->postal_code ?? '12345',
            'default_vat_rate' => $companyA->default_vat_rate, // Required field
            'invoice_prefix' => $companyA->invoice_prefix,
            'vat_override_enabled' => '1',
            'vat_override_rate' => 25.00,
        ]);

        // Run the job for Company A
        $job = new RecomputeDraftInvoicesForCompanyJob($companyA->id);
        $job->handle(app(\App\Services\InvoiceVatResolver::class), new InvoiceTotalsService());

        // Verify Company A invoice updated
        $invoiceA->refresh();
        $this->assertEquals(25.00, (float)$invoiceA->vat_rate);

        // Verify Company B invoice unchanged
        $invoiceB->refresh();
        $this->assertEquals(20.00, (float)$invoiceB->vat_rate);
        $this->assertEquals($companyB->id, $invoiceB->company_id);
    }

    public function test_override_decision_disable_triggers_recompute(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_override_enabled' => true,
            'vat_override_rate' => 25.00,
            'default_vat_rate' => 19.00,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate' => 25.00, // Current override rate
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);

        // Disable override via overrideDecision endpoint
        $response = $this->actingAs($user)->post(route('settings.company.override-decision'), [
            'decision' => 'disable',
        ]);

        $response->assertRedirect(route('settings.company.edit'));

        // Assert job was dispatched
        Queue::assertPushed(RecomputeDraftInvoicesForCompanyJob::class, function ($job) use ($company) {
            return $job->companyId === $company->id;
        });

        // Run the job
        $job = new RecomputeDraftInvoicesForCompanyJob($company->id);
        $job->handle(app(\App\Services\InvoiceVatResolver::class), new InvoiceTotalsService());

        // Verify invoice updated to use official rate (19.00) instead of override (25.00)
        $invoice->refresh();
        $company->refresh();
        $this->assertFalse($company->vat_override_enabled);
        $this->assertEquals(19.00, (float)$invoice->vat_rate, 'Should use official rate after override disabled');
    }

    public function test_country_code_change_triggers_recompute(): void
    {
        Queue::fake();

        // Create tax rates for both countries
        TaxRate::create([
            'country_code' => 'DE',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        TaxRate::create([
            'country_code' => 'FR',
            'tax_type' => 'vat',
            'standard_rate' => 20.00,
            'source' => 'vatlayer',
        ]);

        $company = Company::factory()->create([
            'country_code' => 'DE',
            'vat_override_enabled' => false,
            'default_vat_rate' => 19.00,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => null, // No VAT ID = EU_B2C for cross-border EU
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate' => 19.00,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);

        // User needs Pro plan for vat_rate_auto permission (auto-decisioning)
        \App\Models\Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Change country code (should trigger recompute)
        $this->actingAs($user)->put(route('settings.company.update'), [
            'name' => $company->name,
            'country_code' => 'FR', // Changed from DE to FR
            'vat_id' => $company->vat_id,
            'registration_number' => $company->registration_number ?? 'REG123',
            'tax_identifier' => $company->tax_identifier ?? 'TAX123',
            'address_line1' => $company->address_line1 ?? '123 Street',
            'city' => $company->city ?? 'City',
            'postal_code' => $company->postal_code ?? '12345',
            'default_vat_rate' => $company->default_vat_rate, // Required field
            'invoice_prefix' => $company->invoice_prefix,
        ]);

        // Assert job was dispatched
        Queue::assertPushed(RecomputeDraftInvoicesForCompanyJob::class);

        // Run the job (will use user with Pro plan for vat_rate_auto permission)
        $job = new RecomputeDraftInvoicesForCompanyJob($company->id);
        $job->handle(app(\App\Services\InvoiceVatResolver::class), new InvoiceTotalsService());

        // Verify invoice was recomputed
        $invoice->refresh();
        $company->refresh();
        $this->assertEquals('FR', $company->country_code);
        // Seller is now FR, buyer is DE, so it's EU_B2C
        // Uses company.default_vat_rate (19.00) as baseline, not FR's official rate
        // Note: default_vat_rate is not auto-updated on country change
        $this->assertEquals('EU_B2C', $invoice->tax_treatment, 'DE client + FR seller = EU_B2C');
        $this->assertEquals(19.00, (float)$invoice->vat_rate, 'Uses company default_vat_rate (19.00), not FR official rate');
    }
}
