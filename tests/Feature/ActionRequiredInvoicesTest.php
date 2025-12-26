<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionRequiredInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_invoices_appear_in_action_required(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice1 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'created_at' => now()->subDays(5),
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'number' => null,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Action Required');
        $response->assertSee('Draft', false);
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) use ($invoice1, $invoice2) {
            return $invoices->count() === 2
                && $invoices->contains('id', $invoice1->id)
                && $invoices->contains('id', $invoice2->id);
        });
    }

    public function test_issued_unpaid_invoices_appear_in_action_required(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'number' => 'INV-2025-001',
            'issue_date' => now()->subDays(3),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Action Required');
        $response->assertSee('Unpaid', false);
        $response->assertSee('INV-2025-001');
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) use ($invoice) {
            return $invoices->contains('id', $invoice->id);
        });
    }

    public function test_paid_invoices_do_not_appear(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'paid',
            'number' => 'INV-2025-001',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) {
            return $invoices->isEmpty();
        });
    }

    public function test_voided_invoices_do_not_appear(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'voided',
            'number' => 'INV-2025-001',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) {
            return $invoices->isEmpty();
        });
    }

    public function test_tenant_isolation_enforced(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $client1 = Client::factory()->create(['company_id' => $user1->company->id]);
        $client2 = Client::factory()->create(['company_id' => $user2->company->id]);

        $invoice1 = Invoice::factory()->create([
            'company_id' => $user1->company->id,
            'client_id' => $client1->id,
            'status' => 'draft',
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $user2->company->id,
            'client_id' => $client2->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user1)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) use ($invoice1, $invoice2) {
            return $invoices->contains('id', $invoice1->id)
                && !$invoices->contains('id', $invoice2->id);
        });
    }

    public function test_order_is_correct_drafts_first(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create unpaid invoice (older)
        $unpaid1 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'number' => 'INV-2025-001',
            'issue_date' => now()->subDays(10),
        ]);

        // Create draft invoice (newer but should appear first)
        $draft1 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'created_at' => now()->subDays(2),
        ]);

        // Create another unpaid invoice (newer)
        $unpaid2 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'number' => 'INV-2025-002',
            'issue_date' => now()->subDays(5),
        ]);

        // Create another draft invoice (older)
        $draft2 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'created_at' => now()->subDays(8),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) use ($draft2, $draft1, $unpaid1, $unpaid2) {
            $ids = $invoices->pluck('id')->toArray();

            // Drafts should come first
            $draft2Index = array_search($draft2->id, $ids);
            $draft1Index = array_search($draft1->id, $ids);
            $unpaid1Index = array_search($unpaid1->id, $ids);
            $unpaid2Index = array_search($unpaid2->id, $ids);

            // All should be found
            if ($draft2Index === false || $draft1Index === false || $unpaid1Index === false || $unpaid2Index === false) {
                return false;
            }

            // Drafts should be before unpaid
            return $draft2Index < $unpaid1Index
                && $draft1Index < $unpaid1Index
                && $draft2Index < $unpaid2Index
                && $draft1Index < $unpaid2Index;
        });
    }

    public function test_limit_to_5_invoices(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create 7 invoices
        Invoice::factory()->count(7)->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) {
            return $invoices->count() === 5;
        });
    }

    public function test_handles_soft_deleted_clients_gracefully(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        // Soft delete the client
        $client->delete();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Action Required');
        $response->assertDontSee('Trying to get property', false); // Should not throw error
        $response->assertViewHas('actionRequiredInvoices', function ($invoices) use ($invoice) {
            return $invoices->contains('id', $invoice->id);
        });
    }

    public function test_shows_empty_message_when_no_action_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No invoices require action 🎉');
    }

    public function test_draft_invoices_link_to_edit(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('invoices.edit', $invoice));
    }

    public function test_unpaid_invoices_link_to_show(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'number' => 'INV-2025-001',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('invoices.show', $invoice));
    }
}





