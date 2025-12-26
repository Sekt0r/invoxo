<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardClientsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_recent_clients_for_user_company_only(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $userB = User::factory()->create(['company_id' => $companyB->id]);

        // Create 7 clients for company A (should show 5 most recent)
        // Use timestamps to ensure proper ordering (ordered by updated_at desc)
        $baseTime = now()->subDays(7);
        $clientsA = [];
        for ($i = 1; $i <= 7; $i++) {
            $clientsA[] = Client::factory()->create([
                'company_id' => $companyA->id,
                'name' => "Company A Client {$i}",
                'created_at' => $baseTime->copy()->addDays($i),
                'updated_at' => $baseTime->copy()->addDays($i),
            ]);
        }

        // Create 3 clients for company B (should not appear)
        for ($i = 1; $i <= 3; $i++) {
            Client::factory()->create([
                'company_id' => $companyB->id,
                'name' => "Company B Client {$i}",
            ]);
        }

        $response = $this->actingAs($userA)->get(route('dashboard'));

        $response->assertOk();

        // Should contain the 5 most recent clients from company A (clients 7, 6, 5, 4, 3)
        $response->assertSee('Company A Client 7', false);
        $response->assertSee('Company A Client 6', false);
        $response->assertSee('Company A Client 5', false);
        $response->assertSee('Company A Client 4', false);
        $response->assertSee('Company A Client 3', false);

        // Should NOT contain older clients from company A (clients 2, 1)
        $response->assertDontSee('Company A Client 2', false);
        $response->assertDontSee('Company A Client 1', false);

        // Should NOT contain any clients from company B
        $response->assertDontSee('Company B Client 1', false);
        $response->assertDontSee('Company B Client 2', false);
        $response->assertDontSee('Company B Client 3', false);

        // Should contain "View all clients" link
        $response->assertSee(route('clients.index'), false);

        // Should contain "Add client" link
        $response->assertSee(route('clients.create'), false);
    }

    public function test_dashboard_shows_empty_state_when_no_clients(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No clients yet.', false);
        $response->assertSee(route('clients.create'), false);
    }
}

