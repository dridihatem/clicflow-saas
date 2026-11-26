<?php

namespace App\Enums;

enum ProspectStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Engaged = 'engaged';
    case Verified = 'verified';
    case Converted = 'converted';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => __('prospect_status_new'),
            self::Contacted => __('prospect_status_contacted'),
            self::Engaged => __('prospect_status_engaged'),
            self::Verified => __('prospect_status_verified'),
            self::Converted => __('prospect_status_converted'),
            self::Archived => __('prospect_status_archived'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
