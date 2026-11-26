<?php

namespace App\Enums;

enum ProductKind: string
{
    case Product = 'product';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Product => __('catalog_kind_product'),
            self::Service => __('catalog_kind_service'),
        };
    }
}
