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

class InvoiceItemImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_can_create_items_on_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Should not throw
        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Test Item',
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $this->assertNotNull($item->id);
        $this->assertEquals($invoice->id, $item->invoice_id);
    }

    public function test_can_update_items_on_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Original Description',
        ]);

        // Should not throw
        $item->update(['description' => 'Updated Description']);
        $item->refresh();

        $this->assertEquals('Updated Description', $item->description);
    }

    public function test_can_delete_items_on_draft_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $itemId = $item->id;

        // Should not throw
        $item->delete();

        $this->assertNull(InvoiceItem::find($itemId));
    }

    public function test_cannot_create_items_on_issued_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice, add item, then issue
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        // Update to issued
        $invoice->update(['status' => 'issued', 'number' => 'INV-2025-001']);

        // Attempt to create new item on issued invoice - should throw
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot add items to an issued invoice.');

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'New Item',
            'quantity' => 1.0,
            'unit_price_minor' => 5000,
            'line_total_minor' => 5000,
        ]);
    }

    public function test_cannot_update_items_on_issued_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice with item as draft
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Original Description',
        ]);

        // Update to issued
        $invoice->update(['status' => 'issued', 'number' => 'INV-2025-001']);

        // Attempt to update item - should throw
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot modify items on an issued invoice.');

        $item->update(['description' => 'Modified Description']);
    }

    public function test_cannot_delete_items_on_issued_invoice(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice with item as draft
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        // Update to issued
        $invoice->update(['status' => 'issued', 'number' => 'INV-2025-001']);

        // Attempt to delete item - should throw
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot delete items from an issued invoice.');

        $item->delete();
    }

    public function test_items_protected_on_paid_invoices(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice with item as draft
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        // Issue then mark as paid
        $invoice->update(['status' => 'issued', 'number' => 'INV-2025-001']);
        $invoice->update(['status' => 'paid']);

        // Attempt to modify item on paid invoice - should throw
        $this->expectException(ValidationException::class);

        $item->update(['description' => 'Modified']);
    }

    public function test_items_protected_on_voided_invoices(): void
    {
        $company = Company::factory()->create();
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice with item as draft
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        // Issue then void
        $invoice->update(['status' => 'issued', 'number' => 'INV-2025-001']);
        $invoice->update(['status' => 'voided']);

        // Attempt to modify item on voided invoice - should throw
        $this->expectException(ValidationException::class);

        $item->update(['description' => 'Modified']);
    }
}

