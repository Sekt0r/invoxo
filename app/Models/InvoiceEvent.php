<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'user_id',
        'event_type',
        'from_status',
        'to_status',
        'message',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
