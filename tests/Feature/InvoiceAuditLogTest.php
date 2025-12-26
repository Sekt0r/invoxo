<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_invoice_creates_event(): void
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
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO',
            'vat_id' => null,
        ]);

        // Ensure company has required legal identity fields
        $company->update([
            'name' => 'Test Company',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);

        // Create bank account for issuance requirement
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
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

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        // Verify event was created
        $event = InvoiceEvent::where('invoice_id', $invoice->id)
            ->where('event_type', 'issued')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($company->id, $event->company_id);
        $this->assertEquals($user->id, $event->user_id);
        $this->assertEquals('draft', $event->from_status);
        $this->assertEquals('issued', $event->to_status);
        $this->assertNotNull($event->message);
        $this->assertStringContainsString('issued', $event->message);
    }

    public function test_changing_status_creates_event(): void
    {
        Queue::fake();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.markPaid', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        // Verify event was created
        $event = InvoiceEvent::where('invoice_id', $invoice->id)
            ->where('event_type', 'status_changed')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($company->id, $event->company_id);
        $this->assertEquals($user->id, $event->user_id);
        $this->assertEquals('issued', $event->from_status);
        $this->assertEquals('paid', $event->to_status);
        $this->assertNotNull($event->message);
        $this->assertStringContainsString('issued', $event->message);
        $this->assertStringContainsString('paid', $event->message);
    }

    public function test_change_status_endpoint_creates_event(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.changeStatus', $invoice), [
            'status' => 'paid',
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));

        // Verify event was created
        $event = InvoiceEvent::where('invoice_id', $invoice->id)
            ->where('event_type', 'status_changed')
            ->where('from_status', 'issued')
            ->where('to_status', 'paid')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($company->id, $event->company_id);
        $this->assertEquals($user->id, $event->user_id);
    }

    public function test_tenant_scoped_events(): void
    {
        Queue::fake();

        TaxRate::create([
            'country_code' => 'RO',
            'tax_type' => 'vat',
            'standard_rate' => 19.00,
            'source' => 'vatlayer',
        ]);

        $companyA = Company::factory()->create([
            'country_code' => 'RO',
            'default_vat_rate' => 19.00,
        ]);
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create([
            'company_id' => $companyA->id,
            'country_code' => 'RO',
            'vat_id' => null,
        ]);

        $companyB = Company::factory()->create();
        $userB = User::factory()->create(['company_id' => $companyB->id]);

        // Ensure company has required legal identity fields
        $companyA->update([
            'name' => 'Test Company A',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);

        // Create bank account for issuance requirement
        \App\Models\BankAccount::create([
            'company_id' => $companyA->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $invoiceA = Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'draft',
            'number' => null,
            'currency' => 'EUR',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoiceA->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        // User A issues invoice A
        $this->actingAs($userA)->post(route('invoices.issue', $invoiceA));

        // Verify event exists for company A
        $eventA = InvoiceEvent::where('company_id', $companyA->id)
            ->where('invoice_id', $invoiceA->id)
            ->first();
        $this->assertNotNull($eventA);

        // User B cannot see events for company A's invoice
        $response = $this->actingAs($userB)->get(route('invoices.show', $invoiceA));
        $response->assertStatus(403);

        // User B cannot create events for company A's invoice
        $response = $this->actingAs($userB)->post(route('invoices.changeStatus', $invoiceA), [
            'status' => 'paid',
        ]);
        $response->assertStatus(403);

        // Verify no event was created by user B
        $eventCount = InvoiceEvent::where('company_id', $companyA->id)
            ->where('invoice_id', $invoiceA->id)
            ->count();
        $this->assertEquals(1, $eventCount); // Only the issue event from user A
    }

    public function test_status_change_validates_transitions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Try to change draft directly to paid (should fail)
        $response = $this->actingAs($user)->post(route('invoices.changeStatus', $invoice), [
            'status' => 'paid',
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('status');

        // Verify no event was created
        $eventCount = InvoiceEvent::where('invoice_id', $invoice->id)->count();
        $this->assertEquals(0, $eventCount);
    }

    public function test_activity_timeline_shows_events(): void
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
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $client = Client::factory()->create([
            'company_id' => $company->id,
            'country_code' => 'RO',
            'vat_id' => null,
        ]);

        // Ensure company has required legal identity fields
        $company->update([
            'name' => 'Test Company',
            'tax_identifier' => 'TAX123',
            'address_line1' => '123 Street',
            'city' => 'City',
            'postal_code' => '12345',
        ]);

        // Create bank account for issuance requirement
        \App\Models\BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
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

        // Issue invoice
        $this->actingAs($user)->post(route('invoices.issue', $invoice));

        // Mark as paid
        $this->actingAs($user)->post(route('invoices.markPaid', $invoice));

        // View invoice show page
        $response = $this->actingAs($user)->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Activity');
        $response->assertSee('Issued');
        $response->assertSee('Status changed');
        $response->assertSee('issued');
        $response->assertSee('paid');
    }
}
