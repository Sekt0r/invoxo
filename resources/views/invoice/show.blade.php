<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoice</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($invoice->status === 'draft' && isset($vatChanged) && $vatChanged)
                        <div class="mb-6 p-4 bg-yellow-50 border border-yellow-300 rounded-md">
                            <h3 class="text-sm font-semibold text-yellow-800 mb-2">
                                Client VAT validation status changed since this draft was last computed.
                            </h3>
                            <div class="text-sm text-yellow-700 space-y-1">
                                <div>
                                    <span class="font-medium">Previously:</span>
                                    <span class="capitalize">{{ $previousVatStatus ?? 'unknown' }}</span>
                                </div>
                                <div>
                                    <span class="font-medium">Current:</span>
                                    <span class="capitalize">{{ $currentVatStatus ?? 'unknown' }}</span>
                                </div>
                                @if($vatStatusChangedAt)
                                    <div>
                                        <span class="font-medium">Changed at:</span>
                                        {{ $vatStatusChangedAt->format('Y-m-d H:i') }}
                                    </div>
                                @endif
                            </div>
                            <p class="mt-3 text-xs text-yellow-600">
                                VAT will be recomputed automatically when you save or issue the invoice.
                            </p>
                        </div>
                    @endif

                    <div class="space-y-4">
                        <div class="border-b pb-4">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Seller Details</h3>
                            <div class="space-y-1 text-sm">
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

                                <div class="text-gray-900">{{ $seller['company_name'] ?? $invoice->company->name }}</div>

                                {{-- Address --}}
                                @if($seller)
                                    @if(!empty($seller['address_line1']))
                                        <div class="text-gray-600">{{ $seller['address_line1'] }}</div>
                                    @endif
                                    @if(!empty($seller['address_line2']))
                                        <div class="text-gray-600">{{ $seller['address_line2'] }}</div>
                                    @endif
                                    @if(!empty($seller['city']) || !empty($seller['postal_code']))
                                        <div class="text-gray-600">
                                            @if(!empty($seller['postal_code'])){{ $seller['postal_code'] }}@endif
                                            @if(!empty($seller['postal_code']) && !empty($seller['city'])) &nbsp;@endif
                                            @if(!empty($seller['city'])){{ $seller['city'] }}@endif
                                        </div>
                                    @endif
                                    @if(!empty($seller['country_code']))
                                        <div class="text-gray-600">{{ $seller['country_code'] }}</div>
                                    @endif
                                @else
                                    @if($invoice->company->address_line1)
                                        <div class="text-gray-600">{{ $invoice->company->address_line1 }}</div>
                                    @endif
                                    @if($invoice->company->address_line2)
                                        <div class="text-gray-600">{{ $invoice->company->address_line2 }}</div>
                                    @endif
                                    @if($invoice->company->city || $invoice->company->postal_code)
                                        <div class="text-gray-600">
                                            @if($invoice->company->postal_code){{ $invoice->company->postal_code }}@endif
                                            @if($invoice->company->postal_code && $invoice->company->city) &nbsp;@endif
                                            @if($invoice->company->city){{ $invoice->company->city }}@endif
                                        </div>
                                    @endif
                                    @if($invoice->company->country_code)
                                        <div class="text-gray-600">{{ $invoice->company->country_code }}</div>
                                    @endif
                                @endif

                                {{-- Registration number --}}
                                @php
                                    $regNumber = $seller['registration_number'] ?? $invoice->company->registration_number;
                                    $regLabels = $countryLabels['registration_number'] ?? $defaultLabels['registration_number'] ?? [];
                                    $regLabel = $regLabels['label'] ?? 'Registration';
                                @endphp
                                @if($regNumber)
                                    <div class="text-gray-600">{{ $regLabel }}: {{ $regNumber }}</div>
                                @endif

                                {{-- Tax identifier --}}
                                @php
                                    $taxId = $seller['tax_identifier'] ?? $invoice->company->tax_identifier;
                                    $taxLabels = $countryLabels['tax_identifier'] ?? $defaultLabels['tax_identifier'] ?? [];
                                    $taxLabel = $taxLabels['label'] ?? 'Tax Identifier';
                                @endphp
                                @if($taxId)
                                    <div class="text-gray-600">{{ $taxLabel }}: {{ $taxId }}</div>
                                @endif

                                {{-- VAT ID --}}
                                @php
                                    $vatId = $seller['vat_id'] ?? $invoice->company->vat_id;
                                @endphp
                                @if($vatId)
                                    <div class="text-gray-600">VAT ID: {{ $vatId }}</div>
                                @endif
                            </div>
                        </div>

                        {{-- Payment Details (if issued) --}}
                        @if($invoice->status !== 'draft' && $invoice->payment_details)
                            <div class="border-b pb-4">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">Payment Details</h3>
                                <div class="space-y-3 text-sm">
                                    <div class="text-gray-900">{{ $invoice->payment_details['company_name'] ?? $invoice->company->name }}</div>
                                    @if(isset($invoice->payment_details['currency']))
                                        <div class="text-gray-600">Currency: {{ $invoice->payment_details['currency'] }}</div>
                                    @endif
                                    @if(isset($invoice->payment_details['accounts']) && is_array($invoice->payment_details['accounts']))
                                        <div class="space-y-2">
                                            @foreach($invoice->payment_details['accounts'] as $account)
                                                <div class="p-2 bg-gray-50 rounded border border-gray-200">
                                                    @if(!empty($account['nickname']))
                                                        <div class="font-medium text-gray-900">{{ $account['nickname'] }}</div>
                                                    @endif
                                                    <div class="text-gray-600">IBAN: {{ $account['iban'] ?? '—' }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @elseif(isset($invoice->payment_details['iban']))
                                        {{-- Legacy format (single account) --}}
                                        <div class="text-gray-600">IBAN: {{ $invoice->payment_details['iban'] }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Status" />
                                <div class="mt-1">
                                    @if($invoice->status === 'paid')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Paid
                                        </span>
                                    @else
                                        <span class="text-gray-900">{{ ucfirst($invoice->status) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <x-input-label value="Invoice number" />
                                <div class="mt-1 text-gray-900">{{ $invoice->number ?? '—' }}</div>
                            </div>
                        </div>

                        @if($invoice->status === 'draft')
                            <div class="border-t pt-4">
                                @if($errors->has('limit'))
                                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                                        <p class="text-sm text-red-800">{{ $errors->first('limit') }}</p>
                                    </div>
                                @endif
                                @if($errors->has('pdf'))
                                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                                        <p class="text-sm text-red-800">{{ $errors->first('pdf') }}</p>
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('invoices.issue', $invoice) }}">
                                    @csrf
                                    <x-primary-button>Issue invoice</x-primary-button>
                                </form>
                            </div>
                        @endif

                        @if($invoice->status === 'issued')
                            <div class="border-t pt-4 space-y-4">
                                @if($errors->has('pdf'))
                                    <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                                        <p class="text-sm text-red-800">{{ $errors->first('pdf') }}</p>
                                    </div>
                                @endif
                                <div class="flex items-center gap-4">
                                    <a href="{{ route('invoices.pdf', $invoice) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        Download PDF
                                    </a>
                                    <form method="POST" action="{{ route('invoices.markPaid', $invoice) }}" class="inline">
                                        @csrf
                                        <x-primary-button type="submit">Mark as paid</x-primary-button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <div class="border-t pt-4">
                            <x-input-label value="Public link" />
                            <div class="mt-1">
                                <input type="text" readonly value="{{ route('invoices.share', $invoice->public_id) }}?t={{ $invoice->share_token }}" class="block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" onclick="this.select();">
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Share this link to allow others to view the invoice</p>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t">
                            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-700 hover:underline">Back</a>
                        </div>

                        <div class="border-t pt-6 mt-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Activity</h3>
                            @if($events->isEmpty())
                                <p class="text-sm text-gray-500">No activity recorded yet.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($events as $event)
                                        <div class="flex items-start space-x-3 text-sm">
                                            <div class="flex-shrink-0 w-2 h-2 rounded-full bg-gray-400 mt-2"></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <span class="font-medium text-gray-900">
                                                            @if($event->event_type === 'issued')
                                                                Issued
                                                            @elseif($event->event_type === 'status_changed')
                                                                Status changed
                                                            @elseif($event->event_type === 'draft_updated')
                                                                Draft updated
                                                            @else
                                                                {{ ucfirst(str_replace('_', ' ', $event->event_type)) }}
                                                            @endif
                                                        </span>
                                                        @if($event->from_status && $event->to_status)
                                                            <span class="text-gray-600 ml-2">
                                                                ({{ ucfirst($event->from_status) }} → {{ ucfirst($event->to_status) }})
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <span class="text-gray-500 text-xs">
                                                        {{ $event->created_at->format('Y-m-d H:i') }}
                                                    </span>
                                                </div>
                                                @if($event->message)
                                                    <p class="text-gray-600 mt-1">{{ $event->message }}</p>
                                                @endif
                                                <p class="text-gray-500 text-xs mt-1">
                                                    by {{ $event->user ? $event->user->name : 'System' }}
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
