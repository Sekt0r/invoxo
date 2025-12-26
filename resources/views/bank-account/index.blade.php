<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bank Accounts') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <section>
                        <header>
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-medium text-gray-900">
                                        {{ __('Bank Accounts') }}
                                    </h2>
                                    <p class="mt-1 text-sm text-gray-600">
                                        {{ __('Manage bank accounts for invoice payments.') }}
                                    </p>
                                </div>
                                <a href="{{ route('bank-accounts.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                    Add Account
                                </a>
                            </div>
                        </header>

                        @if (session('status') === 'created')
                            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                                <p class="text-sm text-green-800">Bank account created successfully.</p>
                            </div>
                        @endif

                        @if (session('status') === 'updated')
                            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                                <p class="text-sm text-green-800">Bank account updated successfully.</p>
                            </div>
                        @endif

                        @if (session('status') === 'deleted')
                            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                                <p class="text-sm text-green-800">Bank account deleted successfully.</p>
                            </div>
                        @endif

                        @if (session('status') === 'default-set')
                            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                                <p class="text-sm text-green-800">Default bank account updated successfully.</p>
                            </div>
                        @endif

                        @if($bankAccounts->isEmpty())
                            <div class="mt-6 text-center py-8">
                                <p class="text-sm text-gray-500">No bank accounts configured yet.</p>
                                <a href="{{ route('bank-accounts.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                    Add Your First Bank Account
                                </a>
                            </div>
                        @else
                            <div class="mt-6 space-y-3">
                                @foreach($bankAccounts as $account)
                                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-md">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <div class="font-medium text-gray-900">{{ $account->display_name }}</div>
                                                @if($account->is_default)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                        Default
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-gray-600 mt-1">{{ $account->currency }} • {{ $account->iban }}</div>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            @if(!$account->is_default)
                                                <form method="POST" action="{{ route('bank-accounts.set-default', $account) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">Make default</button>
                                                </form>
                                            @endif
                                            <a href="{{ route('bank-accounts.edit', $account) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Edit</a>
                                            <form method="POST" action="{{ route('bank-accounts.destroy', $account) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this bank account?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-6">
                            <a href="{{ route('settings.company.edit') }}" class="text-sm text-gray-700 hover:underline">← Back to Company Settings</a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
