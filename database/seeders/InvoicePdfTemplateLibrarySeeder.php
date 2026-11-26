<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class InvoicePdfTemplateLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'corporate_blue' => [
                'label' => 'Corporate Blue',
                'primary_color' => '#0f172a',
                'accent_color' => '#2563eb',
                'table_header_bg' => '#eff6ff',
                'table_header_text' => '#1e40af',
                'border_color' => '#bfdbfe',
                'text_color' => '#1e293b',
                'muted_color' => '#64748b',
                'totals_grand_bg' => '#eff6ff',
                'totals_grand_text' => '#1d4ed8',
                'pay_box_bg' => '#f8fafc',
                'panel_bg' => '#f8fafc',
                'compact_header' => true,
            ],
            'teal_professional' => [
                'label' => 'Teal Professional',
                'primary_color' => '#134e4a',
                'accent_color' => '#0d9488',
                'table_header_bg' => '#f0fdfa',
                'table_header_text' => '#0f766e',
                'border_color' => '#99f6e4',
                'text_color' => '#134e4a',
                'muted_color' => '#5f857e',
                'totals_grand_bg' => '#ccfbf1',
                'totals_grand_text' => '#0f766e',
                'pay_box_bg' => '#f0fdfa',
                'panel_bg' => '#f0fdfa',
                'compact_header' => true,
            ],
            'slate_minimal' => [
                'label' => 'Slate Minimal',
                'primary_color' => '#18181b',
                'accent_color' => '#52525b',
                'table_header_bg' => '#f4f4f5',
                'table_header_text' => '#3f3f46',
                'border_color' => '#d4d4d8',
                'text_color' => '#27272a',
                'muted_color' => '#71717a',
                'totals_grand_bg' => '#fafafa',
                'totals_grand_text' => '#18181b',
                'pay_box_bg' => '#fafafa',
                'panel_bg' => '#fafafa',
                'compact_header' => true,
            ],
            'forest_ledger' => [
                'label' => 'Forest Ledger',
                'primary_color' => '#14532d',
                'accent_color' => '#16a34a',
                'table_header_bg' => '#f0fdf4',
                'table_header_text' => '#166534',
                'border_color' => '#bbf7d0',
                'text_color' => '#14532d',
                'muted_color' => '#4b7c5c',
                'totals_grand_bg' => '#dcfce7',
                'totals_grand_text' => '#15803d',
                'pay_box_bg' => '#f0fdf4',
                'panel_bg' => '#f0fdf4',
                'compact_header' => true,
            ],
            'amber_studio' => [
                'label' => 'Amber Studio',
                'primary_color' => '#78350f',
                'accent_color' => '#d97706',
                'table_header_bg' => '#fffbeb',
                'table_header_text' => '#92400e',
                'border_color' => '#fde68a',
                'text_color' => '#78350f',
                'muted_color' => '#b45309',
                'totals_grand_bg' => '#fef3c7',
                'totals_grand_text' => '#b45309',
                'pay_box_bg' => '#fffbeb',
                'panel_bg' => '#fffbeb',
                'compact_header' => true,
            ],
            'indigo_boardroom' => [
                'label' => 'Indigo Boardroom',
                'primary_color' => '#312e81',
                'accent_color' => '#4f46e5',
                'table_header_bg' => '#eef2ff',
                'table_header_text' => '#3730a3',
                'border_color' => '#c7d2fe',
                'text_color' => '#1e1b4b',
                'muted_color' => '#6366a8',
                'totals_grand_bg' => '#e0e7ff',
                'totals_grand_text' => '#4338ca',
                'pay_box_bg' => '#eef2ff',
                'panel_bg' => '#eef2ff',
                'compact_header' => false,
            ],
            'rose_atelier' => [
                'label' => 'Rose Atelier',
                'primary_color' => '#881337',
                'accent_color' => '#e11d48',
                'table_header_bg' => '#fff1f2',
                'table_header_text' => '#9f1239',
                'border_color' => '#fecdd3',
                'text_color' => '#4c0519',
                'muted_color' => '#9f6b78',
                'totals_grand_bg' => '#ffe4e6',
                'totals_grand_text' => '#be123c',
                'pay_box_bg' => '#fff1f2',
                'panel_bg' => '#fff1f2',
                'compact_header' => true,
            ],
            'charcoal_ink' => [
                'label' => 'Charcoal Ink',
                'primary_color' => '#0a0a0a',
                'accent_color' => '#171717',
                'table_header_bg' => '#f5f5f5',
                'table_header_text' => '#404040',
                'border_color' => '#a3a3a3',
                'text_color' => '#171717',
                'muted_color' => '#737373',
                'totals_grand_bg' => '#e5e5e5',
                'totals_grand_text' => '#0a0a0a',
                'pay_box_bg' => '#fafafa',
                'panel_bg' => '#fafafa',
                'compact_header' => false,
            ],
        ];

        $row = PlatformSetting::query()->first() ?? new PlatformSetting;
        $library = is_array($row->invoice_pdf_library) ? $row->invoice_pdf_library : [];

        foreach ($templates as $key => $preset) {
            $library[$key] = $preset;
        }

        $row->invoice_pdf_library = $library;
        $row->save();
    }
}
