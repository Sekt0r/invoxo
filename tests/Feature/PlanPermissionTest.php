<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_no_subscription_has_no_permissions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->assertFalse($user->hasPlanPermission('single_company'));
        $this->assertFalse($user->hasPlanPermission('vies_validation'));
    }

    public function test_user_with_no_company_has_no_permissions(): void
    {
        $user = User::factory()->create(['company_id' => null]);

        $this->assertFalse($user->hasPlanPermission('single_company'));
    }

    public function test_user_with_expired_subscription_has_no_permissions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(), // Expired
        ]);

        $this->assertFalse($user->hasPlanPermission('single_company'));
        $this->assertFalse($user->hasPlanPermission('vies_validation'));
    }

    public function test_user_with_future_subscription_has_no_permissions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'starts_at' => now()->addMonth(), // Starts in the future
            'ends_at' => null,
        ]);

        $this->assertFalse($user->hasPlanPermission('single_company'));
    }

    public function test_user_with_active_starter_subscription_has_starter_permissions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Starter plan grants these permissions
        $this->assertTrue($user->hasPlanPermission('single_company'));
        $this->assertTrue($user->hasPlanPermission('eu_templates'));
        $this->assertTrue($user->hasPlanPermission('vat_id_format_check'));
        $this->assertTrue($user->hasPlanPermission('manual_vat_rates'));
        $this->assertTrue($user->hasPlanPermission('pdf_export'));

        // Starter plan does NOT grant Pro-only permissions
        $this->assertFalse($user->hasPlanPermission('multi_currency'));
        $this->assertFalse($user->hasPlanPermission('vies_validation'));
        $this->assertFalse($user->hasPlanPermission('vat_rate_auto'));
        $this->assertFalse($user->hasPlanPermission('cross_border_b2b'));
        $this->assertFalse($user->hasPlanPermission('accountant_exports'));

        // Starter plan does NOT grant Business-only permissions
        $this->assertFalse($user->hasPlanPermission('priority_support'));
        $this->assertFalse($user->hasPlanPermission('audit_trail'));
        $this->assertFalse($user->hasPlanPermission('peppol'));
    }

    public function test_user_with_active_pro_subscription_has_pro_permissions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Pro plan grants Pro-specific permissions
        $this->assertTrue($user->hasPlanPermission('vies_validation'));
        $this->assertTrue($user->hasPlanPermission('vat_rate_auto'));
        $this->assertTrue($user->hasPlanPermission('cross_border_b2b'));
        $this->assertTrue($user->hasPlanPermission('accountant_exports'));

        // Pro plan does NOT grant Business-only permissions
        $this->assertFalse($user->hasPlanPermission('priority_support'));
        $this->assertFalse($user->hasPlanPermission('audit_trail'));
        $this->assertFalse($user->hasPlanPermission('peppol'));
    }

    public function test_user_with_active_business_subscription_has_business_permissions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'business',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Business plan grants Business-specific permissions (even if value is false in config)
        $this->assertTrue($user->hasPlanPermission('priority_support'));
        $this->assertTrue($user->hasPlanPermission('audit_trail'));
        $this->assertTrue($user->hasPlanPermission('peppol'));

        // Business plan also grants inherited permissions (Pro permissions, even if marked false)
        $this->assertTrue($user->hasPlanPermission('vies_validation'));
        $this->assertTrue($user->hasPlanPermission('vat_rate_auto'));
        $this->assertTrue($user->hasPlanPermission('cross_border_b2b'));
        $this->assertTrue($user->hasPlanPermission('accountant_exports'));
    }

    public function test_permission_check_ignores_boolean_value_in_config(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'business',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Even though 'vies_validation' is set to false in business plan config,
        // it should still be granted because the key exists (presence-based, not value-based)
        $this->assertTrue($user->hasPlanPermission('vies_validation'));
    }

    public function test_most_recent_subscription_is_used_when_multiple_exist(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Older subscription (should be ignored)
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'starts_at' => now()->subMonths(3),
            'ends_at' => null,
        ]);

        // Newer subscription (should be used)
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        // Should have Pro permissions, not Starter permissions
        $this->assertTrue($user->hasPlanPermission('vies_validation'));
        $this->assertTrue($user->hasPlanPermission('multi_currency'));
    }

    public function test_subscription_with_future_end_date_is_active(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(), // Ends in the future
        ]);

        $this->assertTrue($user->hasPlanPermission('vies_validation'));
    }

    public function test_subscription_is_active_method(): void
    {
        $now = Carbon::parse('2025-01-15 12:00:00');
        Carbon::setTestNow($now);

        $subscription = Subscription::factory()->create([
            'plan' => 'starter',
            'starts_at' => $now->copy()->subMonth(),
            'ends_at' => null,
        ]);

        $this->assertTrue($subscription->isActive());

        // Test with future start date
        $subscription->starts_at = $now->copy()->addMonth();
        $this->assertFalse($subscription->isActive());

        // Test with past end date
        $subscription->starts_at = $now->copy()->subMonth();
        $subscription->ends_at = $now->copy()->subWeek();
        $this->assertFalse($subscription->isActive());

        // Test with future end date
        $subscription->ends_at = $now->copy()->addMonth();
        $this->assertTrue($subscription->isActive());

        Carbon::setTestNow();
    }

    public function test_company_active_subscription_method(): void
    {
        $company = Company::factory()->create();

        // Create expired subscription
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'starter',
            'starts_at' => now()->subMonths(3),
            'ends_at' => now()->subMonth(),
        ]);

        $this->assertNull($company->activeSubscription());

        // Create active subscription
        $activeSubscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $this->assertNotNull($company->activeSubscription());
        $this->assertEquals($activeSubscription->id, $company->activeSubscription()->id);
        $this->assertEquals('pro', $company->activeSubscription()->plan);

        // Add another active subscription (should return most recent)
        $newerSubscription = Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'business',
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        $this->assertEquals($newerSubscription->id, $company->activeSubscription()->id);
        $this->assertEquals('business', $company->activeSubscription()->plan);
    }

    public function test_nonexistent_permission_returns_false(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'pro',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $this->assertFalse($user->hasPlanPermission('nonexistent_permission'));
    }

    public function test_nonexistent_plan_returns_false(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan' => 'invalid_plan',
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
        ]);

        $this->assertFalse($user->hasPlanPermission('single_company'));
    }
}

