<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Validation\ValidationException;

class InvoiceItemObserver
{
    /**
     * Handle the InvoiceItem "creating" event.
     * Block creation if parent invoice is not a draft.
     */
    public function creating(InvoiceItem $item): void
    {
        $this->assertInvoiceIsDraft($item, 'Cannot add items to an issued invoice.');
    }

    /**
     * Handle the InvoiceItem "updating" event.
     * Block updates if parent invoice is not a draft.
     */
    public function updating(InvoiceItem $item): void
    {
        $this->assertInvoiceIsDraft($item, 'Cannot modify items on an issued invoice.');
    }

    /**
     * Handle the InvoiceItem "deleting" event.
     * Block deletion if parent invoice is not a draft.
     */
    public function deleting(InvoiceItem $item): void
    {
        $this->assertInvoiceIsDraft($item, 'Cannot delete items from an issued invoice.');
    }

    /**
     * Assert that the parent invoice is in draft status.
     *
     * This checks the PERSISTED status of the invoice (from DB), not the in-memory status.
     * This ensures that:
     * - Factory creation of issued invoices with items works (invoice not yet persisted)
     * - Items created during the issue transaction work (invoice was draft when loaded)
     * - Post-issue item mutations are blocked (invoice persisted as 'issued')
     *
     * @throws ValidationException
     */
    private function assertInvoiceIsDraft(InvoiceItem $item, string $message): void
    {
        // For creating: item doesn't have invoice relation loaded yet, use invoice_id
        // For updating/deleting: use the loaded invoice
        $invoiceId = $item->invoice_id;

        if (!$invoiceId) {
            // If no invoice_id, we can't check - allow (shouldn't happen in practice)
            return;
        }

        // Query the PERSISTED status from DB to ensure we check actual committed state
        $persistedStatus = Invoice::where('id', $invoiceId)->value('status');

        // If invoice doesn't exist in DB yet (factory creating invoice + items together), allow
        if ($persistedStatus === null) {
            return;
        }

        // Block if invoice is already issued in the database
        if ($persistedStatus !== 'draft') {
            throw ValidationException::withMessages([
                'invoice_item' => [$message],
            ]);
        }
    }
}
