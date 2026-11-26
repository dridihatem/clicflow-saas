<?php

namespace App\Enums;

enum RealEstatePropertyType: string
{
    case Residence = 'residence';
    case Duplex = 'duplex';
    case Triplex = 'triplex';
    case Terrain = 'terrain';
    case Bureau = 'bureau';

    public function label(): string
    {
        return match ($this) {
            self::Residence => __('Residence'),
            self::Duplex => __('Duplex'),
            self::Triplex => __('Triplex'),
            self::Terrain => __('Terrain'),
            self::Bureau => __('Bureau'),
        };
    }
}
