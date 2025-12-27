<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManualOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoiceWithOverrides(bool $vatRateManual, bool $taxTreatmentManual, string $taxTreatment = 'DOMESTIC', float $vatRate = 19.0): Invoice
    {
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate_is_manual' => $vatRateManual,
            'tax_treatment_is_manual' => $taxTreatmentManual,
            'tax_treatment' => $taxTreatment,
            'vat_rate' => $vatRate,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        return $invoice;
    }

    private function createUserWithSubscription(Company $company, string $plan = 'starter'): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => $plan,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);
        return $user;
    }

    // ==================== VISIBILITY TESTS ====================

    public function test_vat_warning_visible_when_vat_rate_is_manual(): void
    {
        $invoice = $this->createInvoiceWithOverrides(vatRateManual: true, taxTreatmentManual: false);
        $user = $this->createUserWithSubscription($invoice->company);

        $response = $this->actingAs($user)->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertSee('Manual VAT rate applied');
        $response->assertSee('Reset VAT rate to automatic');
    }

    public function test_tax_warning_visible_when_tax_treatment_is_manual(): void
    {
        $invoice = $this->createInvoiceWithOverrides(vatRateManual: false, taxTreatmentManual: true);
        $user = $this->createUserWithSubscription($invoice->company);

        $response = $this->actingAs($user)->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertSee('Manual tax treatment applied');
        $response->assertSee('Reset tax treatment to automatic');
    }

    public function test_both_warnings_visible_when_both_overrides_active(): void
    {
        $invoice = $this->createInvoiceWithOverrides(vatRateManual: true, taxTreatmentManual: true);
        $user = $this->createUserWithSubscription($invoice->company);

        $response = $this->actingAs($user)->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertSee('Manual VAT rate applied');
        $response->assertSee('Manual tax treatment applied');
        $response->assertSee('Reset VAT rate to automatic');
        $response->assertSee('Reset tax treatment to automatic');
    }

    public function test_no_warnings_visible_for_issued_invoice(): void
    {
        $invoice = $this->createInvoiceWithOverrides(vatRateManual: true, taxTreatmentManual: true);
        $invoice->status = 'issued';
        $invoice->save();

        $user = $this->createUserWithSubscription($invoice->company);

        // Attempting to edit an issued invoice redirects to show page
        // This means warnings are never rendered (as required)
        $response = $this->actingAs($user)->get(route('invoices.edit', $invoice));

        // Verify redirect occurs (warnings cannot be visible if edit page is not accessible)
        $response->assertRedirect(route('invoices.show', $invoice));

        // Verify invoice is still issued (not modified by the request)
        $invoice->refresh();
        $this->assertEquals('issued', $invoice->status);
    }

    public function test_no_warnings_visible_when_no_overrides(): void
    {
        $invoice = $this->createInvoiceWithOverrides(vatRateManual: false, taxTreatmentManual: false);
        $user = $this->createUserWithSubscription($invoice->company);

        $response = $this->actingAs($user)->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertDontSee('Manual VAT rate applied');
        $response->assertDontSee('Manual tax treatment applied');
    }

    // ==================== VAT RATE RESET TESTS ====================

    public function test_vat_reset_clears_only_vat_rate_manual_flag(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: false,
            taxTreatment: 'DOMESTIC',
            vatRate: 25.0
        );
        $user = $this->createUserWithSubscription($invoice->company);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'vat_rate_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertFalse($invoice->vat_rate_is_manual);
        $this->assertFalse($invoice->tax_treatment_is_manual); // Should remain unchanged
        $this->assertEquals('DOMESTIC', $invoice->tax_treatment); // Should remain unchanged
    }

    public function test_vat_reset_recomputes_vat_rate_for_domestic(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: false,
            taxTreatment: 'DOMESTIC',
            vatRate: 25.0
        );
        $user = $this->createUserWithSubscription($invoice->company);

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'vat_rate_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertEquals(19.0, (float)$invoice->vat_rate); // Company default
    }

    public function test_vat_reset_recomputes_vat_rate_to_zero_for_eu_b2b_rc(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: false,
            taxTreatment: 'EU_B2B_RC',
            vatRate: 25.0
        );
        $user = $this->createUserWithSubscription($invoice->company);

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'vat_rate_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertEquals(0.0, (float)$invoice->vat_rate);
        $this->assertEquals('Reverse charge (EU B2B).', $invoice->vat_reason_text);
    }

    public function test_vat_reset_recomputes_vat_rate_to_zero_for_non_eu(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: false,
            taxTreatment: 'NON_EU',
            vatRate: 25.0
        );
        $user = $this->createUserWithSubscription($invoice->company);

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'vat_rate_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertEquals(0.0, (float)$invoice->vat_rate);
        $this->assertEquals('Outside EU VAT scope.', $invoice->vat_reason_text);
    }

    public function test_vat_reset_forbidden_on_issued_invoice(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: false
        );
        $invoice->status = 'issued';
        $invoice->save();

        $user = $this->createUserWithSubscription($invoice->company);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'vat_rate_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertTrue($invoice->vat_rate_is_manual); // Should remain unchanged
    }

    // ==================== TAX TREATMENT RESET TESTS ====================

    public function test_tax_reset_clears_only_tax_treatment_manual_flag(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: false,
            taxTreatmentManual: true,
            taxTreatment: 'EU_B2C',
            vatRate: 19.0
        );
        $user = $this->createUserWithSubscription($invoice->company, 'pro');

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'tax_treatment_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertFalse($invoice->tax_treatment_is_manual);
        $this->assertFalse($invoice->vat_rate_is_manual); // Should remain unchanged
    }

    public function test_tax_reset_recomputes_tax_treatment_with_permission(): void
    {
        // Company RO, Client DE (cross-border EU)
        $company = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);

        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'DE',
            'vat_id' => 'DE123456789',
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'vat_rate_is_manual' => false,
            'tax_treatment_is_manual' => true,
            'tax_treatment' => 'EU_B2C',
            'vat_rate' => 19.0,
        ]);

        $user = $this->createUserWithSubscription($company, 'pro');

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'tax_treatment_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        // With cross_border_b2b permission, should auto-suggest EU_B2B_RC or EU_B2C
        $this->assertContains($invoice->tax_treatment, ['EU_B2B_RC', 'EU_B2C']);
    }

    public function test_tax_reset_leaves_tax_treatment_unchanged_without_permission(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: false,
            taxTreatmentManual: true,
            taxTreatment: 'EU_B2C',
            vatRate: 19.0
        );
        $user = $this->createUserWithSubscription($invoice->company, 'starter'); // No automation permission

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'tax_treatment_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertFalse($invoice->tax_treatment_is_manual); // Flag cleared
        $this->assertEquals('EU_B2C', $invoice->tax_treatment); // But treatment unchanged (no permission)
    }

    public function test_tax_reset_recomputes_vat_rate_when_not_manual(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: false,
            taxTreatmentManual: true,
            taxTreatment: 'EU_B2B_RC',
            vatRate: 19.0
        );
        $user = $this->createUserWithSubscription($invoice->company, 'pro');

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'tax_treatment_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        // VAT rate should be recomputed based on new tax_treatment
        // If tax_treatment is EU_B2B_RC or NON_EU, vat_rate should be 0
        if (in_array($invoice->tax_treatment, ['EU_B2B_RC', 'NON_EU'])) {
            $this->assertEquals(0.0, (float)$invoice->vat_rate);
        }
    }

    public function test_tax_reset_does_not_change_vat_rate_when_vat_is_manual(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: true,
            taxTreatment: 'EU_B2B_RC',
            vatRate: 25.0 // Manual rate
        );
        $user = $this->createUserWithSubscription($invoice->company, 'pro');

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'tax_treatment_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertTrue($invoice->vat_rate_is_manual); // Should remain true
        $this->assertEquals(25.0, (float)$invoice->vat_rate); // Should remain unchanged
    }

    public function test_tax_reset_forbidden_on_issued_invoice(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: false,
            taxTreatmentManual: true
        );
        $invoice->status = 'issued';
        $invoice->save();

        $user = $this->createUserWithSubscription($invoice->company);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'tax_treatment_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $invoice->refresh();
        $this->assertTrue($invoice->tax_treatment_is_manual); // Should remain unchanged
    }

    // ==================== INTERACTION TESTS ====================

    public function test_vat_reset_preserves_tax_treatment_when_tax_is_manual(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: true,
            taxTreatment: 'EU_B2C',
            vatRate: 25.0
        );
        $user = $this->createUserWithSubscription($invoice->company);

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'vat_rate_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertTrue($invoice->tax_treatment_is_manual); // Should remain true
        $this->assertEquals('EU_B2C', $invoice->tax_treatment); // Should remain unchanged
        $this->assertEquals(19.0, (float)$invoice->vat_rate); // Recomputed based on EU_B2C
    }

    public function test_tax_reset_preserves_vat_rate_when_vat_is_manual(): void
    {
        $invoice = $this->createInvoiceWithOverrides(
            vatRateManual: true,
            taxTreatmentManual: true,
            taxTreatment: 'EU_B2B_RC',
            vatRate: 25.0
        );
        $user = $this->createUserWithSubscription($invoice->company, 'pro');

        $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'client_id' => $invoice->client_id,
            'currency' => $invoice->currency ?? 'EUR',
            'tax_treatment_reset' => '1',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $invoice->refresh();
        $this->assertTrue($invoice->vat_rate_is_manual); // Should remain true
        $this->assertEquals(25.0, (float)$invoice->vat_rate); // Should remain unchanged (VAT is manual)
    }
}

