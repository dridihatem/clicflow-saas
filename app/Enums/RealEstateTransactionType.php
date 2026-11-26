<?php

namespace App\Enums;

enum RealEstateTransactionType: string
{
    case Achat = 'achat';
    case Vente = 'vente';
    case Location = 'location';
    case LocationVacance = 'location_vacance';

    public function label(): string
    {
        return match ($this) {
            self::Achat => __('Achat'),
            self::Vente => __('Vente'),
            self::Location => __('Location'),
            self::LocationVacance => __('Location vacance'),
        };
    }
}
