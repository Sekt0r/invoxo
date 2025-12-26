<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number ?? 'Draft' }}</title>
    @vite(['resources/css/app.css'])
    @php
        $invoiceCurrency = $invoice->currency ?? 'EUR';
    @endphp
</head>
<body class="bg-gray-50 font-sans">
    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg p-6 sm:p-8">
            <div class="mb-8">
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

                <h1 class="text-2xl font-semibold text-gray-900">{{ $seller['company_name'] ?? $invoice->company->name }}</h1>

                {{-- Address --}}
                @if($seller)
                    @if(!empty($seller['address_line1']))
                        <div class="text-sm text-gray-600 mt-1">{{ $seller['address_line1'] }}</div>
                    @endif
                    @if(!empty($seller['address_line2']))
                        <div class="text-sm text-gray-600 mt-1">{{ $seller['address_line2'] }}</div>
                    @endif
                    @if(!empty($seller['city']) || !empty($seller['postal_code']))
                        <div class="text-sm text-gray-600 mt-1">
                            @if(!empty($seller['postal_code'])){{ $seller['postal_code'] }}@endif
                            @if(!empty($seller['postal_code']) && !empty($seller['city'])) &nbsp;@endif
                            @if(!empty($seller['city'])){{ $seller['city'] }}@endif
                        </div>
                    @endif
                    @if(!empty($seller['country_code']))
                        <div class="text-sm text-gray-600 mt-1">{{ $seller['country_code'] }}</div>
                    @endif
                @else
                    @if($invoice->company->address_line1)
                        <div class="text-sm text-gray-600 mt-1">{{ $invoice->company->address_line1 }}</div>
                    @endif
                    @if($invoice->company->address_line2)
                        <div class="text-sm text-gray-600 mt-1">{{ $invoice->company->address_line2 }}</div>
                    @endif
                    @if($invoice->company->city || $invoice->company->postal_code)
                        <div class="text-sm text-gray-600 mt-1">
                            @if($invoice->company->postal_code){{ $invoice->company->postal_code }}@endif
                            @if($invoice->company->postal_code && $invoice->company->city) &nbsp;@endif
                            @if($invoice->company->city){{ $invoice->company->city }}@endif
                        </div>
                    @endif
                    @if($invoice->company->country_code)
                        <div class="text-sm text-gray-600 mt-1">{{ $invoice->company->country_code }}</div>
                    @endif
                @endif

                {{-- Registration number --}}
                @php
                    $regNumber = $seller['registration_number'] ?? $invoice->company->registration_number;
                    $regLabels = $countryLabels['registration_number'] ?? $defaultLabels['registration_number'] ?? [];
                    $regLabel = $regLabels['label'] ?? 'Registration';
                @endphp
                @if($regNumber)
                    <div class="text-sm text-gray-600 mt-1">{{ $regLabel }}: {{ $regNumber }}</div>
                @endif

                {{-- Tax identifier --}}
                @php
                    $taxId = $seller['tax_identifier'] ?? $invoice->company->tax_identifier;
                    $taxLabels = $countryLabels['tax_identifier'] ?? $defaultLabels['tax_identifier'] ?? [];
                    $taxLabel = $taxLabels['label'] ?? 'Tax Identifier';
                @endphp
                @if($taxId)
                    <div class="text-sm text-gray-600 mt-1">{{ $taxLabel }}: {{ $taxId }}</div>
                @endif

                {{-- VAT ID --}}
                @php
                    $vatId = $seller['vat_id'] ?? $invoice->company->vat_id;
                @endphp
                @if($vatId)
                    <div class="text-sm text-gray-600 mt-1">VAT ID: {{ $vatId }}</div>
                @endif
            </div>

            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Invoice {{ $invoice->number ?? 'Draft' }}</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-600">
                    @if($invoice->issue_date)
                        <div>
                            <span class="font-medium">Issue date:</span> {{ $invoice->issue_date->format('Y-m-d') }}
                        </div>
                    @endif
                    @if($invoice->due_date)
                        <div>
                            <span class="font-medium">Due date:</span> {{ $invoice->due_date->format('Y-m-d') }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Bill to:</h3>
                <div class="text-sm text-gray-700">
                    <div class="font-medium">{{ $invoice->client->name }}</div>
                    <div>{{ $invoice->client->country_code }}</div>
                    @if($invoice->client->vat_id)
                        <div>VAT ID: {{ $invoice->client->vat_id }}</div>
                    @endif
                </div>
            </div>

            <div class="mb-8">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($invoice->invoiceItems as $item)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $item->description }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ number_format((float)$item->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 text-right">{{ \App\Support\Money::format($item->unit_price_minor, $invoiceCurrency) }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 text-right">{{ \App\Support\Money::format($item->line_total_minor, $invoiceCurrency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end mb-8">
                <div class="w-full sm:w-64">
                    <div class="flex justify-between py-2 text-sm text-gray-600">
                        <span class="font-medium">Subtotal:</span>
                        <span>{{ \App\Support\Money::format($invoice->subtotal_minor, $invoiceCurrency) }}</span>
                    </div>
                    <div class="flex justify-between py-2 text-sm text-gray-600">
                        <span class="font-medium">VAT ({{ number_format((float)$invoice->vat_rate, 2) }}%):</span>
                        <span>{{ \App\Support\Money::format($invoice->vat_minor, $invoiceCurrency) }}</span>
                    </div>
                    <div class="flex justify-between py-3 text-base font-semibold text-gray-900 border-t border-gray-200">
                        <span>Total:</span>
                        <span>{{ \App\Support\Money::format($invoice->total_minor, $invoiceCurrency) }}</span>
                    </div>
                </div>
            </div>

            @if($invoice->vat_reason_text)
                <div class="mt-4 p-3 bg-gray-50 rounded-md border border-gray-200">
                    <p class="text-sm text-gray-700 italic">{{ $invoice->vat_reason_text }}</p>
                </div>
            @endif

            {{-- Payment Details (if issued) --}}
            @if($invoice->status !== 'draft' && $invoice->payment_details)
                <div class="mt-8 border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Payment Details</h3>
                    <div class="space-y-3 text-sm">
                        <div class="text-gray-900">{{ $invoice->payment_details['company_name'] ?? $invoice->company->name }}</div>
                        @if(isset($invoice->payment_details['currency']))
                            <div class="text-gray-600">Currency: {{ $invoice->payment_details['currency'] }}</div>
                        @endif
                        @if(isset($invoice->payment_details['accounts']) && is_array($invoice->payment_details['accounts']))
                            <div class="space-y-2 mt-3">
                                @foreach($invoice->payment_details['accounts'] as $account)
                                    <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                                        @if(!empty($account['nickname']))
                                            <div class="font-medium text-gray-900">{{ $account['nickname'] }}</div>
                                        @endif
                                        <div class="text-gray-600">IBAN: {{ $account['iban'] ?? '—' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif(isset($invoice->payment_details['iban']))
                            {{-- Legacy format --}}
                            <div class="text-gray-600">IBAN: {{ $invoice->payment_details['iban'] }}</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
