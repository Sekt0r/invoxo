<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('clients.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('Add Client') }}
                        </a>
                        <a href="{{ route('clients.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('View All Clients') }}
                        </a>
                        <a href="{{ route('invoices.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('New Invoice') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Action Required Widget -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Action Required</h3>
                    </div>

                    @if($actionRequiredInvoices->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500">No invoices require action 🎉</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($actionRequiredInvoices as $invoice)
                                <div class="flex justify-between items-start py-2 border-b border-gray-100 last:border-0">
                                    <div class="flex-1">
                                        <a href="{{ $invoice->status === 'draft' ? route('invoices.edit', $invoice) : route('invoices.show', $invoice) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                                            {{ $invoice->number ?? 'Draft' }}
                                        </a>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                            @if($invoice->client)
                                                <span>{{ $invoice->client->name }}</span>
                                            @else
                                                <span class="text-gray-400">Client deleted</span>
                                            @endif
                                            <span>•</span>
                                            <span>{{ $invoice->issue_date ? $invoice->issue_date->format('M d, Y') : '—' }}</span>
                                            @if($invoice->currency && $invoice->total_minor)
                                                <span>•</span>
                                                <span>{{ \App\Support\Money::format($invoice->total_minor, $invoice->currency) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        @if($invoice->status === 'draft')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Draft
                                            </span>
                                        @elseif($invoice->status === 'issued')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                                Unpaid
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Clients Widget -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Clients</h3>
                        <span class="text-sm text-gray-500">{{ $clientsCount }} {{ $clientsCount === 1 ? 'client' : 'clients' }}</span>
                    </div>

                    @if($recentClients->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500 mb-4">No clients yet.</p>
                            <a href="{{ route('clients.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Add Client') }}
                            </a>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($recentClients as $client)
                                <div class="flex justify-between items-start py-2 border-b border-gray-100 last:border-0">
                                    <div class="flex-1">
                                        <a href="{{ route('clients.show', $client) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600">
                                            {{ $client->name }}
                                        </a>
                                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                                            <span>{{ $client->country_code }}</span>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex items-center gap-2">
                                        @if($client->vatIdentity && $client->vatIdentity->status)
                                            @if($client->vatIdentity->status === 'valid')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                    Valid
                                                </span>
                                            @elseif($client->vatIdentity->status === 'invalid')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    Invalid
                                                </span>
                                            @elseif($client->vatIdentity->status === 'pending')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    Pending
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    None
                                                </span>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                None
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200 flex gap-3">
                            <a href="{{ route('clients.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('Add Client') }}
                            </a>
                            <a href="{{ route('clients.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('View All Clients') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Invoice History Widget -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Invoice History</h3>
                    </div>

                    @if(empty($invoiceHistoryByMonth))
                        <div class="text-center py-8">
                            <p class="text-gray-500">No issued invoices yet</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($invoiceHistoryByMonth as $monthKey => $currencies)
                                <div class="border-b border-gray-100 last:border-0 pb-4 last:pb-0">
                                    <div class="text-sm font-medium text-gray-900 mb-2">
                                        {{ \Carbon\Carbon::createFromFormat('Y-m', $monthKey)->format('M Y') }}
                                    </div>
                                    <div class="space-y-1">
                                        @foreach($currencies as $currency => $totalMinor)
                                            <div class="flex justify-between items-center text-sm text-gray-600">
                                                <span>{{ $currency }}</span>
                                                <span class="font-medium">{{ \App\Support\Money::format($totalMinor, $currency) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Invoices Issued This Month</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">
                            This month: {{ $usage['used'] }} / {{ $usage['limit'] === null ? '∞' : $usage['limit'] }} issued invoices
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500">Revenue This Month</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">€{{ number_format($revenue_this_month_minor / 100, 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
