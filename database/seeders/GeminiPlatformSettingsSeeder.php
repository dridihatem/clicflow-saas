<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class GeminiPlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $row = PlatformSetting::query()->first() ?? new PlatformSetting;

        $row->gemini_model = 'gemini-3.5-flash';
        $row->gemini_tts_model = 'gemini-3.1-flash-tts-preview';
        $row->gemini_tts_voice = $row->gemini_tts_voice ?: 'Kore';

        $row->save();
    }
}
