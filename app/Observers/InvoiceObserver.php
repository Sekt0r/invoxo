<?php

namespace App\Observers;

use App\Models\Invoice;

class InvoiceObserver
{
    /**
     * Handle the Invoice "updating" event.
     * Enforce immutability of issued invoices.
     */
    public function updating(Invoice $invoice): void
    {
        // Only enforce immutability if the invoice WAS issued before this update
        // (check original status, not current status)
        if ($invoice->getOriginal('status') !== 'issued') {
            return;
        }

        // List of immutable fields for issued invoices
        $immutableFields = [
            'issue_date',
            'number',
            'currency',
            'tax_treatment',
            'vat_rate',
            'vat_reason_text',
            'vat_decided_at',
            'client_vat_status_snapshot',
            'client_vat_id_snapshot',
            'subtotal_minor',
            'vat_minor',
            'total_minor',
            'seller_details',
            'payment_details',
            'company_id',
            'client_id',
        ];

        // Check if any immutable field was changed
        foreach ($immutableFields as $field) {
            if ($invoice->isDirty($field)) {
                $originalValue = $invoice->getOriginal($field);

                // Only revert if the original value was not null
                // (Allow setting values on fields that were never set, for data migration/backfill)
                if ($originalValue !== null) {
                    $invoice->$field = $originalValue;
                }
            }
        }

        // Allow status transitions (e.g., issued -> paid)
        // Allow due_date updates (administrative)
    }
}

