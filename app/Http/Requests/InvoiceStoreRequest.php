<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Filter out completely empty item rows (description, quantity, and unit_price all empty)
        if ($this->has('items') && is_array($this->items)) {
            $filteredItems = [];
            foreach ($this->items as $item) {
                $description = trim($item['description'] ?? '');
                $quantity = $item['quantity'] ?? '';
                $unitPrice = $item['unit_price'] ?? '';

                // Keep row if at least one field is filled
                if ($description !== '' || $quantity !== '' || $unitPrice !== '') {
                    $filteredItems[] = $item;
                }
            }
            $this->merge(['items' => $filteredItems]);
        }
    }

    public function rules(): array
    {
        $company = $this->user()->company;
        $bankAccounts = $company->bankAccounts;
        $allowedCurrencies = $bankAccounts->pluck('currency')->unique()->all();

        $currencyRules = [];
        if (empty($allowedCurrencies)) {
            // No bank accounts: currency must be null
            $currencyRules = ['nullable', 'string'];
        } else {
            // Has bank accounts: currency must be in allowed currencies
            $currencyRules = ['required', 'string', 'size:3', Rule::in($allowedCurrencies)];
        }

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $this->user()->company_id)),
            ],
            'currency' => $currencyRules,
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],

            'bank_account_id' => ['nullable', 'integer'],
        ];
    }
}
