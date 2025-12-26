<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Client Details') }}
            </h2>
            <a href="{{ route('clients.edit', $client) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Edit') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Client Information</h3>
                        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Name</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Country Code</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->country_code }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">VAT ID</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $client->vat_id ?? '—' }}</dd>
                            </div>
                            @if($client->vatIdentity)
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">VAT Validation</dt>
                                    <dd class="mt-1 text-sm">
                                        @if($client->vatIdentity->status === 'pending')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                VIES: pending
                                            </span>
                                        @elseif($client->vatIdentity->status === 'valid')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                VIES: valid
                                                @if($client->vatIdentity->validated_at)
                                                    (checked {{ $client->vatIdentity->validated_at->format('Y-m-d') }})
                                                @endif
                                            </span>
                                        @elseif($client->vatIdentity->status === 'invalid')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                VIES: invalid
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                VIES: unavailable
                                                @if($client->vatIdentity->validated_at)
                                                    (checked {{ $client->vatIdentity->validated_at->format('Y-m-d') }})
                                                @endif
                                                @if($client->vatIdentity->last_error)
                                                    <details class="ml-2 inline">
                                                        <summary class="cursor-pointer text-xs underline">Error details</summary>
                                                        <span class="block mt-1 text-xs">{{ $client->vatIdentity->last_error }}</span>
                                                    </details>
                                                @endif
                                            </span>
                                        @endif
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    @if($client->vatIdentity && $client->vatIdentity->status === 'valid' && ($client->vatIdentity->name || $client->vatIdentity->address))
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">VIES Registered Details</h3>
                            <dl class="grid grid-cols-1 gap-4">
                                @if($client->vatIdentity->name)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Registered Name</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ $client->vatIdentity->name }}</dd>
                                    </div>
                                @endif
                                @if($client->vatIdentity->address)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Registered Address</dt>
                                        <dd class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $client->vatIdentity->address }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endif

                    <div class="flex items-center gap-4 pt-4 border-t">
                        <a href="{{ route('clients.index') }}" class="text-sm text-gray-700 hover:underline">Back to Clients</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
