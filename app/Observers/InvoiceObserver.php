<?php

namespace App\Observers;

use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class InvoiceObserver
{
    /**
     * Handle the Invoice "updating" event.
     * Enforce immutability of issued invoices.
     *
     * This is the SINGLE source of truth for immutability enforcement.
     * The authoritative list of immutable fields is defined in Invoice::IMMUTABLE_AFTER_ISSUE.
     */
    public function updating(Invoice $invoice): void
    {
        // Only enforce immutability if the invoice WAS issued before this update
        // (check original status, not current status)
        $originalStatus = $invoice->getOriginal('status');

        // Allow edits while the record was still draft (including the update that changes status to issued)
        if ($originalStatus === 'draft') {
            return;
        }

        // For non-draft invoices (issued, paid, voided), enforce immutability
        $dirty = array_keys($invoice->getDirty());
        $blocked = array_values(array_intersect($dirty, Invoice::IMMUTABLE_AFTER_ISSUE));

        if (!empty($blocked)) {
            throw ValidationException::withMessages([
                'invoice' => [
                    'Invoice is immutable after issue. Cannot modify: ' . implode(', ', $blocked),
                ],
            ]);
        }

        // Allowed mutable fields after issue:
        // - status (for transitions: issued -> paid -> voided, etc.)
        // - due_date (administrative correction)
    }
}
