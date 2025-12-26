<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VatRateOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_code',
        'standard_rate',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'standard_rate' => 'decimal:2',
        ];
    }
}




