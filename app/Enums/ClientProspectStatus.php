<?php

namespace App\Enums;

enum ClientProspectStatus: string
{
    case Nouveau = 'nouveau';
    case ARappeler = 'a_rappeler';
    case RdvProgramme = 'rdv_programme';
    case Perdu = 'perdu';
    case Gagne = 'gagne';
    case EnAttente = 'en_attente';
    case PasVenu = 'pas_venu';

    public function label(): string
    {
        return match ($this) {
            self::Nouveau => __('client_prospect_status_nouveau'),
            self::ARappeler => __('client_prospect_status_a_rappeler'),
            self::RdvProgramme => __('client_prospect_status_rdv_programme'),
            self::Perdu => __('client_prospect_status_perdu'),
            self::Gagne => __('client_prospect_status_gagne'),
            self::EnAttente => __('client_prospect_status_en_attente'),
            self::PasVenu => __('client_prospect_status_pas_venu'),
        };
    }

    /**
     * Full-width coloured badge classes (portal table, like the reference board).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Nouveau => 'bg-blue-600 text-white',
            self::ARappeler => 'bg-red-600 text-white',
            self::RdvProgramme => 'bg-amber-400 text-slate-900',
            self::Perdu => 'bg-slate-400 text-white',
            self::Gagne => 'bg-emerald-600 text-white',
            self::EnAttente => 'bg-orange-500 text-white',
            self::PasVenu => 'bg-zinc-900 text-white',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public static function tryFromLabel(string $value): ?self
    {
        $normalized = mb_strtolower(trim($value));
        $aliases = [
            'nouveau' => self::Nouveau,
            'new' => self::Nouveau,
            'a rappeler' => self::ARappeler,
            'à rappeler' => self::ARappeler,
            'a_rappeler' => self::ARappeler,
            'to call' => self::ARappeler,
            'rdv programme' => self::RdvProgramme,
            'rdv programmé' => self::RdvProgramme,
            'rdv_programme' => self::RdvProgramme,
            'appointment' => self::RdvProgramme,
            'perdu' => self::Perdu,
            'lost' => self::Perdu,
            'gagne' => self::Gagne,
            'gagné' => self::Gagne,
            'won' => self::Gagne,
            'en attente' => self::EnAttente,
            'en_attente' => self::EnAttente,
            'pending' => self::EnAttente,
            'pas venu' => self::PasVenu,
            'pas_venu' => self::PasVenu,
            'no show' => self::PasVenu,
            'no-show' => self::PasVenu,
        ];

        return $aliases[$normalized] ?? self::tryFrom($normalized);
    }
}
