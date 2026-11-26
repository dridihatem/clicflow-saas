<?php

namespace App\Support;

use App\Enums\ProductKind;

final class CatalogKindMatcher
{
    /**
     * @param  array<string, mixed>  $signals
     * @return array{kind: string, unit: string, is_service: bool, track_stock: bool}
     */
    public static function classify(array $signals): array
    {
        $explicit = self::explicitKind($signals);
        $unitHint = self::normalizeUnit($signals['unit'] ?? null);
        $isService = $explicit === ProductKind::Service->value
            || ($explicit === null && self::looksLikeService($signals, $unitHint));

        $kind = $isService ? ProductKind::Service->value : ProductKind::Product->value;
        $allowed = CatalogUnits::valuesForKind($kind);
        $unit = $unitHint !== null && in_array($unitHint, $allowed, true)
            ? $unitHint
            : CatalogUnits::defaultForKind($kind);

        return [
            'kind' => $kind,
            'unit' => $unit,
            'is_service' => $isService,
            'track_stock' => ! $isService && filter_var($signals['track_stock'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array{kind: string, unit: string, is_service: bool, track_stock: bool}
     */
    public static function fromWooCommerce(array $remote): array
    {
        $categories = collect((array) data_get($remote, 'categories', []))
            ->map(fn ($row) => is_array($row) ? ($row['name'] ?? $row['slug'] ?? '') : $row)
            ->filter()
            ->all();
        $tags = collect((array) data_get($remote, 'tags', []))
            ->map(fn ($row) => is_array($row) ? ($row['name'] ?? $row['slug'] ?? '') : $row)
            ->filter()
            ->all();

        return self::classify([
            'name' => data_get($remote, 'name'),
            'description' => strip_tags((string) data_get($remote, 'description', data_get($remote, 'short_description', ''))),
            'sku' => data_get($remote, 'sku'),
            'type' => data_get($remote, 'type'),
            'virtual' => data_get($remote, 'virtual'),
            'downloadable' => data_get($remote, 'downloadable'),
            'categories' => $categories,
            'tags' => $tags,
            'unit' => data_get($remote, 'sold_individually') ? null : data_get($remote, 'unit'),
            'track_stock' => data_get($remote, 'manage_stock', true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array{kind: string, unit: string, is_service: bool, track_stock: bool}
     */
    public static function fromShopify(array $remote): array
    {
        $variants = collect((array) data_get($remote, 'variants', []));
        $requiresShipping = $variants->contains(fn ($variant) => is_array($variant) && ($variant['requires_shipping'] ?? true));

        return self::classify([
            'name' => data_get($remote, 'title'),
            'description' => strip_tags((string) data_get($remote, 'body_html', '')),
            'product_type' => data_get($remote, 'product_type'),
            'type' => data_get($remote, 'product_type'),
            'tags' => data_get($remote, 'tags'),
            'requires_shipping' => $variants->isNotEmpty() ? $requiresShipping : true,
            'track_stock' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $signals
     */
    private static function explicitKind(array $signals): ?string
    {
        if (array_key_exists('is_service', $signals) && $signals['is_service'] !== null && $signals['is_service'] !== '') {
            return filter_var($signals['is_service'], FILTER_VALIDATE_BOOL)
                ? ProductKind::Service->value
                : ProductKind::Product->value;
        }

        $kind = strtolower(trim((string) ($signals['kind'] ?? '')));
        if ($kind === ProductKind::Service->value) {
            return ProductKind::Service->value;
        }
        if ($kind === ProductKind::Product->value) {
            return ProductKind::Product->value;
        }

        $type = strtolower(trim((string) ($signals['type'] ?? $signals['product_type'] ?? '')));
        if (in_array($type, ['service', 'services', 'booking', 'appointment', 'consultation', 'mission'], true)) {
            return ProductKind::Service->value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $signals
     */
    private static function looksLikeService(array $signals, ?string $unitHint): bool
    {
        if ($unitHint !== null && in_array($unitHint, CatalogUnits::serviceValues(), true)) {
            return true;
        }

        $haystack = mb_strtolower(trim(implode(' ', [
            (string) ($signals['name'] ?? ''),
            (string) ($signals['description'] ?? ''),
            (string) ($signals['product_type'] ?? ''),
            (string) ($signals['type'] ?? ''),
            self::listText($signals['tags'] ?? []),
            self::listText($signals['categories'] ?? []),
        ])));

        $keywords = [
            'service', 'services', 'consulting', 'consultancy', 'maintenance', 'retainer',
            'hourly', 'per hour', 'mission', 'forfait', 'abonnement', 'subscription',
            'coaching', 'training', 'support', 'audit', 'design', 'development',
            'prestation', 'maintenance', 'entretien', 'conseil', 'formation',
        ];
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        $virtual = filter_var($signals['virtual'] ?? false, FILTER_VALIDATE_BOOL);
        $requiresShipping = $signals['requires_shipping'] ?? null;

        return $virtual && $requiresShipping === false;
    }

    private static function normalizeUnit(mixed $unit): ?string
    {
        $unit = strtolower(trim((string) $unit));
        if ($unit === '') {
            return null;
        }

        $aliases = [
            'piece' => 'pcs',
            'pieces' => 'pcs',
            'pc' => 'pcs',
            'unit' => 'pcs',
            'hours' => 'hour',
            'hr' => 'hour',
            'hrs' => 'hour',
            'heure' => 'hour',
            'heures' => 'hour',
            'jour' => 'day',
            'days' => 'day',
            'mois' => 'month',
            'months' => 'month',
            'projet' => 'project',
            'projects' => 'project',
            'paire' => 'pair',
            'pairs' => 'pair',
        ];

        $unit = $aliases[$unit] ?? $unit;

        return CatalogUnits::isKnown($unit) ? $unit : null;
    }

    private static function listText(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (! is_array($value)) {
            return '';
        }

        return collect($value)
            ->map(fn ($item) => is_array($item) ? (string) ($item['name'] ?? $item['slug'] ?? '') : (string) $item)
            ->filter()
            ->implode(' ');
    }
}
