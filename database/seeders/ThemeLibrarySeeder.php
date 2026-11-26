<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class ThemeLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'ocean_breeze' => [
                'label' => 'Ocean Breeze',
                'primary_color' => '#0ea5e9',
                'secondary_color' => '#334155',
                'background_color' => '#f0f9ff',
                'font_family' => 'DM Sans',
                'dark_mode' => 'light',
            ],
            'forest_mint' => [
                'label' => 'Forest Mint',
                'primary_color' => '#059669',
                'secondary_color' => '#1e293b',
                'background_color' => '#f0fdf4',
                'font_family' => 'Figtree',
                'dark_mode' => 'light',
            ],
            'sunset_coral' => [
                'label' => 'Sunset Coral',
                'primary_color' => '#f97316',
                'secondary_color' => '#7c2d12',
                'background_color' => '#fff7ed',
                'font_family' => 'Nunito',
                'dark_mode' => 'light',
            ],
            'midnight_indigo' => [
                'label' => 'Midnight Indigo',
                'primary_color' => '#818cf8',
                'secondary_color' => '#94a3b8',
                'background_color' => '#0f172a',
                'font_family' => 'Inter',
                'dark_mode' => 'dark',
            ],
            'rosewood' => [
                'label' => 'Rosewood',
                'primary_color' => '#e11d48',
                'secondary_color' => '#4c0519',
                'background_color' => '#fff1f2',
                'font_family' => 'Source Sans 3',
                'dark_mode' => 'light',
            ],
            'slate_studio' => [
                'label' => 'Slate Studio',
                'primary_color' => '#475569',
                'secondary_color' => '#94a3b8',
                'background_color' => '#f8fafc',
                'font_family' => 'Inter',
                'dark_mode' => 'light',
            ],
            'amber_night' => [
                'label' => 'Amber Night',
                'primary_color' => '#fbbf24',
                'secondary_color' => '#e2e8f0',
                'background_color' => '#18181b',
                'font_family' => 'DM Sans',
                'dark_mode' => 'dark',
            ],
            'teal_wave' => [
                'label' => 'Teal Wave',
                'primary_color' => '#14b8a6',
                'secondary_color' => '#0f766e',
                'background_color' => '#f0fdfa',
                'font_family' => 'Nunito',
                'dark_mode' => 'light',
            ],
            'graphite_pro' => [
                'label' => 'Graphite Pro',
                'primary_color' => '#a3a3a3',
                'secondary_color' => '#737373',
                'background_color' => '#171717',
                'font_family' => 'Figtree',
                'dark_mode' => 'dark',
            ],
        ];

        $row = PlatformSetting::query()->first() ?? new PlatformSetting;
        $library = is_array($row->theme_library) ? $row->theme_library : [];

        foreach ($templates as $key => $preset) {
            $library[$key] = $preset;
        }

        $row->theme_library = $library;
        $row->save();
    }
}
