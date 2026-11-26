<?php

namespace App\Enums;

enum ClientCampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('client_campaign_status_draft'),
            self::Scheduled => __('client_campaign_status_scheduled'),
            self::Running => __('client_campaign_status_running'),
            self::Paused => __('client_campaign_status_paused'),
            self::Completed => __('client_campaign_status_completed'),
            self::Cancelled => __('client_campaign_status_cancelled'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-200 text-slate-800',
            self::Scheduled => 'bg-sky-600 text-white',
            self::Running => 'bg-emerald-600 text-white',
            self::Paused => 'bg-amber-400 text-slate-900',
            self::Completed => 'bg-indigo-600 text-white',
            self::Cancelled => 'bg-slate-500 text-white',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
