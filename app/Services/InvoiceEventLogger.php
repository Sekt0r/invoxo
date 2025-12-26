<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\User;

final class InvoiceEventLogger
{
    /**
     * Log when an invoice is issued
     */
    public function logIssued(Invoice $invoice, User $user): void
    {
        InvoiceEvent::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'event_type' => 'issued',
            'from_status' => 'draft',
            'to_status' => 'issued',
            'message' => "Invoice {$invoice->number} was issued.",
        ]);
    }

    /**
     * Log a status change
     */
    public function logStatusChanged(Invoice $invoice, User $user, string $from, string $to, ?string $message = null): void
    {
        InvoiceEvent::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'event_type' => 'status_changed',
            'from_status' => $from,
            'to_status' => $to,
            'message' => $message ?? "Invoice status changed from {$from} to {$to}.",
        ]);
    }

    /**
     * Log draft update (optional, for future use)
     */
    public function logDraftUpdated(Invoice $invoice, User $user, ?string $message = null): void
    {
        InvoiceEvent::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'user_id' => $user->id,
            'event_type' => 'draft_updated',
            'from_status' => 'draft',
            'to_status' => 'draft',
            'message' => $message ?? 'Draft invoice was updated.',
        ]);
    }
}





