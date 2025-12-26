<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VatIdentity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_code',
        'vat_id',
        'status',
        'status_updated_at',
        'last_checked_at',
        'last_enqueued_at',
        'name',
        'address',
        'source',
        'provider_metadata',
        'last_error',
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
            'status_updated_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_enqueued_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
