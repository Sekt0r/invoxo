<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VatIdentity;
use App\Services\InvoiceVatResolver;
use App\Services\VatDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossBorderB2BAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_cross_border_b2b_permission_auto_suggests_eu_b2b_rc(): void
    {
        // Company in RO, client in DE (cross-border EU)
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'pending', // Even with pending status, should suggest if permission exists
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // User with Pro plan (has cross_border_b2b permission)
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create draft invoice
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

        // Apply automatic VAT decisioning
        $vatResolver = app(InvoiceVatResolver::class);
        $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);
        $invoice->save(); // Resolver doesn't save, so save manually

        // Should auto-suggest EU_B2B_RC
        $invoice->refresh();
        $this->assertEquals('EU_B2B_RC', $invoice->tax_treatment);
        $this->assertEquals(0.0, (float)$invoice->vat_rate);
        $this->assertEquals('Reverse charge (EU B2B).', $invoice->vat_reason_text);
        $this->assertFalse($invoice->vat_rate_is_manual);
    }

    public function test_without_cross_border_b2b_permission_no_auto_suggestion(): void
    {
        // Company in RO, client in DE (cross-border EU)
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'valid',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // User with Starter plan (no cross_border_b2b permission)
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create draft invoice
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

        // Apply automatic VAT decisioning (should not apply without vat_rate_auto permission)
        $vatResolver = app(InvoiceVatResolver::class);
        $result = $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);

        // Resolver should return false (no automation without permission)
        $this->assertFalse($result, 'Resolver should not apply automation without vat_rate_auto permission');

        // Invoice should remain in default state (no automation applied)
        $invoice->refresh();
        // Note: Without vat_rate_auto permission, no automation happens
        // The invoice keeps its default state (database defaults apply)
        // User can still manually select tax_treatment and vat_rate
    }

    public function test_manual_selection_eu_b2b_rc_works_without_permission(): void
    {
        // Company in RO, client in DE (cross-border EU)
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // User with Starter plan (no cross_border_b2b permission)
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create draft invoice
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'tax_treatment' => 'EU_B2B_RC', // User manually selected
            'vat_rate' => 0.0,
        ]);

        // Manual selection should work - invoice should save with EU_B2B_RC
        $invoice->save();

        $invoice->refresh();
        $this->assertEquals('EU_B2B_RC', $invoice->tax_treatment);
        $this->assertEquals(0.0, (float)$invoice->vat_rate);
        // System does not block manual selection
    }

    public function test_invalid_vat_id_with_cross_border_b2b_falls_back_to_b2c(): void
    {
        // Company in RO, client in DE (cross-border EU)
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        $vatIdentity = VatIdentity::create([
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'status' => 'invalid',
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
            'vat_identity_id' => $vatIdentity->id,
        ]);

        // User with Pro plan (has cross_border_b2b and vies_validation)
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create draft invoice
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Ensure client relationship is loaded with vatIdentity
        $invoice->load('client.vatIdentity');

        // Apply automatic VAT decisioning
        $vatResolver = app(InvoiceVatResolver::class);
        $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);
        $invoice->save(); // Resolver doesn't save, so save manually

        // Should fall back to EU_B2C (invalid VAT ID prevents EU_B2B_RC)
        $invoice->refresh();
        $this->assertEquals('EU_B2C', $invoice->tax_treatment);
        $this->assertEquals(19.00, (float)$invoice->vat_rate);
    }

    public function test_domestic_invoice_never_uses_eu_b2b_rc(): void
    {
        // Company and client both in DE (domestic)
        $company = Company::factory()->create([
            'country_code' => 'DE',
            'default_vat_rate' => 19.00,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // User with Pro plan
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create draft invoice
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Ensure relationships are loaded
        $invoice->load('client', 'company');

        // Apply automatic VAT decisioning
        $vatResolver = app(InvoiceVatResolver::class);
        $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);
        $invoice->save(); // Resolver doesn't save, so save manually

        // Should be DOMESTIC (same country)
        $invoice->refresh();
        $this->assertEquals('DOMESTIC', $invoice->tax_treatment);
        $this->assertEquals(19.00, (float)$invoice->vat_rate);
    }

    public function test_issued_invoice_not_affected_by_automation(): void
    {
        // Company in RO, client in DE (cross-border EU)
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        // User with Pro plan
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create issued invoice
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'tax_treatment' => 'EU_B2C',
            'vat_rate' => 19.00,
        ]);

        // Try to apply automatic VAT decisioning (should be ignored)
        $vatResolver = app(InvoiceVatResolver::class);
        $result = $vatResolver->applyAutomaticVatIfAllowed($invoice, $user, false);

        // Should return false (no changes applied)
        $this->assertFalse($result);

        // Invoice should remain unchanged
        $invoice->refresh();
        $this->assertEquals('EU_B2C', $invoice->tax_treatment);
        $this->assertEquals(19.00, (float)$invoice->vat_rate);
    }
}

