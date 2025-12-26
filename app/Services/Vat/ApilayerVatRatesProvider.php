<?php

namespace App\Services\Vat;

use App\Contracts\VatRatesProviderInterface;
use App\Exceptions\VatlayerException;
use Illuminate\Support\Facades\Http;

final class ApilayerVatRatesProvider implements VatRatesProviderInterface
{
    private string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.vatlayer.base_url', 'https://apilayer.net/api');
        $this->apiKey = config('services.vatlayer.key');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('VATLAYER_API_KEY is not configured');
        }
    }

    public function getStandardRate(string $countryCode): ?float
    {
        $rates = $this->getAllRates($countryCode);
        return $rates['standard_rate'] ?? null;
    }

    public function getAllRates(string $countryCode): array
    {
        $url = $this->baseUrl . '/rate_list';

        try {
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->get($url, [
                    'access_key' => $this->apiKey,
                    'country_code' => strtoupper($countryCode),
                ]);

            if (!$response->successful()) {
                throw new VatlayerException(
                    "VATlayer API returned HTTP {$response->status()}: {$response->body()}",
                    $response->json()
                );
            }

            $data = $response->json();

            if (isset($data['success']) && $data['success'] === false) {
                throw new VatlayerException(
                    $data['error']['info'] ?? 'VATlayer API error',
                    $data
                );
            }

            // Extract rates for the requested country
            $rates = $data['rates'] ?? [];
            $countryRates = $rates[strtoupper($countryCode)] ?? null;

            if (!$countryRates) {
                return [
                    'standard_rate' => null,
                    'reduced_rates' => [],
                ];
            }

            return [
                'standard_rate' => isset($countryRates['standard_rate']) ? (float)$countryRates['standard_rate'] : null,
                'reduced_rates' => $countryRates['reduced_rates'] ?? [],
            ];
        } catch (VatlayerException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new VatlayerException(
                "Failed to fetch VAT rates: {$e->getMessage()}",
                null,
                $e
            );
        }
    }
}
