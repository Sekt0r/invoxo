<?php

namespace App\Services\Vat;

use App\Contracts\VatValidationProviderInterface;
use App\Data\VatValidationResult;
use Carbon\Carbon;

final class FakeVatValidationProvider implements VatValidationProviderInterface
{
    public function validate(string $countryCode, string $vatId): VatValidationResult
    {
        $normalizedCountryCode = strtoupper(trim($countryCode));
        $normalizedVatId = strtoupper(trim($vatId));

        // Remove country code prefix if present
        if (str_starts_with($normalizedVatId, $normalizedCountryCode)) {
            $normalizedVatId = substr($normalizedVatId, strlen($normalizedCountryCode));
        }

        // Deterministic validation rules based on suffix (case-insensitive check after normalization)
        // Check longest suffixes first to avoid false matches (e.g., 'INVALID' contains 'VALID')
        $status = 'unknown';
        $companyName = null;
        $companyAddress = null;

        if (str_ends_with($normalizedVatId, 'INVALID')) {
            $status = 'invalid';
        } elseif (str_ends_with($normalizedVatId, 'PENDING')) {
            $status = 'pending';
        } elseif (str_ends_with($normalizedVatId, 'VALID')) {
            $status = 'valid';
            $companyName = "Test Company {$normalizedCountryCode}";
            $companyAddress = "Test Address, {$normalizedCountryCode}";
        }

        return new VatValidationResult(
            status: $status,
            companyName: $companyName,
            companyAddress: $companyAddress,
            checkedAt: Carbon::now(),
        );
    }
}
