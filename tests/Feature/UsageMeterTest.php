<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanLimitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageMeterTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_numbers_correct_for_given_month(): void
    {
        $company = Company::factory()->create();
        $plan = Plan::factory()->create([
            'code' => 'PRO',
            'invoice_monthly_limit' => 10,
        ]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => Carbon::now()->subMonth(),
            'ends_at' => null,
        ]);

        $targetMonth = Carbon::now();

        // Create 3 invoices in target month
        Invoice::factory()->count(3)->create([
            'company_id' => $company->id,
            'status' => 'issued',
            'issue_date' => $targetMonth->copy()->day(15)->toDateString(),
        ]);

        // Create 2 invoices in previous month (should not count)
        Invoice::factory()->count(2)->create([
            'company_id' => $company->id,
            'status' => 'issued',
            'issue_date' => $targetMonth->copy()->subMonth()->day(15)->toDateString(),
        ]);

        $service = app(PlanLimitService::class);
        $usage = $service->usageForMonth($company, $targetMonth);

        $this->assertEquals(3, $usage['used']);
        $this->assertEquals(10, $usage['limit']);
        $this->assertEquals('PRO', $usage['plan_code']);
    }

    public function test_usage_is_company_scoped(): void
    {
        $companyA = Company::factory()->create();
        $planA = Plan::factory()->create([
            'code' => 'PLAN-A',
            'invoice_monthly_limit' => 5,
        ]);
        Subscription::factory()->create([
            'company_id' => $companyA->id,
            'plan_id' => $planA->id,
            'starts_at' => Carbon::now()->subMonth(),
            'ends_at' => null,
        ]);

        $companyB = Company::factory()->create();
        $planB = Plan::factory()->create([
            'code' => 'PLAN-B',
            'invoice_monthly_limit' => 20,
        ]);
        Subscription::factory()->create([
            'company_id' => $companyB->id,
            'plan_id' => $planB->id,
            'starts_at' => Carbon::now()->subMonth(),
            'ends_at' => null,
        ]);

        $targetMonth = Carbon::now();

        // Create invoices for company A
        Invoice::factory()->count(2)->create([
            'company_id' => $companyA->id,
            'status' => 'issued',
            'issue_date' => $targetMonth->copy()->day(10)->toDateString(),
        ]);

        // Create invoices for company B
        Invoice::factory()->count(4)->create([
            'company_id' => $companyB->id,
            'status' => 'issued',
            'issue_date' => $targetMonth->copy()->day(10)->toDateString(),
        ]);

        $service = app(PlanLimitService::class);

        $usageA = $service->usageForMonth($companyA, $targetMonth);
        $usageB = $service->usageForMonth($companyB, $targetMonth);

        $this->assertEquals(2, $usageA['used']);
        $this->assertEquals(5, $usageA['limit']);
        $this->assertEquals('PLAN-A', $usageA['plan_code']);

        $this->assertEquals(4, $usageB['used']);
        $this->assertEquals(20, $usageB['limit']);
        $this->assertEquals('PLAN-B', $usageB['plan_code']);
    }

    public function test_usage_returns_null_for_limit_when_no_plan(): void
    {
        $company = Company::factory()->create();
        // No subscription/plan

        $targetMonth = Carbon::now();

        Invoice::factory()->count(1)->create([
            'company_id' => $company->id,
            'status' => 'issued',
            'issue_date' => $targetMonth->copy()->day(10)->toDateString(),
        ]);

        $service = app(PlanLimitService::class);
        $usage = $service->usageForMonth($company, $targetMonth);

        $this->assertEquals(1, $usage['used']);
        $this->assertNull($usage['limit']);
        $this->assertNull($usage['plan_code']);
    }

    public function test_usage_returns_null_limit_when_unlimited(): void
    {
        $company = Company::factory()->create();
        $plan = Plan::factory()->create([
            'code' => 'UNLIMITED',
            'invoice_monthly_limit' => null, // Unlimited
        ]);
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'starts_at' => Carbon::now()->subMonth(),
            'ends_at' => null,
        ]);

        $targetMonth = Carbon::now();

        Invoice::factory()->count(5)->create([
            'company_id' => $company->id,
            'status' => 'issued',
            'issue_date' => $targetMonth->copy()->day(10)->toDateString(),
        ]);

        $service = app(PlanLimitService::class);
        $usage = $service->usageForMonth($company, $targetMonth);

        $this->assertEquals(5, $usage['used']);
        $this->assertNull($usage['limit']);
        $this->assertEquals('UNLIMITED', $usage['plan_code']);
    }
}







