<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', 'in:'.implode(',', array_keys(config('countries')))],
            'vat_id' => ['nullable', 'string', 'max:32'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'tax_identifier' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure country_code is uppercase
        if ($this->has('country_code')) {
            $this->merge([
                'country_code' => strtoupper($this->input('country_code')),
            ]);
        }

        // Trim all string fields
        $stringFields = ['name', 'vat_id', 'registration_number', 'tax_identifier', 'address_line1', 'address_line2', 'city', 'postal_code'];
        foreach ($stringFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                if (is_string($value)) {
                    $this->merge([$field => trim($value)]);
                }
            }
        }
    }
}
