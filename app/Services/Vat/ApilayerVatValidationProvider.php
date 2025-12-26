<?php

namespace App\Services\Vat;

use App\Contracts\VatValidationProviderInterface;
use App\Data\VatValidationResult;
use App\Exceptions\VatlayerException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

final class ApilayerVatValidationProvider implements VatValidationProviderInterface
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

    public function validate(string $countryCode, string $vatId): VatValidationResult
    {
        $url = $this->baseUrl . '/validate';

        try {
            $response = Http::timeout(10)
                ->retry(1, 250)
                ->get($url, [
                    'access_key' => $this->apiKey,
                    'vat_number' => strtoupper($countryCode) . trim($vatId), // VATlayer expects CC+VAT combined
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

            // Map VATlayer response to our result
            $valid = $data['valid'] ?? false;
            $status = $valid ? 'valid' : 'invalid';

            // Normalize name/address: trim and convert empty strings or '---' to null
            $name = isset($data['company_name']) ? trim($data['company_name']) : null;
            $name = ($name === '' || $name === '---') ? null : $name;

            $address = isset($data['company_address']) ? trim($data['company_address']) : null;
            $address = ($address === '' || $address === '---') ? null : $address;

            return new VatValidationResult(
                status: $status,
                companyName: $name,
                companyAddress: $address,
                checkedAt: Carbon::now(),
            );
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
}






