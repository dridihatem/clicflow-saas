<?php

namespace App\Http\Controllers\Hr;

use App\Enums\HrSalaryAdvanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Hr\Concerns\AuthorizesHrWorkspace;
use App\Models\HrEmployee;
use App\Models\HrSalaryAdvance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdvanceController extends Controller
{
    use AuthorizesHrWorkspace;

    public function index(Request $request): View
    {
        $company = $this->authorizeHr($request);

        $advances = HrSalaryAdvance::query()
            ->where('company_id', $company->id)
            ->with(['employee:id,full_name,employee_number'])
            ->latest()
            ->paginate(20);

        $employees = HrEmployee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number']);

        return view('hr.advances.index', [
            'advances' => $advances,
            'employees' => $employees,
            'currency' => flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->authorizeHr($request);
        $currency = flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency());

        $data = $request->validate([
            'employee_id' => ['required', 'ulid', Rule::exists('hr_employees', 'id')->where('company_id', $company->id)],
            'amount' => ['required', 'string', 'max:32'],
            'requested_on' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $amountMinor = flowdesk_decimal_to_minor($data['amount'], $currency);
        abort_if($amountMinor === null || $amountMinor <= 0, 422, __('hr_advance_invalid_amount'));

        HrSalaryAdvance::query()->create([
            'company_id' => $company->id,
            'employee_id' => $data['employee_id'],
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'requested_on' => $data['requested_on'],
            'reason' => $data['reason'] ?? null,
            'status' => HrSalaryAdvanceStatus::Pending,
        ]);

        return redirect()->route('hr.advances.index')->with('status', __('hr_advance_saved'));
    }

    public function approve(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        $company = $this->authorizeHr($request);
        $this->assertSalaryAdvance($company, $advance);
        abort_if($advance->status !== HrSalaryAdvanceStatus::Pending, 422, __('hr_advance_not_pending'));

        $advance->update([
            'status' => HrSalaryAdvanceStatus::Approved,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', __('hr_advance_approved_notice'));
    }

    public function reject(Request $request, HrSalaryAdvance $advance): RedirectResponse
    {
        $company = $this->authorizeHr($request);
        $this->assertSalaryAdvance($company, $advance);
        abort_if($advance->status !== HrSalaryAdvanceStatus::Pending, 422, __('hr_advance_not_pending'));

        $advance->update([
            'status' => HrSalaryAdvanceStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', __('hr_advance_rejected_notice'));
    }
}
