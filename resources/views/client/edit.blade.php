<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Client</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-6">
                        @csrf
                        @method('put')

                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $client->name)" required autofocus />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        {{-- Expose identity labels to JS for dynamic updates --}}
                        <script>
                            window.IDENTITY_LABELS = @json($identityLabels ?? []);
                        </script>

                        <div>
                            <x-input-label for="country_code" value="Country" />
                            <input
                                id="country_code"
                                name="country_code"
                                type="text"
                                list="country-options"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase"
                                value="{{ old('country_code', $client->country_code) }}"
                                required
                                autocomplete="off"
                                onchange="updateIdentityLabels()"
                            />
                            <datalist id="country-options">
                                @foreach(config('countries') as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </datalist>
                            <x-input-error class="mt-2" :messages="$errors->get('country_code')" />
                            <p class="mt-1 text-xs text-gray-500">Type to search, stores ISO code (e.g., DE, FR)</p>
                        </div>

                        {{-- Client Company Identity (Optional) --}}
                        <div class="border-t pt-6 mt-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4">Client Company Identity (Optional)</h3>

                            <div>
                                <x-input-label id="label_registration_number" for="registration_number" :value="__('Registration Number')" />
                                <x-text-input id="registration_number" name="registration_number" type="text" class="mt-1 block w-full" :value="old('registration_number', $client->registration_number)" maxlength="255" />
                                <x-input-error class="mt-2" :messages="$errors->get('registration_number')" />
                                <p id="hint_registration_number" class="mt-1 text-xs text-gray-500">Company register entry / file number (optional).</p>
                            </div>

                            <div class="mt-4">
                                <x-input-label id="label_tax_identifier" for="tax_identifier" :value="__('Tax Identifier')" />
                                <x-text-input id="tax_identifier" name="tax_identifier" type="text" class="mt-1 block w-full" :value="old('tax_identifier', $client->tax_identifier)" maxlength="255" />
                                <x-input-error class="mt-2" :messages="$errors->get('tax_identifier')" />
                                <p id="hint_tax_identifier" class="mt-1 text-xs text-gray-500">National tax identification number (not VAT ID) (optional).</p>
                            </div>

                            <div class="mt-4">
                                <x-input-label for="vat_id" value="VAT ID (optional)" />
                                <x-text-input id="vat_id" name="vat_id" type="text" class="mt-1 block w-full" :value="old('vat_id', $client->vat_id)" maxlength="32" />
                                <x-input-error class="mt-2" :messages="$errors->get('vat_id')" />
                                <p class="mt-1 text-xs text-gray-500">Optional VAT identification number</p>
                                @if($client->vatIdentity)
                                    <div class="mt-2">
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
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Client Address (Optional) --}}
                        <div class="border-t pt-6 mt-6">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4">Address (Optional)</h3>

                            <div>
                                <x-input-label for="address_line1" value="Address Line 1" />
                                <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $client->address_line1)" maxlength="255" />
                                <x-input-error class="mt-2" :messages="$errors->get('address_line1')" />
                            </div>

                            <div class="mt-4">
                                <x-input-label for="address_line2" value="Address Line 2 (Optional)" />
                                <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $client->address_line2)" maxlength="255" />
                                <x-input-error class="mt-2" :messages="$errors->get('address_line2')" />
                            </div>

                            <div class="mt-4">
                                <x-input-label for="city" value="City" />
                                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $client->city)" maxlength="255" />
                                <x-input-error class="mt-2" :messages="$errors->get('city')" />
                            </div>

                            <div class="mt-4">
                                <x-input-label for="postal_code" value="Postal Code" />
                                <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $client->postal_code)" maxlength="32" />
                                <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('clients.index') }}" class="text-sm text-gray-700 hover:underline">Back</a>
                            <x-primary-button>Update client</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateIdentityLabels() {
            const countryCode = document.getElementById('country_code').value?.toUpperCase() || '';
            const labels = window.IDENTITY_LABELS || {};
            const defaultLabels = labels['default'] || {};
            const countryLabels = labels[countryCode] || {};

            // Update registration_number
            const regLabelEl = document.getElementById('label_registration_number');
            const regHintEl = document.getElementById('hint_registration_number');
            if (regLabelEl && regHintEl) {
                const regLabels = countryLabels['registration_number'] || defaultLabels['registration_number'] || {};
                regLabelEl.textContent = regLabels['label'] || 'Registration number';
                regHintEl.textContent = regLabels['hint'] || '';
            }

            // Update tax_identifier
            const taxLabelEl = document.getElementById('label_tax_identifier');
            const taxHintEl = document.getElementById('hint_tax_identifier');
            if (taxLabelEl && taxHintEl) {
                const taxLabels = countryLabels['tax_identifier'] || defaultLabels['tax_identifier'] || {};
                taxLabelEl.textContent = taxLabels['label'] || 'Tax identifier';
                taxHintEl.textContent = taxLabels['hint'] || '';
            }
        }

        // Update labels on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateIdentityLabels();
        });
    </script>
</x-app-layout>
