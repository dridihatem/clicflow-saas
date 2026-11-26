<?php

namespace App\Enums;

enum ProspectVerifiedVia: string
{
    case EmailReply = 'email_reply';
    case Form = 'form';
    case Meeting = 'meeting';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::EmailReply => __('prospect_verified_via_email_reply'),
            self::Form => __('prospect_verified_via_form'),
            self::Meeting => __('prospect_verified_via_meeting'),
            self::Manual => __('prospect_verified_via_manual'),
        };
    }
}
