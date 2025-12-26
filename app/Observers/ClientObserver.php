<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

class ClientObserver
{
    /**
     * Handle the Client "forceDeleting" event.
     * Block permanent deletion if the client has any invoices.
     *
     * Note: Soft deletes are allowed; only permanent deletes are blocked.
     * Invoices keep their FK reference to the soft-deleted client.
     */
    public function forceDeleting(Client $client): void
    {
        $invoiceCount = Invoice::where('client_id', $client->id)->count();

        if ($invoiceCount > 0) {
            throw ValidationException::withMessages([
                'client' => [
                    "Cannot permanently delete client with {$invoiceCount} existing invoice(s). Invoices must be deleted first.",
                ],
            ]);
        }
    }

    /**
     * Handle the Client "deleting" event.
     * For soft deletes, we allow the operation but could log a warning.
     *
     * If the user truly wants to prevent soft deletes of clients with invoices,
     * they can uncomment the check below.
     */
    public function deleting(Client $client): void
    {
        // Soft delete is allowed - invoices keep their FK reference
        // Uncomment below to also block soft deletes:
        /*
        if (!$client->isForceDeleting()) {
            $invoiceCount = Invoice::where('client_id', $client->id)->count();
            if ($invoiceCount > 0) {
                throw ValidationException::withMessages([
                    'client' => [
                        "Cannot delete client with {$invoiceCount} existing invoice(s). Archive or reassign invoices first.",
                    ],
                ]);
            }
        }
        */
    }
}
