<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InvoiceTotalsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_limit_blocks_issuing_when_exceeded(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        // Create client with no VAT ID to avoid VAT validation blocking
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => null,
            'vat_identity_id' => null,
        ]);

        $plan = Plan::factory()->create([
            'code' => 'starter',
            'name' => 'Starter Plan',
            'invoice_monthly_limit' => 2,
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create 2 issued invoices in current month
        $issued1 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
        ]);

        $issued2 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
        ]);

        InvoiceItem::factory()->create(['invoice_id' => $issued1->id]);
        InvoiceItem::factory()->create(['invoice_id' => $issued2->id]);

        // Create a third draft invoice
        $draftInvoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $draftInvoice->id,
            'description' => 'Test Item',
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
        ]);

        $draftInvoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($draftInvoice);
        $draftInvoice->save();

        // Create bank account for issuance requirement
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => $draftInvoice->currency ?? 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $draftInvoice));

        $response->assertRedirect(route('invoices.show', $draftInvoice));
        $response->assertSessionHasErrors('limit');

        // Verify error message includes plan code and limit
        $response->assertSessionHasErrors(['limit' => "Monthly invoice limit reached for plan starter (2 invoices/month)."]);

        $draftInvoice->refresh();
        $this->assertEquals('draft', $draftInvoice->status);
    }

    public function test_unlimited_plan_allows_issuing(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        // Create client with no VAT ID to avoid VAT validation blocking
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => null,
            'vat_identity_id' => null,
        ]);

        $plan = Plan::factory()->create([
            'code' => 'unlimited',
            'name' => 'Unlimited Plan',
            'invoice_monthly_limit' => null,
        ]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Create many issued invoices (with valid VAT identities to avoid blocking)
        for ($i = 0; $i < 10; $i++) {
            $invoice = Invoice::factory()->create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'status' => 'issued',
                'issue_date' => now()->toDateString(),
            ]);
            InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
        }

        // Create another draft invoice
        $draftInvoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $draftInvoice->id,
            'description' => 'Test Item',
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
        ]);

        $draftInvoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($draftInvoice);
        $draftInvoice->save();

        // Create bank account for issuance requirement
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => $draftInvoice->currency ?? 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $draftInvoice));

        $response->assertRedirect(route('invoices.show', $draftInvoice));
        $response->assertSessionHasNoErrors();

        $draftInvoice->refresh();
        $this->assertEquals('issued', $draftInvoice->status);
        $this->assertNotNull($draftInvoice->number);
    }

    public function test_no_subscription_allows_issuing_fail_open(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        // Create client with no VAT ID to avoid VAT validation blocking
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'vat_id' => null,
            'vat_identity_id' => null,
        ]);

        // No subscription created

        $draftInvoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $draftInvoice->id,
            'description' => 'Test Item',
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
        ]);

        $draftInvoice->load('invoiceItems');
        app(InvoiceTotalsService::class)->recalculate($draftInvoice);
        $draftInvoice->save();

        // Create bank account for issuance requirement
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => $draftInvoice->currency ?? 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $draftInvoice));

        $response->assertRedirect(route('invoices.show', $draftInvoice));
        $response->assertSessionHasNoErrors();

        $draftInvoice->refresh();
        $this->assertEquals('issued', $draftInvoice->status);
        $this->assertNotNull($draftInvoice->number);
    }
}

