<?php

namespace App\Services;

use App\Enums\HrPayrollRuleCalculation;
use App\Enums\HrPayrollRuleKind;
use App\Enums\HrPayrollRunStatus;
use App\Enums\HrSalaryAdvanceStatus;
use App\Models\Company;
use App\Models\HrEmployee;
use App\Models\HrPayrollRule;
use App\Models\HrPayrollRun;
use App\Models\HrPayslip;
use App\Models\HrSalaryAdvance;
use Illuminate\Support\Facades\DB;

class HrPayrollService
{
    public function generatePayslips(HrPayrollRun $run, Company $company): int
    {
        abort_if($run->status !== HrPayrollRunStatus::Draft, 422, __('hr_payroll_not_draft'));

        $currency = flowdesk_normalize_currency_code($run->currency ?? $company->default_currency ?? flowdesk_default_currency());

        $employees = HrEmployee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        $rules = HrPayrollRule::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return DB::transaction(function () use ($run, $employees, $currency, $rules, $company): int {
            $existingPayslipIds = $run->payslips()->pluck('id');
            if ($existingPayslipIds->isNotEmpty()) {
                HrSalaryAdvance::query()
                    ->where('company_id', $company->id)
                    ->whereIn('payslip_id', $existingPayslipIds)
                    ->update([
                        'status' => HrSalaryAdvanceStatus::Approved->value,
                        'payslip_id' => null,
                    ]);
            }

            $run->payslips()->delete();
            $created = 0;

            foreach ($employees as $employee) {
                if (! $employee->isPayrollEligible()) {
                    continue;
                }

                $computed = $this->computePayslipAmounts($employee, $rules, $currency);
                $payslip = HrPayslip::query()->create([
                    'company_id' => $run->company_id,
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'gross_minor' => $computed['gross_minor'],
                    'deductions_minor' => $computed['deductions_minor'],
                    'net_minor' => $computed['net_minor'],
                    'currency' => $employee->salaryCurrency($currency),
                    'breakdown' => $computed['breakdown'],
                    'status' => 'draft',
                ]);

                foreach ($computed['advance_ids'] as $advanceId) {
                    HrSalaryAdvance::query()
                        ->where('id', $advanceId)
                        ->where('company_id', $company->id)
                        ->where('status', HrSalaryAdvanceStatus::Approved)
                        ->update([
                            'status' => HrSalaryAdvanceStatus::Deducted->value,
                            'payslip_id' => $payslip->id,
                        ]);
                }

                $created++;
            }

            return $created;
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, HrPayrollRule>  $rules
     * @return array{
     *     gross_minor: int,
     *     deductions_minor: int,
     *     net_minor: int,
     *     breakdown: array<string, mixed>,
     *     advance_ids: list<string>
     * }
     */
    public function computePayslipAmounts(HrEmployee $employee, $rules, string $fallbackCurrency): array
    {
        $base = max(0, (int) $employee->base_salary_minor);
        $lines = [];
        $sort = 0;

        $lines[] = [
            'code' => 'base_salary',
            'name' => __('hr_base_salary'),
            'kind' => HrPayrollRuleKind::Earning->value,
            'amount_minor' => $base,
            'sort_order' => $sort++,
            'rule_id' => null,
        ];

        $earnings = $base;
        $deductions = 0;

        foreach ($rules as $rule) {
            $amount = $rule->amountForBase($base);
            if ($amount <= 0) {
                continue;
            }

            $kind = $rule->kind instanceof HrPayrollRuleKind
                ? $rule->kind
                : HrPayrollRuleKind::tryFrom((string) $rule->kind) ?? HrPayrollRuleKind::Deduction;

            if ($kind === HrPayrollRuleKind::Earning) {
                $earnings += $amount;
            } else {
                $deductions += $amount;
            }

            $calculation = $rule->calculation instanceof HrPayrollRuleCalculation
                ? $rule->calculation
                : HrPayrollRuleCalculation::tryFrom((string) $rule->calculation);

            $include = $rule->include_on_payslip !== false;
            if ($include) {
                $lines[] = [
                    'code' => $rule->code ?: (string) $rule->id,
                    'name' => (string) $rule->name,
                    'kind' => $kind->value,
                    'amount_minor' => $amount,
                    'sort_order' => (int) ($rule->sort_order ?? $sort),
                    'rule_id' => (string) $rule->id,
                    'percent' => $calculation === HrPayrollRuleCalculation::PercentOfBase
                        ? (float) $rule->percent
                        : null,
                ];
                $sort++;
            }
        }

        $advanceIds = [];
        $advances = HrSalaryAdvance::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', HrSalaryAdvanceStatus::Approved)
            ->orderBy('requested_on')
            ->get();

        foreach ($advances as $advance) {
            $amount = max(0, (int) $advance->amount_minor);
            if ($amount <= 0) {
                continue;
            }
            $deductions += $amount;
            $lines[] = [
                'code' => 'salary_advance',
                'name' => __('hr_salary_advance'),
                'kind' => HrPayrollRuleKind::Deduction->value,
                'amount_minor' => $amount,
                'sort_order' => $sort++,
                'rule_id' => null,
                'advance_id' => $advance->id,
            ];
            $advanceIds[] = (string) $advance->id;
        }

        $gross = max(0, $earnings);
        $deductions = max(0, $deductions);
        $net = max(0, $gross - $deductions);

        usort($lines, fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return [
            'gross_minor' => $gross,
            'deductions_minor' => $deductions,
            'net_minor' => $net,
            'breakdown' => [
                'base_salary' => $base,
                'earnings_minor' => $gross,
                'deductions_minor' => $deductions,
                'net_minor' => $net,
                'currency' => $employee->salaryCurrency($fallbackCurrency),
                'lines' => $lines,
            ],
            'advance_ids' => $advanceIds,
        ];
    }

    public function finalize(HrPayrollRun $run): void
    {
        abort_if($run->status !== HrPayrollRunStatus::Draft, 422, __('hr_payroll_not_draft'));
        abort_if($run->payslips()->count() === 0, 422, __('hr_payroll_no_payslips'));

        $run->update([
            'status' => HrPayrollRunStatus::Finalized,
            'finalized_at' => now(),
        ]);
    }

    public function markPaid(HrPayrollRun $run): void
    {
        abort_if($run->status !== HrPayrollRunStatus::Finalized, 422, __('hr_payroll_not_finalized'));

        DB::transaction(function () use ($run): void {
            $run->payslips()->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
            $run->update(['status' => HrPayrollRunStatus::Paid]);
        });
    }
}
