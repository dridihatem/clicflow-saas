<?php

namespace App\Http\Controllers\Hr;

use App\Enums\HrPayrollRuleCalculation;
use App\Enums\HrPayrollRuleKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Hr\Concerns\AuthorizesHrWorkspace;
use App\Models\HrPayrollRule;
use App\Services\HrSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use AuthorizesHrWorkspace;

    public function index(Request $request, HrSetupService $setup): View
    {
        $company = $this->authorizeHr($request);
        $setup->ensureDefaults($company);
        $setup->ensureExamplePayrollRules($company);

        $rules = HrPayrollRule::query()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('hr.settings.index', [
            'rules' => $rules,
            'currency' => flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency()),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $company = $this->authorizeHr($request);
        $data = $this->validatedRule($request);

        $maxOrder = (int) HrPayrollRule::query()->where('company_id', $company->id)->max('sort_order');

        HrPayrollRule::query()->create([
            ...$data,
            'company_id' => $company->id,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
        ]);

        return redirect()->route('hr.settings.index')->with('status', __('hr_rule_saved'));
    }

    public function updateRule(Request $request, HrPayrollRule $rule): RedirectResponse
    {
        $company = $this->authorizeHr($request);
        $this->assertPayrollRule($company, $rule);

        $rule->update($this->validatedRule($request));

        return redirect()->route('hr.settings.index')->with('status', __('hr_rule_saved'));
    }

    public function destroyRule(Request $request, HrPayrollRule $rule): RedirectResponse
    {
        $company = $this->authorizeHr($request);
        $this->assertPayrollRule($company, $rule);
        $rule->delete();

        return redirect()->route('hr.settings.index')->with('status', __('hr_rule_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRule(Request $request): array
    {
        if ($request->input('calculation') === HrPayrollRuleCalculation::PercentOfBase->value) {
            $request->merge(['fixed_amount' => null]);
        }
        if ($request->input('calculation') === HrPayrollRuleCalculation::Fixed->value) {
            $request->merge(['percent' => null]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:64'],
            'kind' => ['required', Rule::enum(HrPayrollRuleKind::class)],
            'calculation' => ['required', Rule::enum(HrPayrollRuleCalculation::class)],
            'percent' => [
                Rule::requiredIf(fn () => $request->input('calculation') === HrPayrollRuleCalculation::PercentOfBase->value),
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'fixed_amount' => [
                Rule::requiredIf(fn () => $request->input('calculation') === HrPayrollRuleCalculation::Fixed->value),
                'nullable',
                'string',
                'max:32',
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'include_on_payslip' => ['sometimes', 'boolean'],
        ]);

        $calc = HrPayrollRuleCalculation::from($data['calculation']);
        $fixedMinor = null;
        if ($calc === HrPayrollRuleCalculation::Fixed) {
            $currency = flowdesk_normalize_currency_code($request->user()->company?->default_currency ?? flowdesk_default_currency());
            $fixedMinor = flowdesk_decimal_to_minor($data['fixed_amount'] ?? null, $currency) ?? 0;
        }

        return [
            'name' => $data['name'],
            'code' => filled($data['code'] ?? null) ? $data['code'] : null,
            'kind' => $data['kind'],
            'calculation' => $data['calculation'],
            'percent' => $calc === HrPayrollRuleCalculation::PercentOfBase
                ? round((float) ($data['percent'] ?? 0), 4)
                : null,
            'fixed_minor' => $fixedMinor,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'is_active' => $request->boolean('is_active'),
            'include_on_payslip' => $request->boolean('include_on_payslip'),
        ];
    }
}
