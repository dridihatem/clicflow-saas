<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_settings', 'cartesia_api_key_encrypted')) {
                $table->text('cartesia_api_key_encrypted')->nullable()->after('google_api_key_encrypted');
            }
            if (! Schema::hasColumn('platform_settings', 'cartesia_tts_model')) {
                $table->string('cartesia_tts_model', 64)->nullable()->after('cartesia_api_key_encrypted');
            }
            if (! Schema::hasColumn('platform_settings', 'cartesia_tts_voice')) {
                $table->string('cartesia_tts_voice', 64)->nullable()->after('cartesia_tts_model');
            }
        });

        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        $defaultVoice = 'db6b0ed5-d5d3-463d-ae85-518a07d3c2b4';

        foreach (DB::table('platform_settings')->get(['id', 'tts_provider', 'cartesia_tts_model', 'cartesia_tts_voice']) as $row) {
            $updates = [];
            if (in_array((string) ($row->tts_provider ?? ''), ['', 'auto', 'google'], true)) {
                $updates['tts_provider'] = 'cartesia';
            }
            if (trim((string) ($row->cartesia_tts_model ?? '')) === '') {
                $updates['cartesia_tts_model'] = 'sonic-3';
            }
            if (trim((string) ($row->cartesia_tts_voice ?? '')) === '') {
                $updates['cartesia_tts_voice'] = $defaultVoice;
            }
            if ($updates !== []) {
                DB::table('platform_settings')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $cols = array_values(array_filter([
                Schema::hasColumn('platform_settings', 'cartesia_api_key_encrypted') ? 'cartesia_api_key_encrypted' : null,
                Schema::hasColumn('platform_settings', 'cartesia_tts_model') ? 'cartesia_tts_model' : null,
                Schema::hasColumn('platform_settings', 'cartesia_tts_voice') ? 'cartesia_tts_voice' : null,
            ]));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
