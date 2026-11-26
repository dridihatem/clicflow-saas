<?php

namespace App\Http\Controllers\Hr;

use App\Enums\HrLeaveRequestStatus;
use App\Enums\HrPayrollRunStatus;
use App\Enums\HrSalaryAdvanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Hr\Concerns\AuthorizesHrWorkspace;
use App\Models\HrLeaveRequest;
use App\Models\HrLeaveType;
use App\Models\HrPayslip;
use App\Models\HrSalaryAdvance;
use App\Services\HrPayslipPdfService;
use App\Services\HrSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SelfServiceController extends Controller
{
    use AuthorizesHrWorkspace;

    public function leave(Request $request, HrSetupService $setup): View
    {
        $company = $this->authorizeHrSelfService($request);
        $setup->ensureDefaults($company);
        $employee = $this->linkedEmployee($request, $company);

        $requests = HrLeaveRequest::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->with(['leaveType:id,name,color'])
            ->latest()
            ->paginate(15);

        $leaveTypes = HrLeaveType::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('hr.me.leave', compact('requests', 'leaveTypes', 'employee'));
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        $company = $this->authorizeHrSelfService($request);
        $employee = $this->linkedEmployee($request, $company);

        $data = $request->validate([
            'leave_type_id' => ['required', 'ulid', Rule::exists('hr_leave_types', 'id')->where('company_id', $company->id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $starts = Carbon::parse($data['starts_on']);
        $ends = Carbon::parse($data['ends_on']);
        $days = max(1, $starts->diffInDays($ends) + 1);

        HrLeaveRequest::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $data['leave_type_id'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'days_count' => $days,
            'reason' => $data['reason'] ?? null,
            'status' => HrLeaveRequestStatus::Pending,
        ]);

        return redirect()->route('hr.me.leave')->with('status', __('hr_leave_request_saved'));
    }

    public function advances(Request $request): View
    {
        $company = $this->authorizeHrSelfService($request);
        $employee = $this->linkedEmployee($request, $company);

        $advances = HrSalaryAdvance::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->latest()
            ->paginate(15);

        return view('hr.me.advances', [
            'advances' => $advances,
            'employee' => $employee,
            'currency' => flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency()),
        ]);
    }

    public function storeAdvance(Request $request): RedirectResponse
    {
        $company = $this->authorizeHrSelfService($request);
        $employee = $this->linkedEmployee($request, $company);
        $currency = flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency());

        $data = $request->validate([
            'amount' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $amountMinor = flowdesk_decimal_to_minor($data['amount'], $currency);
        abort_if($amountMinor === null || $amountMinor <= 0, 422, __('hr_advance_invalid_amount'));

        HrSalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'requested_on' => now()->toDateString(),
            'reason' => $data['reason'] ?? null,
            'status' => HrSalaryAdvanceStatus::Pending,
        ]);

        return redirect()->route('hr.me.advances')->with('status', __('hr_advance_saved'));
    }

    public function payslips(Request $request): View
    {
        $company = $this->authorizeHrSelfService($request);
        $employee = $this->linkedEmployee($request, $company);

        $payslips = HrPayslip::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', function ($q) {
                $q->whereIn('status', [
                    HrPayrollRunStatus::Finalized->value,
                    HrPayrollRunStatus::Paid->value,
                ]);
            })
            ->with(['payrollRun:id,title,period_start,period_end,pay_date,status'])
            ->latest()
            ->paginate(15);

        return view('hr.me.payslips', compact('payslips', 'employee'));
    }

    public function downloadPayslip(Request $request, HrPayslip $payslip, HrPayslipPdfService $pdf): StreamedResponse
    {
        $company = $this->authorizeHrSelfService($request);
        $employee = $this->linkedEmployee($request, $company);
        $this->assertPayslip($company, $payslip);
        abort_if((string) $payslip->employee_id !== (string) $employee->id, 403);

        $payslip->loadMissing('payrollRun');
        $runStatus = $payslip->payrollRun?->status;
        $allowed = $runStatus === HrPayrollRunStatus::Finalized
            || $runStatus === HrPayrollRunStatus::Paid
            || $payslip->status === 'paid';
        abort_if(! $allowed, 403);

        return $pdf->stream($payslip);
    }
}
