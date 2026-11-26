<?php

namespace App\Enums;

enum ClientBookingStatus: string
{
    case Nouvelle = 'nouvelle';
    case Confirmee = 'confirmee';
    case Effectuee = 'effectuee';
    case Annulee = 'annulee';
    case PasVenu = 'pas_venu';

    public function label(): string
    {
        return match ($this) {
            self::Nouvelle => __('client_booking_status_nouvelle'),
            self::Confirmee => __('client_booking_status_confirmee'),
            self::Effectuee => __('client_booking_status_effectuee'),
            self::Annulee => __('client_booking_status_annulee'),
            self::PasVenu => __('client_booking_status_pas_venu'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Nouvelle => 'bg-blue-600 text-white',
            self::Confirmee => 'bg-amber-400 text-slate-900',
            self::Effectuee => 'bg-emerald-600 text-white',
            self::Annulee => 'bg-slate-400 text-white',
            self::PasVenu => 'bg-zinc-900 text-white',
        };
    }

    public static function tryFromLabel(string $value): ?self
    {
        $n = mb_strtolower(trim($value));
        $n = str_replace(['é', 'è'], 'e', $n);

        return match ($n) {
            'nouvelle', 'new', 'nouveau' => self::Nouvelle,
            'confirmee', 'confirme', 'confirmed' => self::Confirmee,
            'effectuee', 'effectue', 'done', 'completed' => self::Effectuee,
            'annulee', 'annule', 'cancelled', 'canceled' => self::Annulee,
            'pas venu', 'pas_venu', 'no show', 'no-show' => self::PasVenu,
            default => self::tryFrom($n),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c): string => $c->value, self::cases());
    }
}
