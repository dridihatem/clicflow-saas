<?php

namespace App\Enums;

enum HrSalaryAdvanceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deducted = 'deducted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('hr_advance_pending'),
            self::Approved => __('hr_advance_approved'),
            self::Rejected => __('hr_advance_rejected'),
            self::Deducted => __('hr_advance_deducted'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
            self::Approved => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
            self::Rejected => 'bg-rose-100 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
            self::Deducted => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }
}
