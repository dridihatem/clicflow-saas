<?php

namespace App\Support;

class ClicflowIcon
{
    /** @var array{icons: array, aliases: array}|null */
    private static ?array $data = null;

    /**
     * @return array{icons: array<string, array>, aliases: array<string, string>}
     */
    public static function data(): array
    {
        if (self::$data === null) {
            $path = resource_path('icons/clicflow.json');
            $decoded = json_decode((string) file_get_contents($path), true);
            self::$data = is_array($decoded) ? $decoded : ['icons' => [], 'aliases' => []];
        }

        return self::$data;
    }

    public static function resolve(?string $name): ?string
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        $aliases = self::data()['aliases'] ?? [];
        $icons = self::data()['icons'] ?? [];

        $candidates = self::candidates($name);
        foreach ($candidates as $candidate) {
            if (isset($aliases[$candidate])) {
                return (string) $aliases[$candidate];
            }
            if (isset($icons[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function candidates(string $name): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $lower = strtolower($raw);
        $out = [$lower, $raw];

        $stripped = preg_replace('/\b(fa-(?:solid|regular|brands|light|thin|duotone|sharp|fw|beat|fade|sm|lg|xl|2x|3x)|fa-fw)\b/', '', $lower) ?? $lower;
        $stripped = trim(preg_replace('/\s+/', ' ', $stripped) ?? $stripped);
        $out[] = $stripped;
        $out[] = preg_replace('/^fa-/', '', $stripped) ?? $stripped;

        foreach (preg_split('/\s+/', $lower) ?: [] as $part) {
            if (str_starts_with($part, 'fa-') && ! in_array($part, ['fa-solid', 'fa-regular', 'fa-brands', 'fa-fw', 'fa-light', 'fa-thin', 'fa-duotone', 'fa-sharp'], true)) {
                $out[] = $part;
                $out[] = substr($part, 3);
            }
        }

        return array_values(array_unique(array_filter($out, fn ($v) => is_string($v) && $v !== '')));
    }

    public static function svg(?string $name, string $class = 'cf-icon-svg'): string
    {
        $id = self::resolve((string) $name);
        if ($id === null) {
            return '';
        }

        $icon = self::data()['icons'][$id] ?? null;
        if (! is_array($icon)) {
            return '';
        }

        $parts = [];
        $i = 0;
        foreach (($icon['paths'] ?? []) as $d) {
            $i++;
            $delay = min(0.28, ($i - 1) * 0.06);
            $parts[] = '<path class="cf-icon-stroke" pathLength="1" style="animation-delay:'.$delay.'s" d="'.e((string) $d).'"/>';
        }
        foreach (($icon['circles'] ?? []) as $c) {
            $i++;
            $delay = min(0.28, ($i - 1) * 0.06);
            $fill = ! empty($c['fill']);
            $cls = $fill ? 'cf-icon-fill' : 'cf-icon-stroke';
            $fillAttr = $fill ? 'fill="currentColor" stroke="none"' : 'fill="none" pathLength="1"';
            $parts[] = '<circle class="'.$cls.'" '.$fillAttr.' style="animation-delay:'.$delay.'s" cx="'.e((string) ($c['cx'] ?? 0)).'" cy="'.e((string) ($c['cy'] ?? 0)).'" r="'.e((string) ($c['r'] ?? 1)).'"/>';
        }

        $class = trim($class);

        return '<svg class="'.e($class).'" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'.implode('', $parts).'</svg>';
    }
}
