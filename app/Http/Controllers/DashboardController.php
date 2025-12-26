<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $company = auth()->user()->company;

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $revenueThisMonth = Invoice::where('company_id', $company->id)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('total_minor');

        $usage = app(PlanLimitService::class)->usageForMonth($company, Carbon::now());

        // Load recent clients for the dashboard widget
        $recentClients = Client::query()
            ->where('company_id', $company->id)
            ->with('vatIdentity:id,status')
            ->orderBy('updated_at', 'DESC')
            ->limit(5)
            ->get(['id', 'name', 'country_code', 'vat_id', 'vat_identity_id', 'updated_at']);

        // Get total count of active clients (excluding soft-deleted)
        $clientsCount = Client::where('company_id', $company->id)->count();

        // Load action required invoices (drafts and unpaid issued)
        // Get drafts and unpaid separately, then merge and limit
        $drafts = Invoice::query()
            ->where('company_id', $company->id)
            ->where('status', 'draft')
            ->with('client:id,name')
            ->orderByRaw('COALESCE(issue_date, created_at) ASC')
            ->get(['id', 'number', 'status', 'issue_date', 'total_minor', 'currency', 'client_id', 'created_at']);

        $unpaid = Invoice::query()
            ->where('company_id', $company->id)
            ->where('status', 'issued')
            ->with('client:id,name')
            ->orderBy('issue_date', 'ASC')
            ->get(['id', 'number', 'status', 'issue_date', 'total_minor', 'currency', 'client_id', 'created_at']);

        // Merge: drafts first, then unpaid, limit to 5
        $actionRequiredInvoices = $drafts->concat($unpaid)->take(5);

        // Load invoice history by month (last 6 months including current)
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $invoiceHistoryByMonth = Invoice::query()
            ->where('company_id', $company->id)
            ->whereIn('status', ['issued', 'paid'])
            ->whereNotNull('issue_date')
            ->where('issue_date', '>=', $sixMonthsAgo->toDateString())
            ->selectRaw("
                to_char(issue_date, 'YYYY-MM') as month_key,
                currency,
                SUM(total_minor) as total_minor
            ")
            ->groupBy('month_key', 'currency')
            ->orderBy('month_key', 'DESC')
            ->get()
            ->groupBy('month_key')
            ->map(function ($monthData) {
                return $monthData->mapWithKeys(function ($row) {
                    return [$row->currency => (int)$row->total_minor];
                })->toArray();
            })
            ->toArray();

        return view('dashboard', [
            'usage' => $usage,
            'revenue_this_month_minor' => $revenueThisMonth,
            'recentClients' => $recentClients,
            'clientsCount' => $clientsCount,
            'actionRequiredInvoices' => $actionRequiredInvoices,
            'invoiceHistoryByMonth' => $invoiceHistoryByMonth,
        ]);
    }
}
