<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'public_id',
        'share_token',
        'number',
        'status',
        'issue_date',
        'due_date',
        'tax_treatment',
        'vat_rate',
        'vat_reason_text',
        'vat_decided_at',
        'client_vat_status_snapshot',
        'client_vat_id_snapshot',
        'subtotal_minor',
        'vat_minor',
        'total_minor',
        'currency',
        'payment_details',
        'seller_details',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'client_id' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'vat_rate' => 'decimal:2',
            'vat_decided_at' => 'datetime',
            'subtotal_minor' => 'integer',
            'vat_minor' => 'integer',
            'total_minor' => 'integer',
            'currency' => 'string',
            'payment_details' => 'array',
            'seller_details' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    private const IMMUTABLE_AFTER_DRAFT = [
        'issue_date',
        'seller_details',
        'client_vat_status_snapshot',
        'client_vat_id_snapshot',
         'tax_treatment',
         'vat_rate',
         'vat_reason_text',
         'vat_decided_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->public_id)) {
                $invoice->public_id = (string) Str::uuid();
            }
            if (empty($invoice->share_token)) {
                $invoice->share_token = Str::random(48);
            }
        });

        static::updating(function (Invoice $invoice) {
            // Evaluate against the persisted status (original DB value)
            $wasDraft = $invoice->getOriginal('status') === 'draft';

            // Allow edits while the record is still draft (including the same update that changes status away from draft)
            if ($wasDraft) {
                return;
            }

            $dirty = array_keys($invoice->getDirty());
            $blocked = array_values(array_intersect($dirty, self::IMMUTABLE_AFTER_DRAFT));

            if (!empty($blocked)) {
                throw ValidationException::withMessages([
                    'invoice' => [
                        'Invoice snapshot is immutable once status is not draft. Blocked fields: ' . implode(', ', $blocked),
                    ],
                ]);
            }
        });
    }
}
