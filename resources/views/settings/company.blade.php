<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Company Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">
                                {{ __('Company Information') }}
                            </h2>

                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('Update your company information and settings.') }}
                            </p>
                        </header>

                        @if (session('status') === 'saved')
                            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                                <p class="text-sm text-green-800">Company settings saved.</p>
                            </div>
                        @endif

                        @if (session('company.override_country_changed') && $company->vat_override_enabled)
                            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                                <p class="text-sm text-yellow-800 font-medium">
                                    Country changed from {{ session('company.override_country_changed_from') }} to {{ session('company.override_country_changed_to') }}. Override is enabled.
                                </p>
                                <p class="mt-2 text-sm text-yellow-700">Keep override enabled?</p>
                                <div class="mt-3 flex gap-3">
                                    <form method="POST" action="{{ route('settings.company.override-decision') }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="keep">
                                        <x-primary-button type="submit" class="bg-yellow-600 hover:bg-yellow-700">
                                            {{ __('Keep Override') }}
                                        </x-primary-button>
                                    </form>
                                    <form method="POST" action="{{ route('settings.company.override-decision') }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="disable">
                                        <x-primary-button type="submit" class="bg-gray-600 hover:bg-gray-700">
                                            {{ __('Disable Override and Use Official Rate') }}
                                        </x-primary-button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        {{-- Expose identity labels to JS for dynamic updates --}}
                        <script>
                            window.IDENTITY_LABELS = @json($identityLabels ?? []);
                        </script>

                        <form method="post" action="{{ route('settings.company.update') }}" class="mt-6 space-y-6">
                            @csrf
                            @method('put')

                            <div>
                                <x-input-label for="name" :value="__('Company Name')" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $company->name)" required autofocus />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <x-input-label for="country_code" :value="__('Country')" />
                                <input
                                    id="country_code"
                                    name="country_code"
                                    type="text"
                                    list="country-options"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase"
                                    value="{{ old('country_code', $company->country_code) }}"
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
                                <p class="mt-1 text-xs text-gray-500">Type to search, stores ISO code (e.g., DE, FR, GB)</p>
                            </div>

                            {{-- Legal Identity Section --}}
                            <div class="border-t pt-6 mt-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4">Legal Identity</h3>

                                <div>
                                    <x-input-label id="label_registration_number" for="registration_number" :value="__('Registration Number')" />
                                    <x-text-input id="registration_number" name="registration_number" type="text" class="mt-1 block w-full" :value="old('registration_number', $company->registration_number)" required maxlength="255" />
                                    <x-input-error class="mt-2" :messages="$errors->get('registration_number')" />
                                    <p id="hint_registration_number" class="mt-1 text-xs text-gray-500">National company registration / trade register number. Required even if you're not VAT registered.</p>
                                </div>

                                <div class="mt-4">
                                    <x-input-label id="label_tax_identifier" for="tax_identifier" :value="__('Tax Identifier')" />
                                    <x-text-input id="tax_identifier" name="tax_identifier" type="text" class="mt-1 block w-full" :value="old('tax_identifier', $company->tax_identifier)" required maxlength="255" />
                                    <x-input-error class="mt-2" :messages="$errors->get('tax_identifier')" />
                                    <p id="hint_tax_identifier" class="mt-1 text-xs text-gray-500">National tax identification number (not VAT ID).</p>
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="vat_id" :value="__('VAT ID')" />
                                    <x-text-input id="vat_id" name="vat_id" type="text" class="mt-1 block w-full" :value="old('vat_id', $company->vat_id)" maxlength="32" />
                                    <x-input-error class="mt-2" :messages="$errors->get('vat_id')" />
                                    <p class="mt-1 text-xs text-gray-500">Optional VAT identification number</p>
                                @if($company->vatIdentity)
                                    <div class="mt-2">
                                        @if($company->vatIdentity->status === 'pending')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                VAT: pending
                                            </span>
                                        @elseif($company->vatIdentity->status === 'valid')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                VAT: valid
                                                @if($company->vatIdentity->validated_at)
                                                    (checked {{ $company->vatIdentity->validated_at->format('Y-m-d') }})
                                                @endif
                                            </span>
                                        @elseif($company->vatIdentity->status === 'invalid')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                VAT: invalid
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                VAT: unavailable
                                                @if($company->vatIdentity->validated_at)
                                                    (checked {{ $company->vatIdentity->validated_at->format('Y-m-d') }})
                                                @endif
                                                @if($company->vatIdentity->last_error)
                                                    <details class="ml-2 inline">
                                                        <summary class="cursor-pointer text-xs underline">Error details</summary>
                                                        <span class="block mt-1 text-xs">{{ $company->vatIdentity->last_error }}</span>
                                                    </details>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Address Section --}}
                            <div class="border-t pt-6 mt-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4">Address</h3>

                                <div>
                                    <x-input-label for="address_line1" :value="__('Address Line 1')" />
                                    <x-text-input id="address_line1" name="address_line1" type="text" class="mt-1 block w-full" :value="old('address_line1', $company->address_line1)" required maxlength="255" />
                                    <x-input-error class="mt-2" :messages="$errors->get('address_line1')" />
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="address_line2" :value="__('Address Line 2 (Optional)')" />
                                    <x-text-input id="address_line2" name="address_line2" type="text" class="mt-1 block w-full" :value="old('address_line2', $company->address_line2)" maxlength="255" />
                                    <x-input-error class="mt-2" :messages="$errors->get('address_line2')" />
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="city" :value="__('City')" />
                                    <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $company->city)" required maxlength="255" />
                                    <x-input-error class="mt-2" :messages="$errors->get('city')" />
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="postal_code" :value="__('Postal Code')" />
                                    <x-text-input id="postal_code" name="postal_code" type="text" class="mt-1 block w-full" :value="old('postal_code', $company->postal_code)" required maxlength="32" />
                                    <x-input-error class="mt-2" :messages="$errors->get('postal_code')" />
                                </div>
                            </div>

                            {{-- Standard VAT Rate Section --}}
                            @if(isset($officialTaxRate) && $officialTaxRate->standard_rate !== null)
                                {{-- Official rate exists: show read-only --}}
                                <div>
                                    <x-input-label :value="__('Official Standard VAT Rate')" />
                                    <div class="mt-1 p-3 bg-gray-50 border border-gray-300 rounded-md">
                                        <div class="flex items-center justify-between">
                                            <span class="text-lg font-semibold text-gray-900">{{ number_format($officialTaxRate->standard_rate, 2) }}%</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                Official Rate
                                            </span>
                                        </div>
                                        <div class="mt-2 text-xs text-gray-600">
                                            <div>Source: {{ $officialTaxRate->source ?? 'Unknown' }}</div>
                                            @php
                                                $lastSynced = $officialTaxRate->fetched_at ?? ($officialTaxRate->updated_at ?? null);
                                            @endphp
                                            @if($lastSynced)
                                                <div>Last synced: {{ $lastSynced->format('Y-m-d H:i') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <input type="hidden" name="default_vat_rate" value="{{ $officialTaxRate->standard_rate }}">
                                    <p class="mt-1 text-xs text-gray-500">Official rate is auto-synced and cannot be edited directly. Use override below to set a custom rate.</p>
                                </div>
                            @else
                                {{-- No official rate: show editable input --}}
                                <div>
                                    <x-input-label for="default_vat_rate" :value="__('Standard VAT Rate (Manual Fallback) (%)')" />
                                    <x-text-input id="default_vat_rate" name="default_vat_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('default_vat_rate', $company->default_vat_rate)" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('default_vat_rate')" />
                                    <p class="mt-1 text-xs text-yellow-600 font-medium">⚠️ No official VAT rate available yet for this country.</p>
                                    <p class="mt-1 text-xs text-gray-500">This rate will be used as fallback until official rate is synced.</p>
                                </div>
                            @endif

                            {{-- Override Section --}}
                            <div class="border-t pt-6 mt-6">
                                <div class="flex items-center gap-3">
                                    <x-input-label for="vat_override_enabled" :value="__('Override VAT Rate')" class="!mb-0" />
                                    <input
                                        id="vat_override_enabled"
                                        name="vat_override_enabled"
                                        type="checkbox"
                                        value="1"
                                        @checked(old('vat_override_enabled', $company->vat_override_enabled))
                                        onchange="document.getElementById('vat_override_rate_container').style.display = this.checked ? 'block' : 'none';"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    />
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Override is your responsibility; official rates are auto-synced.</p>

                                <div id="vat_override_rate_container" style="display: {{ old('vat_override_enabled', $company->vat_override_enabled) ? 'block' : 'none' }}" class="mt-4">
                                    <x-input-label for="vat_override_rate" :value="__('Override VAT Rate (%)')" />
                                    <x-text-input id="vat_override_rate" name="vat_override_rate" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" :value="old('vat_override_rate', $company->vat_override_rate)" />
                                    <x-input-error class="mt-2" :messages="$errors->get('vat_override_rate')" />
                                    <p class="mt-1 text-xs text-gray-500">Custom VAT rate (0-100%) that will override official or default rate.</p>
                                </div>
                            </div>

                            <div>
                                <x-input-label for="invoice_prefix" :value="__('Invoice Prefix')" />
                                <x-text-input id="invoice_prefix" name="invoice_prefix" type="text" class="mt-1 block w-full" :value="old('invoice_prefix', $company->invoice_prefix)" required maxlength="12" />
                                <x-input-error class="mt-2" :messages="$errors->get('invoice_prefix')" />
                                <p class="mt-1 text-xs text-gray-500">Prefix for invoice numbers (e.g., INV or INV-)</p>
                            </div>

                            <div class="flex items-center gap-4">
                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                            </div>
                        </form>
                    </section>
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
