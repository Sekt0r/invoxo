<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InvoiceSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class InvoiceNumberService
{
    /**
     * Get the next invoice number for a company based on issue date.
     * Uses a sequence table with row locks to guarantee unique, monotonic numbers.
     *
     * @param Company $company
     * @param \DateTimeInterface|string|null $issueDate If null, uses current date
     * @return string
     */
    public function nextNumber(Company $company, $issueDate = null): string
    {
        // Normalize prefix: remove trailing dashes, default to "INV"
        $prefix = rtrim($company->invoice_prefix ?: 'INV', '-');

        // Determine year from issue date (not from current date)
        if ($issueDate === null) {
            $issueDate = Carbon::now();
        } elseif (is_string($issueDate)) {
            $issueDate = Carbon::parse($issueDate);
        }
        $year = $issueDate->year;

        // Lock the sequence row for this company/year/prefix combination
        $sequence = InvoiceSequence::where('company_id', $company->id)
            ->where('year', $year)
            ->where('prefix', $prefix)
            ->lockForUpdate()
            ->first();

        // Create sequence row if it doesn't exist
        if (!$sequence) {
            try {
                $sequence = InvoiceSequence::create([
                    'company_id' => $company->id,
                    'year' => $year,
                    'prefix' => $prefix,
                    'last_number' => 0,
                ]);
                // Re-lock the newly created row
                $sequence = InvoiceSequence::whereKey($sequence->id)
                    ->lockForUpdate()
                    ->first();
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle race condition: if another process created it concurrently, fetch it
                if ($e->getCode() === '23000') { // Integrity constraint violation
                    $sequence = InvoiceSequence::where('company_id', $company->id)
                        ->where('year', $year)
                        ->where('prefix', $prefix)
                        ->lockForUpdate()
                        ->first();
                } else {
                    throw $e;
                }
            }
        }

        // Increment the sequence number
        $sequence->last_number += 1;
        $sequence->save();

        // Format: "{prefix}-{year}-{sequence padded to 6}"
        return $prefix . '-' . $year . '-' . str_pad((string)$sequence->last_number, 6, '0', STR_PAD_LEFT);
    }
}
