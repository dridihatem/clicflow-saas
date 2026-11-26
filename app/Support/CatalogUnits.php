<?php

namespace App\Support;

final class CatalogUnits
{
    public const CUSTOM = '__custom__';

    /**
     * @return list<string>
     */
    public static function productValues(): array
    {
        return ['pcs', 'pack', 'box', 'set', 'pair', 'kg', 'g', 'l', 'ml', 'm', 'm2', 'm3'];
    }

    /**
     * @return list<string>
     */
    public static function serviceValues(): array
    {
        return ['hour', 'half_day', 'day', 'week', 'month', 'session', 'visit', 'mission', 'project', 'forfait', 'retainer', 'license'];
    }

    /**
     * @return list<string>
     */
    public static function allValues(): array
    {
        return array_values(array_unique([...self::productValues(), ...self::serviceValues()]));
    }

    /**
     * @return list<string>
     */
    public static function valuesForKind(string $kind): array
    {
        return $kind === 'service' ? self::serviceValues() : self::productValues();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForKind(string $kind): array
    {
        return array_map(
            static fn (string $value): array => [
                'value' => $value,
                'label' => self::label($value),
            ],
            self::valuesForKind($kind),
        );
    }

    public static function defaultForKind(string $kind): string
    {
        return $kind === 'service' ? 'hour' : 'pcs';
    }

    public static function isKnown(?string $unit): bool
    {
        if ($unit === null || $unit === '') {
            return false;
        }

        return in_array($unit, self::allValues(), true);
    }

    public static function label(?string $unit): string
    {
        if ($unit === null || trim($unit) === '') {
            return '';
        }

        $key = 'catalog_unit_'.$unit;
        $translated = __($key);

        return $translated === $key ? $unit : $translated;
    }

    /**
     * @return array<string, mixed>
     */
    public static function formConfig(
        string $kind,
        ?string $unit,
        string $name = '',
        string $description = '',
        bool $aiEnabled = false,
    ): array {
        return [
            'kind' => $kind === 'service' ? 'service' : 'product',
            'unit' => (string) ($unit ?? ''),
            'name' => $name,
            'description' => $description,
            'productUnits' => self::optionsForKind('product'),
            'serviceUnits' => self::optionsForKind('service'),
            'suggestUrl' => route('commerce.products.ai-copy'),
            'aiEnabled' => $aiEnabled,
            'errEmpty' => __('catalog_ai_empty'),
            'errNetwork' => __('Network error.'),
            'errPromptRequired' => __('catalog_ai_prompt_required'),
        ];
    }
}
