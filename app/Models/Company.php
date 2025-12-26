<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
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
        'default_vat_rate',
        'vat_override_enabled',
        'vat_override_rate',
        'invoice_prefix',
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
            'vat_identity_id' => 'integer',
            'default_vat_rate' => 'decimal:2',
            'vat_override_enabled' => 'boolean',
            'vat_override_rate' => 'decimal:2',
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function vatIdentity(): BelongsTo
    {
        return $this->belongsTo(VatIdentity::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }
}
