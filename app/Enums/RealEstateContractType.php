<?php

namespace App\Enums;

enum RealEstateContractType: string
{
    case Vente = 'vente';
    case Bail = 'bail';
    case BailVacance = 'bail_vacance';
    case Reservation = 'reservation';

    public function label(): string
    {
        return match ($this) {
            self::Vente => __('Sales contract'),
            self::Bail => __('Lease'),
            self::BailVacance => __('Vacation lease'),
            self::Reservation => __('Reservation'),
        };
    }
}
