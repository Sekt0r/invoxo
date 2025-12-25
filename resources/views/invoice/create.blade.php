<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New invoice</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('invoices.store') }}" class="space-y-6">
                        @csrf

                        <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}"/>

                        <div>
                            <x-input-label for="client_id" value="Client ID" />
                            <x-text-input id="client_id" name="client_id" type="number" class="mt-1 block w-full"
                                          value="{{ old('client_id') }}" required />
                            <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
                            <div class="mt-1 text-xs text-gray-500">Temporary: enter a client id. We’ll switch to dropdown next.</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="issue_date" value="Issue date (optional)" />
                                <x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full"
                                              value="{{ old('issue_date') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('issue_date')" />
                            </div>
                            <div>
                                <x-input-label for="due_date" value="Due date (optional)" />
                                <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full"
                                              value="{{ old('due_date') }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('due_date')" />
                            </div>
                        </div>

                        <div class="border-t pt-6">
                            <div class="font-semibold text-gray-800">Item 1</div>

                            <div class="mt-4">
                                <x-input-label for="items_0_description" value="Description" />
                                <x-text-input id="items_0_description" name="items[0][description]" type="text" class="mt-1 block w-full"
                                              value="{{ old('items.0.description') }}" required />
                                <x-input-error class="mt-2" :messages="$errors->get('items.0.description')" />
                            </div>

                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="items_0_quantity" value="Quantity" />
                                    <x-text-input id="items_0_quantity" name="items[0][quantity]" type="number" step="0.01" class="mt-1 block w-full"
                                                  value="{{ old('items.0.quantity', 1) }}" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('items.0.quantity')" />
                                </div>

                                <div>
                                    <x-input-label for="items_0_unit_price_minor" value="Unit price (cents)" />
                                    <x-text-input id="items_0_unit_price_minor" name="items[0][unit_price_minor]" type="number" class="mt-1 block w-full"
                                                  value="{{ old('items.0.unit_price_minor', 0) }}" required />
                                    <x-input-error class="mt-2" :messages="$errors->get('items.0.unit_price_minor')" />
                                </div>

                                <div class="text-sm text-gray-600 flex items-end">
                                    EUR only (MVP)
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-700 hover:underline">Back</a>
                            <x-primary-button>Create draft</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
