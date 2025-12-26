<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Bank Account') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">
                                {{ __('Edit Bank Account') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-600">
                                {{ __('Update bank account details.') }}
                            </p>
                        </header>

                        <form method="POST" action="{{ route('bank-accounts.update', $bankAccount) }}" class="mt-6 space-y-6">
                            @csrf
                            @method('PUT')

                            <div>
                                <x-input-label for="currency" :value="__('Currency')" />
                                <select id="currency" name="currency" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm uppercase" required>
                                    <option value="">Select currency</option>
                                    @foreach(config('currencies.allowed', []) as $currencyCode)
                                        <option value="{{ $currencyCode }}" @selected(old('currency', $bankAccount->currency) === $currencyCode)>
                                            {{ $currencyCode }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('currency')" />
                            </div>

                            <div>
                                <x-input-label for="iban" :value="__('IBAN')" />
                                <x-text-input id="iban" name="iban" type="text" class="mt-1 block w-full uppercase" :value="old('iban', $bankAccount->iban)" required maxlength="34" />
                                <x-input-error class="mt-2" :messages="$errors->get('iban')" />
                                <p class="mt-1 text-xs text-gray-500">International Bank Account Number (e.g., RO49 AAAA 1B31 0075 9384 0000)</p>
                            </div>

                            <div>
                                <x-input-label for="nickname" :value="__('Nickname (Optional)')" />
                                <x-text-input id="nickname" name="nickname" type="text" class="mt-1 block w-full" :value="old('nickname', $bankAccount->nickname)" maxlength="255" />
                                <x-input-error class="mt-2" :messages="$errors->get('nickname')" />
                                <p class="mt-1 text-xs text-gray-500">Optional friendly name (e.g., "Main Account", "EUR Account")</p>
                            </div>

                            <div class="flex items-center">
                                <input id="is_default" name="is_default" type="checkbox" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_default', $bankAccount->is_default))>
                                <x-input-label for="is_default" :value="__('Make default')" class="ml-2 !mb-0" />
                                <x-input-error class="mt-2" :messages="$errors->get('is_default')" />
                            </div>
                            <p class="text-xs text-gray-500">This account will be used as the default for new invoices.</p>

                            <div class="flex items-center gap-4">
                                <a href="{{ route('bank-accounts.index') }}" class="text-sm text-gray-700 hover:underline">Cancel</a>
                                <x-primary-button>{{ __('Update Bank Account') }}</x-primary-button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

