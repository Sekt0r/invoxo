<?php

namespace App\Services;

use App\Models\Invoice;

final class InvoiceTotalsService
{
    /**
     * Recalculate invoice totals from items.
     * Minor unit assumed to be 2 decimals; extend later using ISO 4217 map if needed.
     */
    public function recalculate(Invoice $invoice): void
    {
        $subtotal = 0;

        foreach ($invoice->invoiceItems as $item) {
            $qty = (float)$item->quantity;
            $unit = (int)$item->unit_price_minor;

            $line = (int)round($qty * $unit, 0, PHP_ROUND_HALF_UP);
            $item->line_total_minor = $line;
            $item->save();

            $subtotal += $line;
        }

        $vatRate = (float)$invoice->vat_rate;
        $vat = (int)round($subtotal * ($vatRate / 100.0), 0, PHP_ROUND_HALF_UP);

        $invoice->subtotal_minor = $subtotal;
        $invoice->vat_minor = $vat;
        $invoice->total_minor = $subtotal + $vat;
    }
}
