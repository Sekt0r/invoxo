<?php

namespace App\Support;

class VatId
{
    /**
     * Normalize country code: uppercase 2 letters
     */
    public static function normalizeCountry(string $countryCode): string
    {
        return strtoupper(trim($countryCode));
    }

    /**
     * Normalize VAT ID: uppercase, strip spaces, dots, dashes, and non-alnum
     */
    public static function normalizeVatId(string $vatId): string
    {
        // Remove spaces, dots, dashes, and any non-alphanumeric characters
        $normalized = preg_replace('/[^a-zA-Z0-9]/', '', trim($vatId));
        // Uppercase
        return strtoupper($normalized);
    }
}


