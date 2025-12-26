<?php

namespace App\Services\Vat;

use App\Contracts\VatRatesProviderInterface;

final class FakeVatRatesProvider implements VatRatesProviderInterface
{
    /**
     * Hardcoded VAT rates for deterministic test behavior
     */
    private const VAT_RATES = [
        'RO' => 19.00,
        'DE' => 19.00,
        'FR' => 20.00,
        'GB' => 20.00,
        'ES' => 21.00,
        'IT' => 22.00,
        'PL' => 23.00,
    ];

    private const DEFAULT_RATE = 20.00;

    public function getStandardRate(string $countryCode): ?float
    {
        $normalizedCode = strtoupper(trim($countryCode));
        return self::VAT_RATES[$normalizedCode] ?? self::DEFAULT_RATE;
    }

    public function getAllRates(string $countryCode): array
    {
        $standardRate = $this->getStandardRate($countryCode);

        return [
            'standard_rate' => $standardRate,
            'reduced_rates' => [], // No reduced rates in fake provider for simplicity
        ];
    }
}





