<?php

namespace App\Enums;

enum ClientAppointmentStatus: string
{
    case Confirme = 'confirme';
    case Venu = 'venu';
    case PasVenu = 'pas_venu';
    case Annule = 'annule';
    case Reporte = 'reporte';

    public function label(): string
    {
        return match ($this) {
            self::Confirme => __('client_appointment_status_confirme'),
            self::Venu => __('client_appointment_status_venu'),
            self::PasVenu => __('client_appointment_status_pas_venu'),
            self::Annule => __('client_appointment_status_annule'),
            self::Reporte => __('client_appointment_status_reporte'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Confirme => 'bg-amber-400 text-slate-900',
            self::Venu => 'bg-emerald-600 text-white',
            self::PasVenu => 'bg-zinc-900 text-white',
            self::Annule => 'bg-slate-400 text-white',
            self::Reporte => 'bg-sky-600 text-white',
        };
    }

    public function calendarColor(): string
    {
        return match ($this) {
            self::Confirme => '#f59e0b',
            self::Venu => '#059669',
            self::PasVenu => '#18181b',
            self::Annule => '#94a3b8',
            self::Reporte => '#0284c7',
        };
    }
}
