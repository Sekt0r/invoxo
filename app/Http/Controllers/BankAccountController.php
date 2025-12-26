<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\BankAccountEvent;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    /**
     * Display a listing of bank accounts for the company.
     */
    public function index(Request $request): View
    {
        $companyId = auth()->user()->company_id;
        $bankAccounts = BankAccount::where('company_id', $companyId)
            ->orderBy('currency')
            ->orderBy('nickname')
            ->get();

        return view('bank-account.index', [
            'bankAccounts' => $bankAccounts,
        ]);
    }

    /**
     * Show the form for creating a new bank account.
     */
    public function create(): View
    {
        return view('bank-account.create');
    }

    /**
     * Store a newly created bank account.
     */
    public function store(Request $request): RedirectResponse
    {
        // Normalize currency before validation
        if ($request->has('currency')) {
            $request->merge(['currency' => strtoupper(trim($request->input('currency')))]);
        }

        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', Rule::in(config('currencies.allowed', []))],
            'iban' => ['required', 'string', 'max:34'],
            'nickname' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate IBAN format
        if (!BankAccount::isValidIbanFormat($validated['iban'])) {
            return redirect()->back()
                ->withErrors(['iban' => 'Invalid IBAN format. Expected: 2 letters (country code) + 2 digits + up to 30 alphanumeric characters.'])
                ->withInput();
        }

        // Check for duplicate (company_id, currency, iban)
        $companyId = auth()->user()->company_id;
        $existing = BankAccount::where('company_id', $companyId)
            ->where('currency', $validated['currency']) // Already normalized
            ->where('iban', strtoupper(str_replace(' ', '', $validated['iban'])))
            ->first();

        if ($existing) {
            return redirect()->back()
                ->withErrors(['iban' => 'A bank account with this IBAN and currency already exists.'])
                ->withInput();
        }

        $bankAccount = DB::transaction(function () use ($validated, $companyId) {
            $bankAccount = BankAccount::create([
                'company_id' => $companyId,
                'currency' => $validated['currency'], // Already normalized
                'iban' => $validated['iban'],
                'nickname' => $validated['nickname'] ? trim($validated['nickname']) : null,
                'is_default' => false, // Will be set below if requested
            ]);

            // If this should be default, set it (which will unset others)
            if (!empty($validated['is_default'])) {
                $bankAccount->setAsDefault();
            }

            return $bankAccount;
        });

        return redirect()->route('bank-accounts.index')
            ->with('status', 'created');
    }

    /**
     * Show the form for editing the specified bank account.
     */
    public function edit(Request $request, BankAccount $bankAccount): View
    {
        if ((int)$bankAccount->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        return view('bank-account.edit', [
            'bankAccount' => $bankAccount,
        ]);
    }

    /**
     * Update the specified bank account.
     */
    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        if ((int)$bankAccount->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        // Normalize currency before validation
        if ($request->has('currency')) {
            $request->merge(['currency' => strtoupper(trim($request->input('currency')))]);
        }

        $validated = $request->validate([
            'currency' => ['required', 'string', 'size:3', Rule::in(config('currencies.allowed', []))],
            'iban' => ['required', 'string', 'max:34'],
            'nickname' => ['nullable', 'string', 'max:255'],
        ]);

        // Validate IBAN format
        if (!BankAccount::isValidIbanFormat($validated['iban'])) {
            return redirect()->back()
                ->withErrors(['iban' => 'Invalid IBAN format. Expected: 2 letters (country code) + 2 digits + up to 30 alphanumeric characters.'])
                ->withInput();
        }

        // Check for duplicate (excluding current account)
        $companyId = auth()->user()->company_id;
        $normalizedIban = strtoupper(str_replace(' ', '', $validated['iban']));
        $existing = BankAccount::where('company_id', $companyId)
            ->where('currency', $validated['currency'])
            ->where('iban', $normalizedIban)
            ->where('id', '!=', $bankAccount->id)
            ->first();

        if ($existing) {
            return redirect()->back()
                ->withErrors(['iban' => 'A bank account with this IBAN and currency already exists.'])
                ->withInput();
        }

        DB::transaction(function () use ($bankAccount, $validated) {
            $bankAccount->update([
                'currency' => $validated['currency'], // Already normalized
                'iban' => $validated['iban'],
                'nickname' => $validated['nickname'] ? trim($validated['nickname']) : null,
            ]);

            // Handle default setting
            if (!empty($validated['is_default'])) {
                $bankAccount->setAsDefault();
            } elseif (empty($validated['is_default']) && $bankAccount->is_default) {
                // If unchecking default and this was the default, we need to clear it
                // But don't allow clearing if it's the only account
                $otherAccounts = BankAccount::where('company_id', $bankAccount->company_id)
                    ->where('id', '!=', $bankAccount->id)
                    ->count();
                if ($otherAccounts > 0) {
                    $bankAccount->is_default = false;
                    $bankAccount->save();
                }
            }
        });

        return redirect()->route('bank-accounts.index')
            ->with('status', 'updated');
    }

    /**
     * Remove (soft delete) the specified bank account.
     */
    public function destroy(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        if ((int)$bankAccount->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        $companyId = auth()->user()->company_id;

        // Prevent deleting if it's the last remaining bank account for the company
        // (only if issuance gate requires at least one bank account)
        $remainingCount = BankAccount::where('company_id', $companyId)
            ->where('id', '!=', $bankAccount->id)
            ->count();

        if ($remainingCount === 0) {
            return redirect()->route('bank-accounts.index')
                ->withErrors(['error' => 'Cannot delete the last remaining bank account. Add another bank account first.']);
        }

        // Soft delete - observer will create audit event
        $bankAccount->delete();

        return redirect()->route('bank-accounts.index')
            ->with('status', 'deleted');
    }

    /**
     * Restore a soft-deleted bank account.
     * Note: Route model binding excludes soft-deleted models by default, so we use ID.
     */
    public function restore(Request $request, int $bank_account): RedirectResponse
    {
        // Find the bank account including soft-deleted ones
        $bankAccount = BankAccount::withTrashed()->findOrFail($bank_account);

        if ((int)$bankAccount->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        // Ensure it's actually soft deleted
        if (!$bankAccount->trashed()) {
            return redirect()->route('bank-accounts.index')
                ->withErrors(['error' => 'Bank account is not deleted.']);
        }

        // Restore - observer will create audit event
        $bankAccount->restore();

        return redirect()->route('bank-accounts.index')
            ->with('status', 'restored');
    }

    /**
     * Set a bank account as the default for the company.
     */
    public function setDefault(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        if ((int)$bankAccount->company_id !== (int)auth()->user()->company_id) {
            abort(403);
        }

        $bankAccount->setAsDefault();

        return redirect()->route('bank-accounts.index')
            ->with('status', 'default-set');
    }
}
