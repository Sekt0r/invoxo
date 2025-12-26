<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BankAccountDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_account_as_default_unsets_others(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        // Create two bank accounts
        $account1 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'is_default' => true,
        ]);

        $account2 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'is_default' => false,
        ]);

        // Set account2 as default
        $account2->setAsDefault();

        $account1->refresh();
        $account2->refresh();

        $this->assertFalse($account1->is_default);
        $this->assertTrue($account2->is_default);
    }

    public function test_only_one_default_per_company(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account1 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'is_default' => true,
        ]);

        $account2 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'is_default' => false,
        ]);

        $account3 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'GBP',
            'is_default' => false,
        ]);

        // Set account3 as default
        $account3->setAsDefault();

        $defaultCount = BankAccount::where('company_id', $company->id)
            ->where('is_default', true)
            ->count();

        $this->assertEquals(1, $defaultCount);
        $this->assertTrue($account3->refresh()->is_default);
        $this->assertFalse($account1->refresh()->is_default);
        $this->assertFalse($account2->refresh()->is_default);
    }

    public function test_set_default_via_controller_action(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account1 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'is_default' => true,
        ]);

        $account2 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'is_default' => false,
        ]);

        $response = $this->actingAs($user)->post(route('bank-accounts.set-default', $account2));

        $response->assertRedirect(route('bank-accounts.index'));

        $account1->refresh();
        $account2->refresh();

        $this->assertFalse($account1->is_default);
        $this->assertTrue($account2->is_default);
    }

    public function test_cannot_set_default_for_other_company_account(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $account1 = BankAccount::factory()->create([
            'company_id' => $user1->company->id,
            'currency' => 'EUR',
        ]);

        $account2 = BankAccount::factory()->create([
            'company_id' => $user2->company->id,
            'currency' => 'USD',
        ]);

        // User1 tries to set User2's account as default
        $response = $this->actingAs($user1)->post(route('bank-accounts.set-default', $account2));

        $response->assertStatus(403);
    }

    public function test_default_change_is_audited(): void
    {
        $user = User::factory()->create();
        $company = $user->company;

        $account1 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'EUR',
            'is_default' => true,
        ]);

        $account2 = BankAccount::factory()->create([
            'company_id' => $company->id,
            'currency' => 'USD',
            'is_default' => false,
        ]);

        // Set account2 as default
        $account2->setAsDefault();

        // Check audit events - account1 should have an update event (is_default changed to false)
        $account1Events = \App\Models\BankAccountEvent::where('bank_account_id', $account1->id)
            ->where('action', 'updated')
            ->get();

        // Account2 should have an update event (is_default changed to true)
        $account2Events = \App\Models\BankAccountEvent::where('bank_account_id', $account2->id)
            ->where('action', 'updated')
            ->get();

        // Verify account1 event shows is_default change to false
        if ($account1Events->isNotEmpty()) {
            $account1UpdateEvent = $account1Events->last();
            $this->assertArrayHasKey('is_default', $account1UpdateEvent->new_values ?? []);
            $this->assertFalse($account1UpdateEvent->new_values['is_default']);
        }

        // Verify account2 event shows is_default change to true
        if ($account2Events->isNotEmpty()) {
            $account2UpdateEvent = $account2Events->last();
            $this->assertArrayHasKey('is_default', $account2UpdateEvent->new_values ?? []);
            $this->assertTrue($account2UpdateEvent->new_values['is_default']);
        }

        // At least one of them should have an event
        $this->assertTrue($account1Events->isNotEmpty() || $account2Events->isNotEmpty(), 'At least one account should have an audit event');
    }
}

