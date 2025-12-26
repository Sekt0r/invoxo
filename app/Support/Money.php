<?php

namespace App\Support;

class Money
{
    private const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'BGN' => 'лв',
        'CZK' => 'Kč',
        'DKK' => 'kr',
        'HUF' => 'Ft',
        'PLN' => 'zł',
        'RON' => 'lei',
        'SEK' => 'kr',
    ];

    /**
     * Format minor units with currency symbol
     */
    public static function format(int $minorUnits, string $currency): string
    {
        $symbol = self::SYMBOLS[$currency] ?? $currency;
        return $symbol . number_format($minorUnits / 100, 2);
    }

    /**
     * Format minor units with currency code (e.g., "123.45 EUR")
     */
    public static function formatWithCode(int $minorUnits, string $currency): string
    {
        return number_format($minorUnits / 100, 2) . ' ' . $currency;
    }
}






