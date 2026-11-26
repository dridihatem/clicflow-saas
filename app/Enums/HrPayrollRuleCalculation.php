<?php

namespace App\Enums;

enum HrPayrollRuleCalculation: string
{
    case PercentOfBase = 'percent_of_base';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PercentOfBase => __('hr_rule_calc_percent'),
            self::Fixed => __('hr_rule_calc_fixed'),
        };
    }
}
