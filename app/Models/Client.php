<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'name',
        'country_code',
        'vat_id',
        'registration_number',
        'tax_identifier',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'vat_identity_id',
        'vat_validation_status',
        'vat_validated_at',
        'vat_validation_name',
        'vat_validation_address',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'vat_identity_id' => 'integer',
            'vat_validated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vatIdentity(): BelongsTo
    {
        return $this->belongsTo(VatIdentity::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
