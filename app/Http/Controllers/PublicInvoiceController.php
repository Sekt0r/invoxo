<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicInvoiceController extends Controller
{
    public function show(Request $request, string $public_id): View
    {
        $invoice = Invoice::where('public_id', $public_id)
            ->with(['invoiceItems', 'company', 'client'])
            ->first();

        if (!$invoice) {
            abort(404);
        }

        $token = $request->query('t');
        if (empty($token) || !hash_equals($invoice->share_token, (string)$token)) {
            abort(404);
        }

        return view('invoice.share', [
            'invoice' => $invoice,
        ]);
    }
}





