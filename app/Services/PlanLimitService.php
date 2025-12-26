<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;

final class PlanLimitService
{
    public function canIssueInvoice(Company $company, \DateTimeInterface $when): bool
    {
        // Find active subscription: ends_at is null OR ends_at > now
        $subscription = Subscription::where('company_id', $company->id)
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('starts_at')
            ->with('plan')
            ->first();

        // Fail-open: if no subscription, allow issuing
        if (!$subscription) {
            return true;
        }

        $plan = $subscription->plan;

        // Fail-open: if no plan, allow issuing
        if (!$plan) {
            return true;
        }

        // If limit is null, unlimited
        if ($plan->invoice_monthly_limit === null) {
            return true;
        }

        // Count issued invoices for the month of $when
        $monthStart = Carbon::instance($when)->startOfMonth();
        $monthEnd = Carbon::instance($when)->endOfMonth();

        $issuedCount = Invoice::where('company_id', $company->id)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        return $issuedCount < $plan->invoice_monthly_limit;
    }

    public function usageForMonth(Company $company, Carbon $when): array
    {
        // Count issued invoices for the month
        $monthStart = $when->copy()->startOfMonth();
        $monthEnd = $when->copy()->endOfMonth();

        $used = Invoice::where('company_id', $company->id)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        // Find active subscription
        $subscription = Subscription::where('company_id', $company->id)
            ->where(function ($query) use ($when) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $when);
            })
            ->orderByDesc('starts_at')
            ->with('plan')
            ->first();

        $limit = null;
        $planCode = null;

        if ($subscription && $subscription->plan) {
            $limit = $subscription->plan->invoice_monthly_limit;
            $planCode = $subscription->plan->code;
        }

        return [
            'used' => $used,
            'limit' => $limit,
            'plan_code' => $planCode,
        ];
    }
}

