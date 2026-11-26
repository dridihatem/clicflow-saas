<?php

namespace App\Enums;

enum WorkspaceSubscriptionOrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Paid => __('Paid'),
            self::Cancelled => __('Cancelled'),
            self::Failed => __('Failed'),
        };
    }
}
