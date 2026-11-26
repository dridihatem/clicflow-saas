<?php

namespace App\Enums;

enum InvoiceKind: string
{
    case Standard = 'standard';
    case Deposit = 'deposit';
    case Remainder = 'remainder';

    public function label(): string
    {
        return match ($this) {
            self::Standard => __('invoice_kind_standard'),
            self::Deposit => __('invoice_kind_deposit'),
            self::Remainder => __('invoice_kind_remainder'),
        };
    }
}
