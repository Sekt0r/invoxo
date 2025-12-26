<?php

namespace App\Services\Vat;

use App\Contracts\VatProvider;
use App\Exceptions\VatlayerException;
use Illuminate\Support\Facades\Http;

class ApilayerVatProvider implements VatProvider
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

    /**
     * Validate a VAT number
     *
     * @param string $countryCode 2-letter country code
     * @param string $vatId VAT ID (without country prefix)
     * @return array Response array with keys: valid, company_name, company_address, etc.
     */
    public function validateVat(string $countryCode, string $vatId): array
    {
        $url = $this->baseUrl . '/validate';

        try {
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->get($url, [
                    'access_key' => $this->apiKey,
                    'vat_number' => $countryCode . $vatId, // VATlayer expects CC+VAT combined
                ]);

            if (!$response->successful()) {
                throw new VatlayerException(
                    "VATlayer API returned HTTP {$response->status()}: {$response->body()}",
                    $response->json()
                );
            }

            $data = $response->json();

            // Check for API-level errors in response
            if (isset($data['success']) && $data['success'] === false) {
                throw new VatlayerException(
                    $data['error']['info'] ?? 'VATlayer API error',
                    $data
                );
            }

            return $data;
        } catch (VatlayerException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new VatlayerException(
                "Failed to validate VAT: {$e->getMessage()}",
                null,
                $e
            );
        }
    }

    /**
     * Get VAT rate list for all countries or a specific country
     *
     * @param string|null $countryCode Optional 2-letter country code
     * @return array Response array with rates data
     */
    public function rateList(?string $countryCode = null): array
    {
        $url = $this->baseUrl . '/rate_list';

        $params = [
            'access_key' => $this->apiKey,
        ];

        if ($countryCode) {
            $params['country_code'] = strtoupper($countryCode);
        }

        try {
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->get($url, $params);

            if (!$response->successful()) {
                throw new VatlayerException(
                    "VATlayer API returned HTTP {$response->status()}: {$response->body()}",
                    $response->json()
                );
            }

            $data = $response->json();

            // Check for API-level errors in response
            if (isset($data['success']) && $data['success'] === false) {
                throw new VatlayerException(
                    $data['error']['info'] ?? 'VATlayer API error',
                    $data
                );
            }

            return $data;
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






