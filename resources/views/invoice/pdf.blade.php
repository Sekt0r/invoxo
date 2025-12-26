<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        h2 {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        h3 {
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .company-info {
            margin-bottom: 30px;
        }
        .company-vat {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .invoice-header {
            margin-bottom: 30px;
        }
        .dates {
            margin-top: 10px;
            font-size: 11px;
        }
        .date-row {
            margin: 5px 0;
        }
        .client-info {
            margin-bottom: 30px;
        }
        .client-info div {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        thead {
            background-color: #f3f4f6;
        }
        th {
            text-align: left;
            padding: 8px;
            font-weight: bold;
            border-bottom: 2px solid #e5e7eb;
        }
        th.text-right {
            text-align: right;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        td.text-right {
            text-align: right;
        }
        .totals {
            width: 250px;
            margin-left: auto;
            margin-bottom: 30px;
        }
        .totals-row {
            padding: 5px 0;
            clear: both;
        }
        .totals-label {
            float: left;
            font-weight: normal;
        }
        .totals-value {
            float: right;
        }
        .totals-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
            clear: both;
        }
        .totals-total .totals-label {
            font-weight: bold;
        }
        .vat-reason {
            font-style: italic;
            margin-top: 20px;
            font-size: 11px;
            padding: 10px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="company-info">
        @php
            // Use snapshot for issued invoices, live company for drafts
            if ($invoice->status !== 'draft' && $invoice->seller_details) {
                $seller = $invoice->seller_details;
                $sellerCountryCode = $seller['country_code'] ?? null;
            } else {
                $seller = null;
                $sellerCountryCode = $invoice->company->country_code;
            }
            $identityLabels = config('company_identity_labels', []);
            $defaultLabels = $identityLabels['default'] ?? [];
            $countryLabels = $sellerCountryCode ? ($identityLabels[$sellerCountryCode] ?? []) : [];
        @endphp

        <h1>{{ $seller['company_name'] ?? $invoice->company->name }}</h1>

        {{-- Address --}}
        @if($seller)
            @if(!empty($seller['address_line1']))
                <div>{{ $seller['address_line1'] }}</div>
            @endif
            @if(!empty($seller['address_line2']))
                <div>{{ $seller['address_line2'] }}</div>
            @endif
            @if(!empty($seller['city']) || !empty($seller['postal_code']))
                <div>
                    @if(!empty($seller['postal_code'])){{ $seller['postal_code'] }}@endif
                    @if(!empty($seller['postal_code']) && !empty($seller['city'])) &nbsp;@endif
                    @if(!empty($seller['city'])){{ $seller['city'] }}@endif
                </div>
            @endif
            @if(!empty($seller['country_code']))
                <div>{{ $seller['country_code'] }}</div>
            @endif
        @else
            @if($invoice->company->address_line1)
                <div>{{ $invoice->company->address_line1 }}</div>
            @endif
            @if($invoice->company->address_line2)
                <div>{{ $invoice->company->address_line2 }}</div>
            @endif
            @if($invoice->company->city || $invoice->company->postal_code)
                <div>
                    @if($invoice->company->postal_code){{ $invoice->company->postal_code }}@endif
                    @if($invoice->company->postal_code && $invoice->company->city) &nbsp;@endif
                    @if($invoice->company->city){{ $invoice->company->city }}@endif
                </div>
            @endif
            @if($invoice->company->country_code)
                <div>{{ $invoice->company->country_code }}</div>
            @endif
        @endif

        {{-- Registration number --}}
        @php
            $regNumber = $seller['registration_number'] ?? $invoice->company->registration_number;
            $regLabels = $countryLabels['registration_number'] ?? $defaultLabels['registration_number'] ?? [];
            $regLabel = $regLabels['label'] ?? 'Registration';
        @endphp
        @if($regNumber)
            <div class="company-registration">{{ $regLabel }}: {{ $regNumber }}</div>
        @endif

        {{-- Tax identifier --}}
        @php
            $taxId = $seller['tax_identifier'] ?? $invoice->company->tax_identifier;
            $taxLabels = $countryLabels['tax_identifier'] ?? $defaultLabels['tax_identifier'] ?? [];
            $taxLabel = $taxLabels['label'] ?? 'Tax Identifier';
        @endphp
        @if($taxId)
            <div class="company-registration">{{ $taxLabel }}: {{ $taxId }}</div>
        @endif

        {{-- VAT ID --}}
        @php
            $vatId = $seller['vat_id'] ?? $invoice->company->vat_id;
        @endphp
        @if($vatId)
            <div class="company-vat">VAT ID: {{ $vatId }}</div>
        @endif
    </div>

    <div class="invoice-header">
        <h2>Invoice {{ $invoice->number ?? 'Draft' }}</h2>
        <div class="dates">
            @if($invoice->issue_date)
                <div class="date-row"><strong>Issue date:</strong> {{ $invoice->issue_date->format('Y-m-d') }}</div>
            @endif
            @if($invoice->due_date)
                <div class="date-row"><strong>Due date:</strong> {{ $invoice->due_date->format('Y-m-d') }}</div>
            @endif
        </div>
    </div>

    <div class="client-info">
        <h3>Bill to:</h3>
        <div>{{ $invoice->client->name }}</div>
        <div>{{ $invoice->client->country_code }}</div>
        @if($invoice->client->vat_id)
            <div>VAT ID: {{ $invoice->client->vat_id }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->invoiceItems as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ number_format((float)$item->quantity, 2) }}</td>
                    <td class="text-right">{{ \App\Support\Money::format($item->unit_price_minor, $invoice->currency ?? 'EUR') }}</td>
                    <td class="text-right">{{ \App\Support\Money::format($item->line_total_minor, $invoice->currency ?? 'EUR') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span class="totals-label">Subtotal:</span>
            <span class="totals-value">{{ \App\Support\Money::format($invoice->subtotal_minor, $invoice->currency ?? 'EUR') }}</span>
        </div>
        <div class="totals-row">
            <span class="totals-label">VAT ({{ number_format((float)$invoice->vat_rate, 2) }}%):</span>
            <span class="totals-value">{{ \App\Support\Money::format($invoice->vat_minor, $invoice->currency ?? 'EUR') }}</span>
        </div>
        <div class="totals-row totals-total">
            <span class="totals-label">Total:</span>
            <span class="totals-value">{{ \App\Support\Money::format($invoice->total_minor, $invoice->currency ?? 'EUR') }}</span>
        </div>
    </div>

    @if($invoice->vat_reason_text)
        <div class="vat-reason">
            {{ $invoice->vat_reason_text }}
        </div>
    @endif

    {{-- Payment Details (if issued) --}}
    @if($invoice->status !== 'draft' && $invoice->payment_details)
        <div class="company-info" style="margin-top: 40px;">
            <h3>Payment Details</h3>
            <div>{{ $invoice->payment_details['company_name'] ?? $invoice->company->name }}</div>
            @if(isset($invoice->payment_details['currency']))
                <div>Currency: {{ $invoice->payment_details['currency'] }}</div>
            @endif
            @if(isset($invoice->payment_details['accounts']) && is_array($invoice->payment_details['accounts']))
                <div style="margin-top: 10px;">
                    @foreach($invoice->payment_details['accounts'] as $account)
                        <div style="margin: 5px 0;">
                            @if(!empty($account['nickname']))
                                <div><strong>{{ $account['nickname'] }}</strong></div>
                            @endif
                            <div>IBAN: {{ $account['iban'] ?? '—' }}</div>
                        </div>
                    @endforeach
                </div>
            @elseif(isset($invoice->payment_details['iban']))
                {{-- Legacy format --}}
                <div>IBAN: {{ $invoice->payment_details['iban'] }}</div>
            @endif
        </div>
    @endif
</body>
</html>
