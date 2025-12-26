<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_stats_are_company_scoped(): void
    {
        $companyA = Company::factory()->create();
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $clientA = Client::factory()->create(['company_id' => $companyA->id]);

        $companyB = Company::factory()->create();
        $clientB = Client::factory()->create(['company_id' => $companyB->id]);

        // Create invoices for company A this month
        Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->toDateString(),
            'total_minor' => 10000, // €100.00
            'currency' => 'EUR',
        ]);

        Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->toDateString(),
            'total_minor' => 20000, // €200.00
            'currency' => 'EUR',
        ]);

        // Create invoice for company B this month (should not appear)
        Invoice::factory()->create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->toDateString(),
            'total_minor' => 50000, // €500.00
            'currency' => 'EUR',
        ]);

        // Create draft invoice for company A (should not count)
        Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'draft',
            'issue_date' => Carbon::now()->toDateString(),
            'total_minor' => 30000, // €300.00
            'currency' => 'EUR',
        ]);

        // Create issued invoice for company A from last month (should not count)
        Invoice::factory()->create([
            'company_id' => $companyA->id,
            'client_id' => $clientA->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonth()->toDateString(),
            'total_minor' => 40000, // €400.00
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($userA)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard');
        $response->assertSee('2'); // 2 invoices issued this month for company A
        // Revenue this month section should show €300.00 (100 + 200)
        $response->assertSee('€300.00');
        // Company B's revenue should not appear
        $response->assertDontSee('€500.00');
        // Note: €400.00 from last month may appear in invoice history widget (last 6 months)
        // but should not be counted in "Revenue This Month" section
    }
}
