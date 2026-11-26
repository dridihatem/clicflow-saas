<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_google_calendar_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('company_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('email');
            $table->text('refresh_token_encrypted');
            $table->timestamp('connected_at')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'is_default']);
            $table->index(['company_id', 'email']);
        });

        if (! Schema::hasTable('company_settings')) {
            return;
        }

        $rows = DB::table('company_settings')
            ->whereNotNull('google_calendar_refresh_token_encrypted')
            ->where('google_calendar_refresh_token_encrypted', '!=', '')
            ->get(['company_id', 'google_calendar_refresh_token_encrypted', 'google_calendar_connected_email', 'google_calendar_connected_at']);

        foreach ($rows as $row) {
            $email = trim((string) ($row->google_calendar_connected_email ?? ''));
            if ($email === '') {
                $email = 'unknown@google.calendar';
            }

            DB::table('company_google_calendar_accounts')->insert([
                'id' => (string) Str::ulid(),
                'company_id' => $row->company_id,
                'label' => null,
                'email' => $email,
                'refresh_token_encrypted' => $row->google_calendar_refresh_token_encrypted,
                'connected_at' => $row->google_calendar_connected_at,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('company_settings')
            ->whereNotNull('google_calendar_refresh_token_encrypted')
            ->update([
                'google_calendar_refresh_token_encrypted' => null,
                'google_calendar_connected_email' => null,
                'google_calendar_connected_at' => null,
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_google_calendar_accounts');
    }
};
