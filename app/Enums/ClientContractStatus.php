<?php

namespace App\Enums;

enum ClientContractStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Signed = 'signed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('contract_status_draft'),
            self::Sent => __('contract_status_sent'),
            self::Signed => __('contract_status_signed'),
            self::Cancelled => __('contract_status_cancelled'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
