<?php

namespace App\Http\Controllers\Hr\Concerns;

use App\Models\Company;
use App\Models\HrDepartment;
use App\Models\HrEmployee;
use App\Models\HrLeaveRequest;
use App\Models\HrPayrollRule;
use App\Models\HrPayrollRun;
use App\Models\HrPayslip;
use App\Models\HrSalaryAdvance;
use Illuminate\Http\Request;

trait AuthorizesHrWorkspace
{
    protected function authorizeHr(Request $request): Company
    {
        abort_if(! $request->user()->can('workspace.manage_hr'), 403);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        return $company;
    }

    /**
     * Full HR admin or self-service (for /hr/me routes).
     */
    protected function authorizeHrAccess(Request $request): Company
    {
        $user = $request->user();
        abort_if(
            ! $user->can('workspace.manage_hr') && ! $user->can('workspace.hr_self_service'),
            403
        );
        $company = $user->company;
        abort_if(! $company instanceof Company, 403);

        return $company;
    }

    protected function authorizeHrSelfService(Request $request): Company
    {
        $user = $request->user();
        abort_if(
            ! $user->can('workspace.hr_self_service') && ! $user->can('workspace.manage_hr'),
            403
        );
        $company = $user->company;
        abort_if(! $company instanceof Company, 403);

        return $company;
    }

    protected function linkedEmployee(Request $request, Company $company): HrEmployee
    {
        $employee = HrEmployee::query()
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if(! $employee, 403, __('hr_no_linked_employee'));

        return $employee;
    }

    protected function assertEmployee(Company $company, HrEmployee $employee): void
    {
        abort_if((string) $employee->company_id !== (string) $company->id, 404);
    }

    protected function assertDepartment(Company $company, HrDepartment $department): void
    {
        abort_if((string) $department->company_id !== (string) $company->id, 404);
    }

    protected function assertLeaveRequest(Company $company, HrLeaveRequest $leaveRequest): void
    {
        abort_if((string) $leaveRequest->company_id !== (string) $company->id, 404);
    }

    protected function assertPayrollRun(Company $company, HrPayrollRun $payrollRun): void
    {
        abort_if((string) $payrollRun->company_id !== (string) $company->id, 404);
    }

    protected function assertPayslip(Company $company, HrPayslip $payslip): void
    {
        abort_if((string) $payslip->company_id !== (string) $company->id, 404);
    }

    protected function assertPayrollRule(Company $company, HrPayrollRule $rule): void
    {
        abort_if((string) $rule->company_id !== (string) $company->id, 404);
    }

    protected function assertSalaryAdvance(Company $company, HrSalaryAdvance $advance): void
    {
        abort_if((string) $advance->company_id !== (string) $company->id, 404);
    }
}
