<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'public_id' => ['required', 'unique:invoices,public_id'],
            'share_token' => ['nullable', 'string', 'max:64'],
            'number' => ['nullable', 'string'],
            'status' => ['required', 'string'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_treatment' => ['required', 'string'],
            'vat_rate' => ['required', 'numeric', 'between:-999.99,999.99'],
            'vat_reason_text' => ['nullable', 'string'],
            'subtotal_minor' => ['required', 'integer'],
            'vat_minor' => ['required', 'integer'],
            'total_minor' => ['required', 'integer'],
        ];
    }
}
