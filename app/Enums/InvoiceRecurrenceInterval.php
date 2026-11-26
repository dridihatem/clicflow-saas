<?php

namespace App\Enums;

enum InvoiceRecurrenceInterval: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Semiannual = 'semiannual';
    case Year = 'year';
    case Custom = 'custom';

    public function months(?int $custom = null): int
    {
        return match ($this) {
            self::Month => 1,
            self::Quarter => 3,
            self::Semiannual => 6,
            self::Year => 12,
            self::Custom => max(1, min(36, $custom ?? 1)),
        };
    }

    public function label(?int $everyMonths = null): string
    {
        return match ($this) {
            self::Month => __('invoice_recurrence.month'),
            self::Quarter => __('invoice_recurrence.quarter'),
            self::Semiannual => __('invoice_recurrence.semiannual'),
            self::Year => __('invoice_recurrence.year'),
            self::Custom => __('invoice_recurrence.custom_n', ['months' => max(1, $everyMonths ?? 1)]),
        };
    }
}
