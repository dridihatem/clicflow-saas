<?php

namespace App\Enums;

enum ClientCampaignChannel: string
{
    case Emailing = 'emailing';
    case Sms = 'sms';
    case Social = 'social';
    case Web = 'web';
    case Phone = 'phone';
    case Print = 'print';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Emailing => __('client_campaign_channel_emailing'),
            self::Sms => __('client_campaign_channel_sms'),
            self::Social => __('client_campaign_channel_social'),
            self::Web => __('client_campaign_channel_web'),
            self::Phone => __('client_campaign_channel_phone'),
            self::Print => __('client_campaign_channel_print'),
            self::Other => __('client_campaign_channel_other'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
