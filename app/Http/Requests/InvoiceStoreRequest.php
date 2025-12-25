<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        return $user !== null && (int) $this->input('company_id') === (int) $user->company_id;
    }


    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price_minor' => ['required', 'integer', 'min:0'],
        ];
    }

}
