<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvoiceImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * Get a properly issued invoice for testing.
     */
    private function createIssuedInvoice(): Invoice
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

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

        // Transition to issued
        $invoice->update([
            'status' => 'issued',
            'number' => 'INV-2025-000001',
            'issue_date' => now()->toDateString(),
            'subtotal_minor' => 10000,
            'vat_minor' => 1900,
            'total_minor' => 11900,
            'seller_details' => ['company_name' => 'Test'],
            'buyer_details' => ['client_name' => 'Test Client'],
            'payment_details' => ['accounts' => []],
        ]);

        return $invoice;
    }

    public function test_cannot_modify_issue_date_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('issue_date');

        $invoice->issue_date = now()->addDay();
        $invoice->save();
    }

    public function test_cannot_modify_number_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('number');

        $invoice->number = 'HACKED-001';
        $invoice->save();
    }

    public function test_cannot_modify_currency_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('currency');

        $invoice->currency = 'USD';
        $invoice->save();
    }

    public function test_cannot_modify_totals_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('total_minor');

        $invoice->total_minor = 999999;
        $invoice->save();
    }

    public function test_cannot_modify_seller_details_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('seller_details');

        $invoice->seller_details = ['company_name' => 'Hacked Company'];
        $invoice->save();
    }

    public function test_cannot_modify_buyer_details_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('buyer_details');

        $invoice->buyer_details = ['client_name' => 'Hacked Client'];
        $invoice->save();
    }

    public function test_cannot_modify_payment_details_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('payment_details');

        $invoice->payment_details = ['hacked' => true];
        $invoice->save();
    }

    public function test_cannot_change_client_id_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();
        $otherClient = Client::factory()->create(['company_id' => $invoice->company_id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('client_id');

        $invoice->client_id = $otherClient->id;
        $invoice->save();
    }

    public function test_can_change_status_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        // Status changes should be allowed
        $invoice->status = 'paid';
        $invoice->save();

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_can_change_due_date_after_issue(): void
    {
        $invoice = $this->createIssuedInvoice();

        // Due date changes should be allowed (administrative)
        $newDueDate = now()->addDays(30);
        $invoice->due_date = $newDueDate;
        $invoice->save();

        $invoice->refresh();
        $this->assertEquals($newDueDate->toDateString(), $invoice->due_date->toDateString());
    }

    public function test_immutability_applies_to_paid_invoices(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->update(['status' => 'paid']);

        $this->expectException(ValidationException::class);

        $invoice->number = 'HACKED-001';
        $invoice->save();
    }

    public function test_immutability_applies_to_voided_invoices(): void
    {
        $invoice = $this->createIssuedInvoice();
        $invoice->update(['status' => 'voided']);

        $this->expectException(ValidationException::class);

        $invoice->number = 'HACKED-001';
        $invoice->save();
    }

    public function test_draft_invoices_are_fully_mutable(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // All fields should be mutable on draft
        $invoice->number = 'DRAFT-001';
        $invoice->currency = 'USD';
        $invoice->subtotal_minor = 50000;
        $invoice->save();

        $invoice->refresh();
        $this->assertEquals('DRAFT-001', $invoice->number);
        $this->assertEquals('USD', $invoice->currency);
        $this->assertEquals(50000, $invoice->subtotal_minor);
    }
}

