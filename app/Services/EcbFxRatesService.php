<?php

namespace App\Services;

use App\Models\FxRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EcbFxRatesService
{
    private const ECB_DAILY_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    private const TIMEOUT = 10;

    /**
     * Fetch daily FX rates from ECB
     *
     * @return array{as_of_date: string, rates: array<string, float>}
     * @throws \Exception
     */
    public function fetchDaily(): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->retry(1, 250)
                ->get(self::ECB_DAILY_URL);

            if (!$response->successful()) {
                throw new \RuntimeException("ECB API returned HTTP {$response->status()}");
            }

            $xml = $response->body();

            // Parse XML
            $xmlObject = @simplexml_load_string($xml);

            if ($xmlObject === false) {
                throw new \RuntimeException('Failed to parse ECB XML response');
            }

            // Extract date from Cube[@time]
            $asOfDate = null;
            $rates = [];

            // Navigate XML structure: Envelope -> Cube -> Cube[@time] -> Cube[@currency]
            // Structure: <Cube><Cube time="..."><Cube currency="..." rate="..."/></Cube></Cube>
            if (isset($xmlObject->Cube) && isset($xmlObject->Cube->Cube)) {
                foreach ($xmlObject->Cube->Cube as $timeCube) {
                    if (isset($timeCube['time'])) {
                        $asOfDate = (string)$timeCube['time'];
                    }

                    if (isset($timeCube->Cube)) {
                        foreach ($timeCube->Cube as $rateCube) {
                            if (isset($rateCube['currency']) && isset($rateCube['rate'])) {
                                $currency = (string)$rateCube['currency'];
                                $rate = (float)$rateCube['rate'];
                                $rates[$currency] = $rate;
                            }
                        }
                    }
                }
            }

            if ($asOfDate === null) {
                throw new \RuntimeException('Could not find date in ECB XML response');
            }

            return [
                'as_of_date' => $asOfDate,
                'rates' => $rates,
            ];
        } catch (\Exception $e) {
            Log::error('EcbFxRatesService: Failed to fetch daily rates', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync daily FX rates to database
     *
     * @return array{inserted: int, updated: int, as_of_date: string}
     * @throws \Exception
     */
    public function syncDaily(): array
    {
        $data = $this->fetchDaily();

        // Normalize date to canonical YYYY-MM-DD format (day precision)
        $asOfDate = Carbon::parse($data['as_of_date'])->toDateString();
        $rates = $data['rates'];

        // Get allowed currencies (excluding EUR as it's the base)
        $allowedCurrencies = array_filter(
            config('currencies.allowed', []),
            fn($currency) => $currency !== 'EUR'
        );

        // Build rows for upsert (deduplicate in-memory by unique key)
        $rowsByKey = [];
        $now = now();

        // Add rates for allowed currencies
        foreach ($allowedCurrencies as $currency) {
            if (!isset($rates[$currency])) {
                Log::warning("EcbFxRatesService: Currency {$currency} not found in ECB response");
                continue;
            }

            $rate = $rates[$currency];
            $key = "EUR|{$currency}|{$asOfDate}";

            // Deduplicate: keep last seen value for each unique key
            $rowsByKey[$key] = [
                'base_currency' => 'EUR',
                'quote_currency' => $currency,
                'rate' => $rate,
                'as_of_date' => $asOfDate,
                'source' => 'ecb',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Add EUR->EUR rate at 1.0 for this date
        $eurKey = "EUR|EUR|{$asOfDate}";
        $rowsByKey[$eurKey] = [
            'base_currency' => 'EUR',
            'quote_currency' => 'EUR',
            'rate' => 1.0,
            'as_of_date' => $asOfDate,
            'source' => 'ecb',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // Convert to array for upsert
        $rows = array_values($rowsByKey);

        if (empty($rows)) {
            return [
                'inserted' => 0,
                'updated' => 0,
                'as_of_date' => $asOfDate,
            ];
        }

        // Get counts before upsert
        $beforeCount = FxRate::where('as_of_date', $asOfDate)
            ->where('source', 'ecb')
            ->count();

        // Upsert: unique on (base_currency, quote_currency, as_of_date)
        // Update rate, source, and updated_at on conflict
        FxRate::upsert(
            $rows,
            ['base_currency', 'quote_currency', 'as_of_date'],
            ['rate', 'source', 'updated_at']
        );

        // Get counts after upsert
        $afterCount = FxRate::where('as_of_date', $asOfDate)
            ->where('source', 'ecb')
            ->count();

        // Calculate inserted vs updated
        // If after count increased, we inserted new rows
        $inserted = max(0, $afterCount - $beforeCount);
        $updated = count($rows) - $inserted;

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'as_of_date' => $asOfDate,
        ];
    }
}
