<?php

namespace App\Models;

use App\Enums\HrPayrollRuleCalculation;
use App\Enums\HrPayrollRuleKind;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrPayrollRule extends Model
{
    use HasUlids, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kind' => HrPayrollRuleKind::class,
            'calculation' => HrPayrollRuleCalculation::class,
            'percent' => 'float',
            'fixed_minor' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'include_on_payslip' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function amountForBase(int $baseMinor): int
    {
        $calculation = $this->calculation instanceof HrPayrollRuleCalculation
            ? $this->calculation
            : HrPayrollRuleCalculation::tryFrom((string) $this->calculation);

        if ($calculation === HrPayrollRuleCalculation::Fixed) {
            return max(0, (int) ($this->fixed_minor ?? 0));
        }

        $percent = (float) ($this->percent ?? 0);
        if ($percent <= 0 || $baseMinor <= 0) {
            return 0;
        }

        return (int) round($baseMinor * ($percent / 100));
    }
}
