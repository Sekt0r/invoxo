<?php

namespace App\Services;

use App\Models\FxRate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;

class FxConversionService
{
    /**
     * Convert amount from one currency to another
     *
     * @param int $amountMinor Amount in minor units (e.g. cents)
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @param CarbonInterface|null $asOf Date to use for rate (null = latest available)
     * @return int Amount in target currency (minor units)
     * @throws DomainException If rate not available
     */
    public function convertMinor(
        int $amountMinor,
        string $fromCurrency,
        string $toCurrency,
        ?CarbonInterface $asOf = null
    ): int {
        // Same currency: no conversion needed
        if (strtoupper($fromCurrency) === strtoupper($toCurrency)) {
            return $amountMinor;
        }

        $rate = $this->getRate($fromCurrency, $toCurrency, $asOf);

        // Convert using high precision: amountMinor is integer, rate is decimal
        // We need: (amountMinor / 100) * rate * 100 = amountMinor * rate
        // Use bcmath if available for precision, otherwise use float with rounding
        if (function_exists('bcmul')) {
            $result = bcmul((string)$amountMinor, (string)$rate, 2);
            return (int)round((float)$result);
        }

        // Fallback: use float with careful rounding
        $result = round($amountMinor * $rate, 2);
        return (int)$result;
    }

    /**
     * Get exchange rate between two currencies
     *
     * @param string $fromCurrency Source currency
     * @param string $toCurrency Target currency
     * @param CarbonInterface|null $asOf Date for rate (null = latest)
     * @return float Exchange rate
     * @throws DomainException If rate not available
     */
    public function getRate(string $fromCurrency, string $toCurrency, ?CarbonInterface $asOf = null): float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        // Same currency
        if ($from === $to) {
            return 1.0;
        }

        // ECB provides EUR base rates, so we convert via EUR
        // If converting from EUR -> X: use rate(EUR->X)
        // If converting from X -> EUR: use 1 / rate(EUR->X)
        // If converting X -> Y: convert X->EUR then EUR->Y

        if ($from === 'EUR') {
            $rate = $this->getEcbRate($to, $asOf);
            return (float)$rate;
        }

        if ($to === 'EUR') {
            $rate = $this->getEcbRate($from, $asOf);
            return 1.0 / (float)$rate;
        }

        // X -> Y via EUR
        $rateFromEur = $this->getEcbRate($from, $asOf);
        $rateToEur = $this->getEcbRate($to, $asOf);

        // X -> EUR: 1 / rate(EUR->X)
        // EUR -> Y: rate(EUR->Y)
        // X -> Y: (1 / rate(EUR->X)) * rate(EUR->Y) = rate(EUR->Y) / rate(EUR->X)
        $result = (float)$rateToEur / (float)$rateFromEur;

        return $result;
    }

    /**
     * Get ECB rate for EUR -> quoteCurrency
     *
     * @param string $quoteCurrency Quote currency (e.g. USD, RON)
     * @param CarbonInterface|null $asOf Date for rate
     * @return float Rate value
     * @throws DomainException If rate not found
     */
    private function getEcbRate(string $quoteCurrency, ?CarbonInterface $asOf = null): float
    {
        $quoteCurrency = strtoupper($quoteCurrency);

        if ($asOf !== null) {
            $asOfDate = $asOf->format('Y-m-d');

            // Try exact date first (use whereDate to handle date casting)
            $rate = FxRate::where('base_currency', 'EUR')
                ->where('quote_currency', $quoteCurrency)
                ->whereDate('as_of_date', $asOfDate)
                ->first();

            if ($rate) {
                return (float)$rate->rate;
            }

            // If not found, use latest prior date
            $rate = FxRate::where('base_currency', 'EUR')
                ->where('quote_currency', $quoteCurrency)
                ->whereDate('as_of_date', '<=', $asOfDate)
                ->orderBy('as_of_date', 'desc')
                ->first();
        } else {
            // Use latest available date
            $latestDate = $this->getLatestDate();

            if ($latestDate === null) {
                throw new DomainException("No FX rates available in database");
            }

            $rate = FxRate::where('base_currency', 'EUR')
                ->where('quote_currency', $quoteCurrency)
                ->whereDate('as_of_date', $latestDate)
                ->first();
        }

        if (!$rate) {
            throw new DomainException(
                "FX rate not available: EUR -> {$quoteCurrency}" .
                ($asOf ? " (as of {$asOf->format('Y-m-d')})" : '')
            );
        }

        return (float)$rate->rate;
    }

    /**
     * Get the latest available date for FX rates
     *
     * @return string|null Date in Y-m-d format or null if no rates exist
     */
    public function getLatestDate(): ?string
    {
        $latest = FxRate::orderBy('as_of_date', 'desc')
            ->value('as_of_date');

        return $latest ? Carbon::parse($latest)->format('Y-m-d') : null;
    }
}
