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

                        <div>
                            <x-input-label for="client_id" value="Client" />
                            <select id="client_id" name="client_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="">Select a client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
                        </div>

                        {{-- VAT Preview Panel --}}
                        <div id="vat-preview-panel" class="p-4 bg-gray-50 border border-gray-300 rounded-md" style="display: none;">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">VAT Preview</h3>
                            <div id="vat-preview-content">
                                <div class="space-y-2 text-sm">
                                    <div>
                                        <span class="font-medium text-gray-600">Tax Treatment:</span>
                                        <span id="vat-preview-treatment" class="ml-2 text-gray-900"></span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-600">VAT Rate:</span>
                                        <span id="vat-preview-rate" class="ml-2 text-gray-900"></span>
                                    </div>
                                    <div id="vat-preview-reason" style="display: none;">
                                        <span class="font-medium text-gray-600">Reason:</span>
                                        <span id="vat-preview-reason-text" class="ml-2 text-gray-900"></span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-600">Client VAT Status:</span>
                                        <span id="vat-preview-client-status" class="ml-2"></span>
                                    </div>
                                    <div id="vat-preview-block" class="mt-3 p-2 bg-yellow-100 border border-yellow-300 rounded text-xs text-yellow-800" style="display: none;">
                                        <strong>⚠️ Warning:</strong> <span id="vat-preview-block-reason"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="vat-preview-placeholder" class="p-4 bg-gray-50 border border-gray-300 rounded-md text-sm text-gray-500">
                            Select a client to see VAT treatment.
                        </div>

                        @if(!$hasBankAccounts)
                            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                                <p class="text-sm text-yellow-800 font-medium">
                                    No bank accounts configured. Add a bank account to issue invoices.
                                </p>
                                <a href="{{ route('bank-accounts.create') }}" class="mt-2 inline-block text-sm text-yellow-900 underline">Add bank account</a>
                            </div>
                        @else
                            <div>
                                <x-input-label for="currency" value="Currency" />
                                <select id="currency" name="currency" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    @foreach ($allowedCurrencies as $currencyCode)
                                        <option value="{{ $currencyCode }}" @selected(old('currency', $defaultCurrency) === $currencyCode)>
                                            {{ $currencyCode }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error class="mt-2" :messages="$errors->get('currency')" />
                            </div>

                            {{-- Payment Account Selection --}}
                            <div id="payment-accounts-section">
                                <x-input-label value="Payment Accounts" />
                                <div id="payment-accounts-list" class="mt-2 space-y-2">
                                    {{-- Will be populated by JavaScript based on selected currency --}}
                                </div>
                            </div>
                        @endif

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
                            <div class="flex items-center justify-between mb-4">
                                <div class="font-semibold text-gray-800">Items</div>
                                <button type="button" id="add-item-btn" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                    + Add item
                                </button>
                            </div>

                            <div class="overflow-x-auto">
                                <table id="items-table" class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Quantity</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Unit price</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Subtotal</th>
                                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-tbody" class="bg-white divide-y divide-gray-200">
                                        <tr class="item-row" data-row-index="0">
                                            <td class="px-3 py-3">
                                                <input type="text" name="items[0][description]" class="item-description block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" value="{{ old('items.0.description') }}" required />
                                                <x-input-error class="mt-1" :messages="$errors->get('items.0.description')" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input type="number" name="items[0][quantity]" step="0.01" min="0.01" class="item-quantity block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" value="{{ old('items.0.quantity', 1) }}" required />
                                                <x-input-error class="mt-1" :messages="$errors->get('items.0.quantity')" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <input type="number" name="items[0][unit_price]" step="0.01" min="0" class="item-unit-price block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" value="{{ old('items.0.unit_price', 0) }}" required />
                                                <x-input-error class="mt-1" :messages="$errors->get('items.0.unit_price')" />
                                            </td>
                                            <td class="px-3 py-3">
                                                <span class="item-line-subtotal text-sm text-gray-900 font-medium">0.00</span>
                                            </td>
                                            <td class="px-3 py-3 text-center">
                                                <button type="button" class="remove-item-btn text-red-600 hover:text-red-800 text-sm font-medium" disabled style="display: none;">Remove</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <x-input-error class="mt-2" :messages="$errors->get('items')" />

                            <div class="mt-6 border-t pt-4">
                                <div class="flex justify-end">
                                    <div class="w-64 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Subtotal:</span>
                                            <span id="invoice-subtotal" class="font-medium text-gray-900">0.00</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">VAT (<span id="vat-rate-display">0</span>%):</span>
                                            <span id="invoice-vat" class="font-medium text-gray-900">0.00</span>
                                        </div>
                                        <div class="flex justify-between text-base font-semibold border-t pt-2">
                                            <span class="text-gray-800">Total:</span>
                                            <span id="invoice-total" class="text-gray-900">0.00</span>
                                        </div>
                                        <div id="vat-pending-msg" class="text-xs text-yellow-600" style="display: none;">
                                            VAT pending - select a client
                                        </div>
                                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientSelect = document.getElementById('client_id');
    const currencySelect = document.getElementById('currency');
    const paymentAccountsList = document.getElementById('payment-accounts-list');
    const paymentAccountsSection = document.getElementById('payment-accounts-section');
    const previewPanel = document.getElementById('vat-preview-panel');
    const previewPlaceholder = document.getElementById('vat-preview-placeholder');
    const previewUrl = '{{ route('invoices.vat-preview') }}';
    const itemsTbody = document.getElementById('items-tbody');
    const addItemBtn = document.getElementById('add-item-btn');
    const bankAccounts = @json($bankAccounts ?? []);
    let currentVatRate = 0;
    let rowIndexCounter = 1;

    // Update payment accounts list based on selected currency
    function updatePaymentAccounts() {
        if (!currencySelect || !paymentAccountsList) return;

        const selectedCurrency = currencySelect.value;
        const matchingAccounts = bankAccounts.filter(acc => acc.currency === selectedCurrency);

        paymentAccountsList.innerHTML = '';

        if (matchingAccounts.length === 0) {
            paymentAccountsList.innerHTML = '<p class="text-sm text-gray-500">No bank accounts found for this currency.</p>';
            return;
        }

        matchingAccounts.forEach(account => {
            const accountDiv = document.createElement('div');
            accountDiv.className = 'p-3 bg-gray-50 border border-gray-200 rounded-md';
            accountDiv.innerHTML = `
                <div class="font-medium text-gray-900">${account.display_name}</div>
                <div class="text-sm text-gray-600 mt-1">${account.iban}</div>
            `;
            paymentAccountsList.appendChild(accountDiv);
        });
    }

    // Update payment accounts when currency changes
    if (currencySelect) {
        currencySelect.addEventListener('change', updatePaymentAccounts);
        // Initial update
        updatePaymentAccounts();
    }

    // Format number to 2 decimals
    function formatCurrency(value) {
        return parseFloat(value || 0).toFixed(2);
    }

    // Calculate totals
    function calculateTotals() {
        const rows = itemsTbody.querySelectorAll('.item-row');
        let subtotal = 0;

        rows.forEach(row => {
            const quantity = parseFloat(row.querySelector('.item-quantity').value || 0);
            const unitPrice = parseFloat(row.querySelector('.item-unit-price').value || 0);
            const lineSubtotal = quantity * unitPrice;

            row.querySelector('.item-line-subtotal').textContent = formatCurrency(lineSubtotal);
            subtotal += lineSubtotal;
        });

        const vat = currentVatRate > 0 ? subtotal * (currentVatRate / 100) : 0;
        const total = subtotal + vat;

        document.getElementById('invoice-subtotal').textContent = formatCurrency(subtotal);
        document.getElementById('invoice-vat').textContent = formatCurrency(vat);
        document.getElementById('invoice-total').textContent = formatCurrency(total);
    }

    // Add item row
    function addItemRow() {
        const row = document.createElement('tr');
        row.className = 'item-row';
        row.setAttribute('data-row-index', rowIndexCounter);

        row.innerHTML = `
            <td class="px-3 py-3">
                <input type="text" name="items[${rowIndexCounter}][description]" class="item-description block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            </td>
            <td class="px-3 py-3">
                <input type="number" name="items[${rowIndexCounter}][quantity]" step="0.01" min="0.01" class="item-quantity block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" value="1" />
            </td>
            <td class="px-3 py-3">
                <input type="number" name="items[${rowIndexCounter}][unit_price]" step="0.01" min="0" class="item-unit-price block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" value="0" />
            </td>
            <td class="px-3 py-3">
                <span class="item-line-subtotal text-sm text-gray-900 font-medium">0.00</span>
            </td>
            <td class="px-3 py-3 text-center">
                <button type="button" class="remove-item-btn text-red-600 hover:text-red-800 text-sm font-medium">Remove</button>
            </td>
        `;

        itemsTbody.appendChild(row);
        rowIndexCounter++;

        // Attach event listeners to new row
        attachItemListeners(row);
        updateRemoveButtons();
    }

    // Remove item row
    function removeItemRow(btn) {
        const row = btn.closest('.item-row');
        row.remove();
        calculateTotals();
        updateRemoveButtons();
    }

    // Update remove buttons (disable when only one row)
    function updateRemoveButtons() {
        const rows = itemsTbody.querySelectorAll('.item-row');
        const removeBtns = itemsTbody.querySelectorAll('.remove-item-btn');

        removeBtns.forEach((btn, index) => {
            if (rows.length === 1) {
                btn.disabled = true;
                btn.style.display = 'none';
            } else {
                btn.disabled = false;
                btn.style.display = 'inline';
            }
        });
    }

    // Attach event listeners to item row
    function attachItemListeners(row) {
        const quantityInput = row.querySelector('.item-quantity');
        const unitPriceInput = row.querySelector('.item-unit-price');
        const removeBtn = row.querySelector('.remove-item-btn');

        quantityInput.addEventListener('input', calculateTotals);
        unitPriceInput.addEventListener('input', calculateTotals);
        removeBtn.addEventListener('click', () => removeItemRow(removeBtn));
    }

    // Attach listeners to existing rows
    itemsTbody.querySelectorAll('.item-row').forEach(attachItemListeners);
    updateRemoveButtons();

    // Add item button
    addItemBtn.addEventListener('click', addItemRow);

    // Initial calculation
    calculateTotals();

    // VAT Preview functionality
    function updateVatPreview(clientId) {
        if (!clientId) {
            previewPanel.style.display = 'none';
            previewPlaceholder.style.display = 'block';
            currentVatRate = 0;
            document.getElementById('vat-rate-display').textContent = '0';
            document.getElementById('vat-pending-msg').style.display = 'block';
            calculateTotals();
            return;
        }

        fetch(previewUrl + '?client_id=' + clientId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Unable to compute VAT preview');
                }
                return response.json();
            })
            .then(data => {
                document.getElementById('vat-preview-treatment').textContent = data.tax_treatment;
                document.getElementById('vat-preview-rate').textContent = data.vat_rate + '%';

                const reasonDiv = document.getElementById('vat-preview-reason');
                if (data.reason_text) {
                    document.getElementById('vat-preview-reason-text').textContent = data.reason_text;
                    reasonDiv.style.display = 'block';
                } else {
                    reasonDiv.style.display = 'none';
                }

                const statusSpan = document.getElementById('vat-preview-client-status');
                const statusColors = {
                    'valid': 'text-green-700 bg-green-100',
                    'invalid': 'text-red-700 bg-red-100',
                    'pending': 'text-yellow-700 bg-yellow-100',
                    'unknown': 'text-gray-700 bg-gray-100'
                };
                const statusClass = statusColors[data.client_vat_status] || 'text-gray-700 bg-gray-100';
                statusSpan.textContent = data.client_vat_status.charAt(0).toUpperCase() + data.client_vat_status.slice(1);
                statusSpan.className = 'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ' + statusClass;

                const blockDiv = document.getElementById('vat-preview-block');
                if (!data.can_issue && data.block_reason) {
                    document.getElementById('vat-preview-block-reason').textContent = data.block_reason;
                    blockDiv.style.display = 'block';
                } else {
                    blockDiv.style.display = 'none';
                }

                previewPlaceholder.style.display = 'none';
                previewPanel.style.display = 'block';

                // Update VAT rate for calculations
                currentVatRate = parseFloat(data.vat_rate || 0);
                document.getElementById('vat-rate-display').textContent = currentVatRate.toFixed(2);
                document.getElementById('vat-pending-msg').style.display = 'none';
                calculateTotals();
            })
            .catch(error => {
                console.error('Error fetching VAT preview:', error);
                previewPlaceholder.textContent = 'Unable to compute VAT preview';
                previewPlaceholder.style.display = 'block';
                previewPanel.style.display = 'none';
                currentVatRate = 0;
                document.getElementById('vat-rate-display').textContent = '0';
                document.getElementById('vat-pending-msg').style.display = 'block';
                calculateTotals();
            });
    }

    clientSelect.addEventListener('change', function() {
        updateVatPreview(this.value);
    });

    // Load preview on page load if client is already selected
    const initialClientId = clientSelect.value;
    if (initialClientId) {
        updateVatPreview(initialClientId);
    }
});
</script>
