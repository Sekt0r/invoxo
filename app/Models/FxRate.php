<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'as_of_date',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'rate' => 'decimal:8',
            'as_of_date' => 'date',
        ];
    }
}






