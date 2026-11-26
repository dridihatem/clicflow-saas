<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\NegotiationStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\FormSubmission;
use App\Models\Invoice;
use App\Models\Negotiation;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\UsageTracking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * @return array<string, int|string|float|array<string, int>>
     */
    public function forCompany(Company $company): array
    {
        $cid = $company->id;

        $clientsCount = Client::query()->withoutGlobalScope('tenant')->where('company_id', $cid)->count();
        $projectsCount = Project::query()->withoutGlobalScope('tenant')->where('company_id', $cid)->count();

        $invoiceQuery = Invoice::query()->withoutGlobalScope('tenant')->where('company_id', $cid);
        $paidCount = (clone $invoiceQuery)->where('status', InvoiceStatus::Paid->value)->count();

        $completedPayments = Payment::query()
            ->withoutGlobalScopes()
            ->select('invoice_id', DB::raw('COALESCE(SUM(amount), 0) as paid_minor'))
            ->where('company_id', $cid)
            ->where('status', PaymentStatus::Completed)
            ->groupBy('invoice_id');

        $outstandingRows = Invoice::query()
            ->withoutGlobalScope('tenant')
            ->from('invoices')
            ->leftJoinSub($completedPayments, 'completed_payments', function ($join): void {
                $join->on('completed_payments.invoice_id', '=', 'invoices.id');
            })
            ->where('invoices.company_id', $cid)
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled)
            ->where('invoices.amount', '>', 0)
            ->whereRaw('invoices.amount > COALESCE(completed_payments.paid_minor, 0)')
            ->select([
                'invoices.currency',
                DB::raw('COUNT(*) as open_count'),
                DB::raw('COALESCE(SUM(invoices.amount - COALESCE(completed_payments.paid_minor, 0)), 0) as balance_minor'),
            ])
            ->groupBy('invoices.currency')
            ->get();

        $outstandingByCurrency = [];
        $openInvoices = 0;

        foreach ($outstandingRows as $row) {
            $openInvoices += (int) $row->open_count;
            $currency = flowdesk_normalize_currency_code($row->currency ?: $company->default_currency);
            $outstandingByCurrency[$currency] = ($outstandingByCurrency[$currency] ?? 0) + (int) $row->balance_minor;
        }

        ksort($outstandingByCurrency);

        $defaultCurrency = flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency());
        [$primaryCurrency, $primaryMinor] = $this->resolvePrimaryOutstanding($outstandingByCurrency, $defaultCurrency);

        return [
            'clients_count' => $clientsCount,
            'projects_count' => $projectsCount,
            'open_invoices_count' => $openInvoices,
            'paid_invoices_count' => $paidCount,
            'outstanding_by_currency' => $outstandingByCurrency,
            'outstanding_amount_minor' => $primaryMinor,
            'currency' => $primaryCurrency,
        ];
    }

    /**
     * @param  array<string, int>  $outstandingByCurrency
     * @return array{0: string, 1: int}
     */
    private function resolvePrimaryOutstanding(array $outstandingByCurrency, string $defaultCurrency): array
    {
        if ($outstandingByCurrency === []) {
            return [$defaultCurrency, 0];
        }

        if (isset($outstandingByCurrency[$defaultCurrency])) {
            return [$defaultCurrency, (int) $outstandingByCurrency[$defaultCurrency]];
        }

        if (count($outstandingByCurrency) === 1) {
            $currency = array_key_first($outstandingByCurrency);

            return [$currency, (int) $outstandingByCurrency[$currency]];
        }

        $sorted = $outstandingByCurrency;
        arsort($sorted);
        $currency = array_key_first($sorted);

        return [$currency, (int) $sorted[$currency]];
    }

    /**
     * Billing-style usage snapshot for the company dashboard widget.
     *
     * @return array{
     *     currency: string,
     *     invoice_revenue_minor: int,
     *     commission_total_minor: int,
     *     proposal_pipeline: int,
     *     ai_credits_used: int,
     *     form_submissions_month: int,
     *     pay_per_use_estimate_minor: int,
     *     ai_credit_price_minor: int,
     *     chart: array{labels: list<string>, values: list<int|float>}
     * }
     */
    public function usageOverview(Company $company): array
    {
        $cid = $company->id;
        $currency = flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency());

        $invoiceRevenue = (int) Invoice::query()->withoutGlobalScope('tenant')
            ->where('company_id', $cid)
            ->where('status', InvoiceStatus::Paid)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('amount');

        $commissionTotal = (int) Negotiation::query()->withoutGlobalScopes()
            ->where('company_id', $cid)
            ->where('status', NegotiationStatus::Accepted)
            ->whereNotNull('commission_amount_minor')
            ->where('created_at', '>=', now()->subDays(365))
            ->sum('commission_amount_minor');

        $proposalPipeline = (int) Proposal::query()->withoutGlobalScopes()
            ->where('company_id', $cid)
            ->whereIn('status', [ProposalStatus::Sent, ProposalStatus::Draft])
            ->count();

        $aiCreditsUsed = (int) UsageTracking::query()->withoutGlobalScopes()
            ->where('company_id', $cid)
            ->where('metric', 'ai_credits')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('value');

        $formSubmissionsMonth = (int) FormSubmission::query()->withoutGlobalScopes()
            ->where('company_id', $cid)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $aiCreditPriceMinor = (int) (Config::get('flowdesk.pay_per_use.ai_credit_price_minor') ?? 1);
        $payPerUseEstimate = $aiCreditsUsed * $aiCreditPriceMinor;

        return [
            'currency' => $currency,
            'invoice_revenue_minor' => $invoiceRevenue,
            'commission_total_minor' => $commissionTotal,
            'proposal_pipeline' => $proposalPipeline,
            'ai_credits_used' => $aiCreditsUsed,
            'form_submissions_month' => $formSubmissionsMonth,
            'pay_per_use_estimate_minor' => $payPerUseEstimate,
            'ai_credit_price_minor' => $aiCreditPriceMinor,
            'chart' => [
                'labels' => [
                    __('Open proposals'),
                    __('AI credits (this month)'),
                    __('Form submissions (this month)'),
                ],
                'values' => [
                    $proposalPipeline,
                    $aiCreditsUsed,
                    $formSubmissionsMonth,
                ],
            ],
        ];
    }

    /**
     * Active projects for dashboard: overdue and due-soon first, then by deadline.
     * Team members only see projects they created or are assigned to.
     *
     * @return Collection<int, Project>
     */
    public function dashboardProjects(Company $company, ?User $user = null, int $limit = 10): Collection
    {
        $query = Project::query()->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->with(['client'])
            ->orderByDesc('updated_at')
            ->limit(40);

        if ($user instanceof User) {
            $query->accessibleBy($user);
        }

        $projects = $query->get();

        return $projects
            ->sort(function (Project $a, Project $b): int {
                $ka = $this->projectDashboardSortKey($a);
                $kb = $this->projectDashboardSortKey($b);

                return $ka[0] <=> $kb[0] ?: $ka[1] <=> $kb[1];
            })
            ->values()
            ->take($limit);
    }

    /**
     * Project count visible to the given user (admins see company total).
     */
    public function projectsCountForUser(Company $company, User $user): int
    {
        return (int) Project::query()->withoutGlobalScope('tenant')
            ->where('company_id', $company->id)
            ->accessibleBy($user)
            ->count();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function projectDashboardSortKey(Project $p): array
    {
        if ($p->status === ProjectStatus::Completed) {
            return [80, $p->final_deadline?->timestamp ?? PHP_INT_MAX];
        }

        $d = $p->final_deadline;
        if ($d === null) {
            return [50, PHP_INT_MAX];
        }

        if (today()->gt($d)) {
            return [10, $d->timestamp];
        }

        if ($d->isToday()) {
            return [15, $d->timestamp];
        }

        if ($d->gt(today()) && $d->lte(today()->addDays(7))) {
            return [20, $d->timestamp];
        }

        return [40, $d->timestamp];
    }
}
