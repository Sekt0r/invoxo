<?php

namespace App\Http\Controllers;

use App\Jobs\RecomputeDraftInvoicesForCompanyJob;
use App\Models\TaxRate;
use App\Services\VatRateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanySettingsController extends Controller
{
    public function edit(Request $request, VatRateResolver $vatRateResolver): View
    {
        $company = $request->user()->company;
        $company->load('vatIdentity');

        $resolvedRate = $vatRateResolver->resolve($company->country_code, $company);

        // Check if official tax rate exists for company's country
        $officialTaxRate = TaxRate::where('country_code', strtoupper($company->country_code))->first();

        return view('settings.company', [
            'company' => $company,
            'resolvedRate' => $resolvedRate,
            'officialTaxRate' => $officialTaxRate,
            'identityLabels' => config('company_identity_labels', []),
            'currencies' => config('currencies.allowed', []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        // Capture VAT-relevant fields before updating (for comparison)
        $vatRelevantFields = ['country_code', 'vat_id', 'vat_override_enabled', 'vat_override_rate', 'default_vat_rate'];
        $before = $company->only($vatRelevantFields);

        // Capture old country code before updating
        $oldCountry = $company->country_code;

        // Normalize country_code: uppercase and trim
        $request->merge(['country_code' => strtoupper(trim($request->input('country_code', '')))]);

        // Check if official tax rate exists for the selected country
        $officialTaxRate = TaxRate::where('country_code', strtoupper($request->input('country_code', '')))->first();
        $hasOfficialRate = $officialTaxRate !== null && $officialTaxRate->standard_rate !== null;

        // Build validation rules
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', 'in:'.implode(',', array_keys(config('countries')))],
            'registration_number' => ['required', 'string', 'max:255'],
            'tax_identifier' => ['required', 'string', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:32'],
            'vat_id' => ['nullable', 'string', 'max:32'],
            'vat_override_enabled' => ['nullable', 'boolean'],
            'invoice_prefix' => ['required', 'string', 'max:12'],
        ];

        // default_vat_rate is ALWAYS required and editable (represents country VAT rate)
        $rules['default_vat_rate'] = ['required', 'numeric', 'between:0,100'];

        // Validate override rate when override is enabled
        if ($request->has('vat_override_enabled') && $request->input('vat_override_enabled')) {
            $rules['vat_override_rate'] = ['required', 'numeric', 'between:0,100'];
        }

        $validated = $request->validate($rules);

        // Normalize all string fields: trim
        $stringFields = ['registration_number', 'tax_identifier', 'address_line1', 'address_line2', 'city', 'postal_code', 'invoice_prefix'];
        foreach ($stringFields as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim($validated[$field]);
            }
        }

        // default_vat_rate is always editable (user-provided, represents country VAT rate)
        // If not provided in request, preserve existing
        if (!isset($validated['default_vat_rate'])) {
            $validated['default_vat_rate'] = $company->default_vat_rate;
        }

        // Handle vat_override fields (preserve existing values if not provided)
        if (!isset($validated['vat_override_enabled'])) {
            $validated['vat_override_enabled'] = $company->vat_override_enabled ?? false;
        } else {
            $validated['vat_override_enabled'] = (bool)$validated['vat_override_enabled'];
        }

        // If override is disabled, clear override_rate
        if (!$validated['vat_override_enabled']) {
            $validated['vat_override_rate'] = null;
        } elseif (!isset($validated['vat_override_rate'])) {
            // If enabled but rate not provided, preserve existing rate
            $validated['vat_override_rate'] = $company->vat_override_rate;
        }

        // Detect country change (compare with current value, not after fill)
        $countryChanged = $oldCountry !== $validated['country_code'];

        if ($countryChanged) {
            // Re-check official rate for new country (we already have it in $officialTaxRate, but refresh)
            $newCountryOfficialRate = TaxRate::where('country_code', strtoupper($validated['country_code']))->first();
            $newCountryHasOfficialRate = $newCountryOfficialRate !== null && $newCountryOfficialRate->standard_rate !== null;

            // default_vat_rate is always user-editable, don't auto-update on country change
            // User can manually update it if needed
            if ($validated['vat_override_enabled']) {
                // Override enabled: set session flags for UI banner
                session()->flash('company.override_country_changed', true);
                session()->flash('company.override_country_changed_from', $oldCountry);
                session()->flash('company.override_country_changed_to', $validated['country_code']);
                // Do NOT change vat_override_rate or disable override
            }
        }

        $company->update($validated);
        // Observer will handle VAT identity linking/validation automatically

        // Check if any VAT-relevant fields changed
        $company->refresh();
        $after = $company->only($vatRelevantFields);

        // Normalize comparison (handle null/empty string differences, float precision)
        $vatChanged = false;
        foreach ($vatRelevantFields as $field) {
            $beforeValue = $before[$field] ?? null;
            $afterValue = $after[$field] ?? null;

            // Normalize for comparison
            if (in_array($field, ['vat_override_rate', 'default_vat_rate'])) {
                $beforeValue = $beforeValue !== null ? (float)$beforeValue : null;
                $afterValue = $afterValue !== null ? (float)$afterValue : null;
            } elseif ($field === 'vat_override_enabled') {
                $beforeValue = (bool)($beforeValue ?? false);
                $afterValue = (bool)($afterValue ?? false);
            } else {
                // country_code, vat_id: string comparison
                $beforeValue = $beforeValue !== null ? (string)$beforeValue : null;
                $afterValue = $afterValue !== null ? (string)$afterValue : null;
            }

            if ($beforeValue !== $afterValue) {
                $vatChanged = true;
                break;
            }
        }

        // Dispatch job to recompute draft invoices if VAT-relevant fields changed
        if ($vatChanged) {
            RecomputeDraftInvoicesForCompanyJob::dispatch($company->id);
        }

        return redirect()->route('settings.company.edit')
            ->with('status', 'saved');
    }

    public function overrideDecision(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        $decision = $request->input('decision'); // 'keep' or 'disable'

        if ($decision === 'disable') {
            // Capture before state for VAT change detection
            $vatChanged = $company->vat_override_enabled || $company->vat_override_rate !== null;

            // Disable override and set default_vat_rate to official standard rate if available
            $taxRate = TaxRate::where('country_code', $company->country_code)->first();

            $company->vat_override_enabled = false;
            $company->vat_override_rate = null;

            if ($taxRate && $taxRate->standard_rate !== null) {
                $oldDefaultRate = $company->default_vat_rate;
                $company->default_vat_rate = (float)$taxRate->standard_rate;
                // Also consider default_vat_rate change as VAT-relevant
                $vatChanged = $vatChanged || ((float)$oldDefaultRate !== (float)$company->default_vat_rate);
            }

            $company->save();

            // Dispatch job to recompute draft invoices if VAT-relevant fields changed
            if ($vatChanged) {
                RecomputeDraftInvoicesForCompanyJob::dispatch($company->id);
            }
        }
        // If 'keep', no changes needed (override stays enabled)

        // Clear session flags
        session()->forget([
            'company.override_country_changed',
            'company.override_country_changed_from',
            'company.override_country_changed_to',
        ]);

        return redirect()->route('settings.company.edit')
            ->with('status', 'saved');
    }
}
