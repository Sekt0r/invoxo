<?php

namespace App\Services;

use App\Data\ViesResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

class ViesVatValidationService
{
    private const WSDL_URL = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService.wsdl';
    private const TIMEOUT = 10;

    public function validate(string $countryCode, string $vatId): ViesResult
    {
        $countryCode = strtoupper(trim($countryCode));
        $vatId = preg_replace('/[\s.\-]/', '', trim($vatId));

        if (strlen($countryCode) !== 2 || empty($vatId)) {
            return new ViesResult(
                status: 'unknown',
                validatedAt: Carbon::now(),
            );
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => self::TIMEOUT,
                ],
            ]);

            $client = new SoapClient(
                self::WSDL_URL,
                [
                    'stream_context' => $context,
                    'cache_wsdl' => WSDL_CACHE_NONE,
                ]
            );

            $response = $client->checkVat([
                'countryCode' => $countryCode,
                'vatNumber' => $vatId,
            ]);

            if (!isset($response->valid)) {
                Log::warning('VIES validation: Invalid response structure', [
                    'country_code' => $countryCode,
                    'vat_id' => $vatId,
                ]);

                return new ViesResult(
                    status: 'unknown',
                    validatedAt: Carbon::now(),
                );
            }

            if ($response->valid === true) {
                return new ViesResult(
                    status: 'valid',
                    name: isset($response->name) ? trim($response->name) : null,
                    address: isset($response->address) ? trim($response->address) : null,
                    validatedAt: Carbon::now(),
                );
            }

            return new ViesResult(
                status: 'invalid',
                validatedAt: Carbon::now(),
            );
        } catch (SoapFault $e) {
            Log::error('VIES validation SOAP fault', [
                'country_code' => $countryCode,
                'vat_id' => $vatId,
                'message' => $e->getMessage(),
            ]);

            return new ViesResult(
                status: 'unknown',
                validatedAt: Carbon::now(),
            );
        } catch (\Exception $e) {
            Log::error('VIES validation exception', [
                'country_code' => $countryCode,
                'vat_id' => $vatId,
                'message' => $e->getMessage(),
            ]);

            return new ViesResult(
                status: 'unknown',
                validatedAt: Carbon::now(),
            );
        }
    }
}
