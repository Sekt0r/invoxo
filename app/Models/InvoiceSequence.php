<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'year',
        'prefix',
        'last_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
