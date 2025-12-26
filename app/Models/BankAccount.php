<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        'company_id',
        'currency',
        'iban',
        'nickname',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'company_id' => 'integer',
            'is_default' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Basic IBAN format validation (MVP-level regex)
     * Validates format: 2 letters (country code) + 2 digits + up to 30 alphanumeric characters
     */
    public static function isValidIbanFormat(string $iban): bool
    {
        $normalized = strtoupper(str_replace(' ', '', trim($iban)));
        return (bool) preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', $normalized);
    }

    /**
     * Get display name for UI (nickname or fallback to "IBAN ending in XXXX")
     */
    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->nickname)) {
            return $this->nickname;
        }

        $iban = str_replace(' ', '', $this->iban);
        $lastFour = substr($iban, -4);
        return "IBAN ending in {$lastFour}";
    }

    /**
     * Normalize IBAN (remove spaces, uppercase)
     */
    public function setIbanAttribute(string $value): void
    {
        $this->attributes['iban'] = strtoupper(str_replace(' ', '', trim($value)));
    }

    /**
     * Validate and normalize currency (must be in config)
     * This is a defensive check - validation should have already enforced this.
     */
    public function setCurrencyAttribute(string $value): void
    {
        $normalized = strtoupper(trim($value));
        $allowedCurrencies = config('currencies.allowed', []);

        // Only validate if config exists (defensive check)
        if (!empty($allowedCurrencies) && !in_array($normalized, $allowedCurrencies, true)) {
            throw new \InvalidArgumentException("Currency '{$normalized}' is not in the allowed currencies list.");
        }

        $this->attributes['currency'] = $normalized;
    }

    /**
     * Set this bank account as the default for its company.
     * Unsets all other default accounts for the same company.
     * Must be run within a database transaction.
     */
    public function setAsDefault(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            // Unset all other defaults for this company (load and save to trigger observer)
            static::where('company_id', $this->company_id)
                ->where('id', '!=', $this->id)
                ->where('is_default', true)
                ->get()
                ->each(function ($account) {
                    $account->is_default = false;
                    $account->save();
                });

            // Set this account as default
            $this->is_default = true;
            $this->save();
        });
    }
}
