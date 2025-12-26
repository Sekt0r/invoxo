<?php

namespace App\Services;

use App\Contracts\VatRatesProviderInterface;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Log;

class VatRatesSyncService
{
    public function __construct(
        private readonly VatRatesProviderInterface $vatRatesProvider
    ) {
    }

    /**
     * Sync VAT rates for all EU countries
     *
     * @return array Summary of sync operation
     */
    public function syncAll(): array
    {
        $synced = 0;
        $errors = [];

        // EU countries list
        $euCountries = [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT',
            'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        ];

        foreach ($euCountries as $countryCode) {
            try {
                $rates = $this->vatRatesProvider->getAllRates($countryCode);

                // Extract standard rate
                $standardRate = $rates['standard_rate'] ?? null;

                if ($standardRate === null) {
                    Log::warning("VatRatesSyncService: No standard_rate for {$countryCode}");
                    continue;
                }

                // Extract reduced rates if available
                $reducedRates = null;
                if (isset($rates['reduced_rates']) && is_array($rates['reduced_rates']) && !empty($rates['reduced_rates'])) {
                    $reducedRates = $rates['reduced_rates'];
                }

                TaxRate::updateOrCreate(
                    [
                        'country_code' => strtoupper($countryCode),
                    ],
                    [
                        'tax_type' => 'vat',
                        'standard_rate' => (float)$standardRate,
                        'reduced_rates' => $reducedRates ?: null,
                        'source' => 'provider', // No environment check - determined by provider binding
                        'fetched_at' => now(),
                    ]
                );

                $synced++;
            } catch (\Exception $e) {
                $errors[] = "{$countryCode}: {$e->getMessage()}";
                Log::error("VatRatesSyncService: Failed to sync rate for {$countryCode}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
            'success' => empty($errors),
        ];
    }
}
