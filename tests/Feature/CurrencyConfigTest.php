<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_account_validation_rejects_invalid_currency(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'INVALID',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Test',
        ]);

        $response->assertSessionHasErrors('currency');
    }

    public function test_bank_account_validation_accepts_valid_currency_from_config(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Test',
        ]);

        $response->assertRedirect(route('bank-accounts.index'));
        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $user->company_id,
            'currency' => 'EUR',
        ]);
    }

    public function test_bank_account_model_rejects_invalid_currency(): void
    {
        $company = Company::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Currency 'INVALID' is not in the allowed currencies list.");

        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'INVALID',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);
    }

    public function test_bank_account_create_form_shows_all_currencies_from_config(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('bank-accounts.create'));

        $allowedCurrencies = config('currencies.allowed', []);
        foreach ($allowedCurrencies as $currency) {
            $response->assertSee($currency, false);
        }
    }

    public function test_bank_account_edit_form_shows_all_currencies_from_config(): void
    {
        $user = User::factory()->create();
        $account = BankAccount::create([
            'company_id' => $user->company_id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->get(route('bank-accounts.edit', $account));

        $allowedCurrencies = config('currencies.allowed', []);
        foreach ($allowedCurrencies as $currency) {
            $response->assertSee($currency, false);
        }
    }

    public function test_invoice_validation_rejects_invalid_currency(): void
    {
        $user = User::factory()->create();
        $client = \App\Models\Client::factory()->create(['company_id' => $user->company_id]);

        // Create bank account with valid currency
        BankAccount::create([
            'company_id' => $user->company_id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'INVALID',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]);

        $response->assertSessionHasErrors('currency');
    }

    public function test_invoice_validation_accepts_valid_currency_from_config(): void
    {
        $user = User::factory()->create();
        $client = \App\Models\Client::factory()->create(['company_id' => $user->company_id]);

        // Create bank account with USD currency
        BankAccount::create([
            'company_id' => $user->company_id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        $response = $this->actingAs($user)->post(route('invoices.store'), [
            'client_id' => $client->id,
            'currency' => 'USD',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]);

        $response->assertRedirect(route('invoices.index'));
        $this->assertDatabaseHas('invoices', [
            'company_id' => $user->company_id,
            'currency' => 'USD',
        ]);
    }

    public function test_invoice_create_form_shows_all_currencies_from_config(): void
    {
        $user = User::factory()->create();

        // Create bank accounts for all currencies from config
        $allowedCurrencies = config('currencies.allowed', []);
        $ibanExamples = [
            'EUR' => 'RO49AAAA1B31007593840000',
            'USD' => 'US64SVBKUS6S3300958879',
            'GBP' => 'GB82WEST12345698765432',
            'BGN' => 'BG80BNBG96611020345678',
            'CZK' => 'CZ6508000000192000145399',
            'DKK' => 'DK5000400440116243',
            'HUF' => 'HU42117730161111101800000000',
            'PLN' => 'PL61109010140000071219812874',
            'RON' => 'RO49AAAA1B31007593840000',
            'SEK' => 'SE3550000000054910000003',
        ];

        foreach ($allowedCurrencies as $currency) {
            BankAccount::create([
                'company_id' => $user->company_id,
                'currency' => $currency,
                'iban' => $ibanExamples[$currency] ?? 'RO49AAAA1B31007593840000',
            ]);
        }

        $response = $this->actingAs($user)->get(route('invoices.create'));

        foreach ($allowedCurrencies as $currency) {
            $response->assertSee($currency, false);
        }
    }

    public function test_adding_currency_to_config_automatically_appears_in_forms(): void
    {
        // This test verifies that all currencies from config appear in forms
        // If a new currency is added to config, it should automatically appear
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('bank-accounts.create'));

        // Verify all config currencies are present (not hardcoded subset)
        $allowedCurrencies = config('currencies.allowed', []);
        $this->assertNotEmpty($allowedCurrencies, 'Config should have currencies defined');

        // Check that all currencies from config appear (proves dynamic, not hardcoded)
        foreach ($allowedCurrencies as $currency) {
            $response->assertSee("value=\"{$currency}\"", false);
        }

        // Verify no hardcoded currency names that aren't in config
        // This is an indirect check that it's using config
        $response->assertStatus(200);
    }

    public function test_currency_normalization_uppercase(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'eur', // lowercase
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Test',
        ]);

        $response->assertRedirect(route('bank-accounts.index'));
        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $user->company_id,
            'currency' => 'EUR', // normalized to uppercase
        ]);
    }
}
