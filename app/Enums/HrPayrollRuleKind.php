<?php

namespace App\Enums;

enum HrPayrollRuleKind: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Earning => __('hr_rule_kind_earning'),
            self::Deduction => __('hr_rule_kind_deduction'),
        };
    }
}
