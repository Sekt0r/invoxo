<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\BankAccountEvent;
use Illuminate\Support\Facades\Auth;

class BankAccountObserver
{
    /**
     * Handle the BankAccount "created" event.
     */
    public function created(BankAccount $bankAccount): void
    {
        $this->logEvent($bankAccount, 'created', null, $bankAccount->toArray());
    }

    /**
     * Handle the BankAccount "updated" event.
     */
    public function updated(BankAccount $bankAccount): void
    {
        // NOTE: In the "updated" event, getDirty() is typically empty because the model
        // has already been persisted. Use getChanges() to capture what actually changed.
        $changes = $bankAccount->getChanges();
        if (empty($changes)) {
            return;
        }

        $oldValues = [];
        foreach (array_keys($changes) as $key) {
            $oldValues[$key] = $bankAccount->getOriginal($key);
        }

        $this->logEvent($bankAccount, 'updated', $oldValues, $bankAccount->toArray());
    }

    /**
     * Handle the BankAccount "deleting" event (soft delete).
     * Capture snapshot of account data before soft deletion.
     */
    public function deleting(BankAccount $bankAccount): void
    {
        // Only handle soft deletes (not force deletes)
        if ($bankAccount->isForceDeleting()) {
            return;
        }

        // Capture data before soft deletion - attributes are still available
        $oldValues = [
            'id' => $bankAccount->id,
            'company_id' => $bankAccount->company_id,
            'currency' => $bankAccount->currency,
            'iban' => $bankAccount->iban,
            'nickname' => $bankAccount->nickname,
            'is_default' => $bankAccount->is_default,
            'created_at' => $bankAccount->created_at?->toIso8601String(),
            'updated_at' => $bankAccount->updated_at?->toIso8601String(),
        ];

        $this->logEvent($bankAccount, 'deleted', $oldValues, null);
    }

    /**
     * Handle the BankAccount "restoring" event (before restore).
     * Capture deleted_at before it's cleared.
     */
    public function restoring(BankAccount $bankAccount): void
    {
        // Capture current state (deleted_at is still set)
        $oldValues = [
            'id' => $bankAccount->id,
            'company_id' => $bankAccount->company_id,
            'currency' => $bankAccount->currency,
            'iban' => $bankAccount->iban,
            'nickname' => $bankAccount->nickname,
            'is_default' => $bankAccount->is_default,
            'deleted_at' => $bankAccount->deleted_at?->toIso8601String(),
            'created_at' => $bankAccount->created_at?->toIso8601String(),
            'updated_at' => $bankAccount->updated_at?->toIso8601String(),
        ];

        // New values after restore (deleted_at will be null)
        $newValues = [
            'deleted_at' => null,
            'updated_at' => now()->toIso8601String(),
        ];

        $this->logEvent($bankAccount, 'restored', $oldValues, $newValues);
    }

    /**
     * Log audit event (non-blocking, must not mutate domain state)
     */
    private function logEvent(BankAccount $bankAccount, string $action, ?array $oldValues, ?array $newValues): void
    {
        try {
            $user = Auth::user();
            $ipAddress = $user?->currentIp();

            BankAccountEvent::create([
                'company_id' => $bankAccount->company_id,
                'bank_account_id' => $bankAccount->id,
                'user_id' => Auth::id(),
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Exception $e) {
            // In tests, re-throw to help debug
            if (app()->environment('testing')) {
                throw $e;
            }
            // Log error but don't block the operation in production
            \Illuminate\Support\Facades\Log::error('Failed to create bank account event', [
                'bank_account_id' => $bankAccount->id ?? 'unknown',
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
