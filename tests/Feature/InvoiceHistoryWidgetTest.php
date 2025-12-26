<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class InvoiceHistoryWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_aggregation_per_month(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoices for different months
        $invoice1 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonths(1)->format('Y-m-15'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        $invoice2 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonths(1)->format('Y-m-20'),
            'total_minor' => 20000,
            'currency' => 'EUR',
        ]);

        $invoice3 = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'paid',
            'issue_date' => Carbon::now()->format('Y-m-10'),
            'total_minor' => 15000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Invoice History');
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            // Should have data for last month and current month
            $lastMonthKey = Carbon::now()->subMonths(1)->format('Y-m');
            $currentMonthKey = Carbon::now()->format('Y-m');

            return isset($history[$lastMonthKey]['EUR'])
                && $history[$lastMonthKey]['EUR'] === 30000 // 10000 + 20000
                && isset($history[$currentMonthKey]['EUR'])
                && $history[$currentMonthKey]['EUR'] === 15000;
        });
    }

    public function test_correct_currency_separation(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        $currentMonthKey = Carbon::now()->format('Y-m');

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->format('Y-m-15'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'paid',
            'issue_date' => Carbon::now()->format('Y-m-20'),
            'total_minor' => 50000,
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) use ($currentMonthKey) {
            return isset($history[$currentMonthKey]['EUR'])
                && $history[$currentMonthKey]['EUR'] === 10000
                && isset($history[$currentMonthKey]['USD'])
                && $history[$currentMonthKey]['USD'] === 50000;
        });
    }

    public function test_draft_invoices_excluded(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'draft',
            'issue_date' => Carbon::now()->format('Y-m-15'),
            'total_minor' => 50000,
            'currency' => 'EUR',
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->format('Y-m-20'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            $currentMonthKey = Carbon::now()->format('Y-m');
            // Should only include issued invoice (10000), not draft (50000)
            return isset($history[$currentMonthKey]['EUR'])
                && $history[$currentMonthKey]['EUR'] === 10000;
        });
    }

    public function test_tenant_isolation_enforced(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $client1 = Client::factory()->create(['company_id' => $user1->company->id]);
        $client2 = Client::factory()->create(['company_id' => $user2->company->id]);

        Invoice::factory()->create([
            'company_id' => $user1->company->id,
            'client_id' => $client1->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->format('Y-m-15'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        Invoice::factory()->create([
            'company_id' => $user2->company->id,
            'client_id' => $client2->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->format('Y-m-15'),
            'total_minor' => 20000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user1)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            $currentMonthKey = Carbon::now()->format('Y-m');
            // Should only include user1's invoice (10000), not user2's (20000)
            return isset($history[$currentMonthKey]['EUR'])
                && $history[$currentMonthKey]['EUR'] === 10000;
        });
    }

    public function test_uses_issue_date_not_created_at(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice with issue_date in last month but created_at in current month
        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonths(1)->format('Y-m-15'),
            'created_at' => Carbon::now(),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            $lastMonthKey = Carbon::now()->subMonths(1)->format('Y-m');
            $currentMonthKey = Carbon::now()->format('Y-m');
            // Should be grouped by issue_date (last month), not created_at (current month)
            return isset($history[$lastMonthKey]['EUR'])
                && $history[$lastMonthKey]['EUR'] === 10000
                && !isset($history[$currentMonthKey]['EUR']);
        });
    }

    public function test_limits_to_last_6_months(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice 7 months ago (should be excluded)
        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonths(7)->format('Y-m-15'),
            'total_minor' => 50000,
            'currency' => 'EUR',
        ]);

        // Create invoice 6 months ago (should be included - last 6 months including current)
        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonths(5)->startOfMonth()->format('Y-m-d'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            $sixMonthsAgoKey = Carbon::now()->subMonths(5)->startOfMonth()->format('Y-m');
            $sevenMonthsAgoKey = Carbon::now()->subMonths(7)->format('Y-m');
            // Should include 6 months ago (10000) but not 7 months ago (50000)
            return isset($history[$sixMonthsAgoKey]['EUR'])
                && $history[$sixMonthsAgoKey]['EUR'] === 10000
                && !isset($history[$sevenMonthsAgoKey]);
        });
    }

    public function test_shows_empty_message_when_no_invoices(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No issued invoices yet');
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            return empty($history);
        });
    }

    public function test_includes_both_issued_and_paid_statuses(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        $currentMonthKey = Carbon::now()->format('Y-m');

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->format('Y-m-15'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'paid',
            'issue_date' => Carbon::now()->format('Y-m-20'),
            'total_minor' => 20000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) use ($currentMonthKey) {
            // Should include both issued (10000) and paid (20000) = 30000 total
            return isset($history[$currentMonthKey]['EUR'])
                && $history[$currentMonthKey]['EUR'] === 30000;
        });
    }

    public function test_months_with_zero_data_do_not_appear(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoice only for current month
        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->format('Y-m-15'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            $currentMonthKey = Carbon::now()->format('Y-m');
            $lastMonthKey = Carbon::now()->subMonths(1)->format('Y-m');
            // Should only have current month, not last month (which has no data)
            return count($history) === 1
                && isset($history[$currentMonthKey]['EUR'])
                && !isset($history[$lastMonthKey]);
        });
    }

    public function test_months_ordered_descending(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $client = Client::factory()->create(['company_id' => $company->id]);

        // Create invoices for different months
        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonths(2)->format('Y-m-15'),
            'total_minor' => 10000,
            'currency' => 'EUR',
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->format('Y-m-15'),
            'total_minor' => 20000,
            'currency' => 'EUR',
        ]);

        Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status' => 'issued',
            'issue_date' => Carbon::now()->subMonths(1)->format('Y-m-15'),
            'total_minor' => 15000,
            'currency' => 'EUR',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('invoiceHistoryByMonth', function ($history) {
            $keys = array_keys($history);
            // Should be ordered descending (most recent first)
            $currentMonthKey = Carbon::now()->format('Y-m');
            $lastMonthKey = Carbon::now()->subMonths(1)->format('Y-m');
            $twoMonthsAgoKey = Carbon::now()->subMonths(2)->format('Y-m');

            return $keys[0] === $currentMonthKey
                && $keys[1] === $lastMonthKey
                && $keys[2] === $twoMonthsAgoKey;
        });
    }
}






