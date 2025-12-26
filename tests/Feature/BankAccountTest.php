<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankAccountEvent;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_bank_account(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $response = $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'EUR',
            'iban' => 'RO49 AAAA 1B31 0075 9384 0000',
            'nickname' => 'Main Account',
        ]);

        $response->assertRedirect(route('bank-accounts.index'));

        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Main Account',
        ]);
    }

    public function test_can_update_bank_account(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Old Nickname',
        ]);

        $response = $this->actingAs($user)->put(route('bank-accounts.update', $account), [
            'currency' => 'USD',
            'iban' => 'RO49BBBB2B31007593840001',
            'nickname' => 'New Nickname',
        ]);

        $response->assertRedirect(route('bank-accounts.index'));

        $account->refresh();
        $this->assertEquals('USD', $account->currency);
        $this->assertEquals('RO49BBBB2B31007593840001', $account->iban);
        $this->assertEquals('New Nickname', $account->nickname);
    }

    public function test_can_delete_bank_account(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Create two accounts so we can delete one (last account deletion is prevented)
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->delete(route('bank-accounts.destroy', $account));

        $response->assertRedirect(route('bank-accounts.index'));

        // Assert soft deleted (not hard deleted)
        $this->assertSoftDeleted('bank_accounts', [
            'id' => $account->id,
        ]);

        // Assert account still exists in database
        $this->assertDatabaseHas('bank_accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_bank_account_create_audits_event(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Main Account',
        ]);

        $account = BankAccount::where('company_id', $company->id)->first();

        $this->assertDatabaseHas('bank_account_events', [
            'company_id' => $company->id,
            'bank_account_id' => $account->id,
            'user_id' => $user->id,
            'action' => 'created',
        ]);

        $event = BankAccountEvent::where('bank_account_id', $account->id)->first();
        $this->assertNotNull($event->new_values);
        $this->assertNull($event->old_values);
    }

    public function test_bank_account_update_audits_event(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Old',
        ]);

        $this->actingAs($user)->put(route('bank-accounts.update', $account), [
            'currency' => 'USD',
            'iban' => 'RO49BBBB2B31007593840001',
            'nickname' => 'New',
        ]);

        $event = BankAccountEvent::where('bank_account_id', $account->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals($user->id, $event->user_id);
        $this->assertNotNull($event->old_values);
        $this->assertNotNull($event->new_values);
    }

    public function test_bank_account_delete_audits_event(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Create two accounts so we can delete one (last account deletion is prevented)
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $accountId = $account->id;

        $response = $this->actingAs($user)->delete(route('bank-accounts.destroy', $account));

        // Assert bank account is soft deleted
        $this->assertSoftDeleted('bank_accounts', ['id' => $accountId]);

        // Assert audit event was created
        $event = BankAccountEvent::where('bank_account_id', $accountId)
            ->where('action', 'deleted')
            ->first();

        $this->assertNotNull($event, 'Bank account event should be created on delete');
        $this->assertEquals($user->id, $event->user_id);
        $this->assertNotNull($event->old_values);
        $this->assertNull($event->new_values);
        $this->assertNotNull($event->ip_address, 'IP address should be captured');
    }

    public function test_bank_accounts_are_company_scoped(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $account1 = BankAccount::create([
            'company_id' => $user1->company_id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        // User2 cannot access User1's bank account
        $response = $this->actingAs($user2)->get(route('bank-accounts.edit', $account1));
        $this->assertEquals(403, $response->status());

        $response = $this->actingAs($user2)->put(route('bank-accounts.update', $account1), [
            'currency' => 'USD',
            'iban' => 'RO49BBBB2B31007593840001',
        ]);
        $this->assertEquals(403, $response->status());

        $response = $this->actingAs($user2)->delete(route('bank-accounts.destroy', $account1));
        $this->assertEquals(403, $response->status());
    }

    public function test_iban_normalization_removes_spaces_and_uppercases(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'EUR',
            'iban' => 'ro49 aaaa 1b31 0075 9384 0000',
            'nickname' => 'Test',
        ]);

        $account = BankAccount::where('company_id', $user->company_id)->first();
        $this->assertEquals('RO49AAAA1B31007593840000', $account->iban);
    }

    public function test_iban_format_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'EUR',
            'iban' => 'INVALID',
        ]);

        $response->assertSessionHasErrors('iban');
    }

    public function test_invoice_payment_details_snapshot_immutability_after_bank_account_delete(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Main Account',
        ]);

        // Create invoice with payment_details
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'status' => 'issued',
            'payment_details' => [
                'company_name' => $company->name,
                'currency' => 'EUR',
                'iban' => 'RO49AAAA1B31007593840000',
                'nickname' => 'Main Account',
                'captured_at' => now()->toIso8601String(),
            ],
        ]);

        $originalPaymentDetails = $invoice->payment_details;

        // Delete the bank account
        $account->delete();

        // Payment details should remain unchanged
        $invoice->refresh();
        $this->assertEquals($originalPaymentDetails, $invoice->payment_details);
    }

    public function test_invoice_payment_details_snapshot_immutability_after_bank_account_update(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Old Name',
        ]);

        // Create invoice with payment_details
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'status' => 'issued',
            'payment_details' => [
                'company_name' => $company->name,
                'currency' => 'EUR',
                'iban' => 'RO49AAAA1B31007593840000',
                'nickname' => 'Old Name',
                'captured_at' => now()->toIso8601String(),
            ],
        ]);

        $originalPaymentDetails = $invoice->payment_details;

        // Update the bank account
        $account->update([
            'iban' => 'RO49BBBB2B31007593840001',
            'nickname' => 'New Name',
        ]);

        // Payment details should remain unchanged
        $invoice->refresh();
        $this->assertEquals($originalPaymentDetails, $invoice->payment_details);
    }

    public function test_cannot_delete_last_bank_account(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $response = $this->actingAs($user)->delete(route('bank-accounts.destroy', $account));

        $response->assertRedirect(route('bank-accounts.index'));
        $response->assertSessionHasErrors('error');

        // Account should not be deleted
        $this->assertNotSoftDeleted('bank_accounts', ['id' => $account->id]);
        $account->refresh();
        $this->assertNull($account->deleted_at);
    }

    public function test_can_restore_bank_account(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Create two accounts so we can delete one
        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        $account = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $accountId = $account->id;

        // Soft delete the account
        $this->actingAs($user)->delete(route('bank-accounts.destroy', $account));
        $this->assertSoftDeleted('bank_accounts', ['id' => $accountId]);

        // Restore the account
        $response = $this->actingAs($user)->post(route('bank-accounts.restore', $account));

        $response->assertRedirect(route('bank-accounts.index'));

        // Assert account is restored
        $account->refresh();
        $this->assertNull($account->deleted_at);
        $this->assertNotSoftDeleted('bank_accounts', ['id' => $accountId]);

        // Assert restore audit event was created
        $event = BankAccountEvent::where('bank_account_id', $accountId)
            ->where('action', 'restored')
            ->first();

        $this->assertNotNull($event, 'Bank account restore event should be created');
        $this->assertEquals($user->id, $event->user_id);
        $this->assertNotNull($event->ip_address, 'IP address should be captured');
    }

    public function test_cannot_restore_another_tenants_bank_account(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create two accounts for user1 so we can delete one
        BankAccount::create([
            'company_id' => $user1->company_id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        $account = BankAccount::create([
            'company_id' => $user1->company_id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        // Delete account as user1
        $this->actingAs($user1)->delete(route('bank-accounts.destroy', $account));
        $this->assertSoftDeleted('bank_accounts', ['id' => $account->id]);

        // Try to restore as user2 (should fail with 403 - authorization check)
        $response = $this->actingAs($user2)->post(route('bank-accounts.restore', $account));
        $this->assertEquals(403, $response->status());
    }

    public function test_bank_account_audit_events_capture_ip_address(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Test create event
        $this->actingAs($user)->post(route('bank-accounts.store'), [
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
            'nickname' => 'Test Account',
        ]);

        $account = BankAccount::where('company_id', $company->id)->first();
        $createEvent = BankAccountEvent::where('bank_account_id', $account->id)
            ->where('action', 'created')
            ->first();

        $this->assertNotNull($createEvent->ip_address, 'Create event should capture IP address');

        // Test update event
        $this->actingAs($user)->put(route('bank-accounts.update', $account), [
            'currency' => 'USD',
            'iban' => 'RO49BBBB2B31007593840001',
            'nickname' => 'Updated Account',
        ]);

        $updateEvent = BankAccountEvent::where('bank_account_id', $account->id)
            ->where('action', 'updated')
            ->first();

        $this->assertNotNull($updateEvent->ip_address, 'Update event should capture IP address');
    }

    public function test_soft_deleted_bank_accounts_excluded_from_queries(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $activeAccount = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $anotherAccount = BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'iban' => 'US64SVBKUS6S3300958879',
        ]);

        // Soft delete one account
        $anotherAccount->delete();

        // Reload company bank accounts
        $company->load('bankAccounts');

        // Should only see active account
        $this->assertCount(1, $company->bankAccounts);
        $this->assertEquals($activeAccount->id, $company->bankAccounts->first()->id);

        // Direct query should also exclude soft-deleted
        $allAccounts = BankAccount::where('company_id', $company->id)->get();
        $this->assertCount(1, $allAccounts);
        $this->assertEquals($activeAccount->id, $allAccounts->first()->id);
    }

    public function test_invoice_issue_requires_payment_details_if_company_has_bank_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        BankAccount::create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'iban' => 'RO49AAAA1B31007593840000',
        ]);

        $client = \App\Models\Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'payment_details' => null,
        ]);

        \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('vat');

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
    }

    public function test_invoice_issue_fails_if_company_has_no_bank_accounts(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $client = \App\Models\Client::factory()->create(['company_id' => $company->id]);
        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'payment_details' => null,
        ]);

        \App\Models\InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1.0,
            'unit_price_minor' => 10000,
            'line_total_minor' => 10000,
        ]);

        \Illuminate\Support\Facades\Queue::fake();

        $response = $this->actingAs($user)->post(route('invoices.issue', $invoice));

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals('draft', $invoice->status);
    }
}
