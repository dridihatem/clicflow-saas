<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tour_dismissed_at')) {
                $table->timestamp('tour_dismissed_at')->nullable()->after('last_login_at');
            }
        });

        Schema::table('client_prospects', function (Blueprint $table) {
            if (! Schema::hasColumn('client_prospects', 'appointment_status')) {
                $table->string('appointment_status', 32)->nullable()->after('appointment_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'tour_dismissed_at')) {
                $table->dropColumn('tour_dismissed_at');
            }
        });

        Schema::table('client_prospects', function (Blueprint $table) {
            if (Schema::hasColumn('client_prospects', 'appointment_status')) {
                $table->dropColumn('appointment_status');
            }
        });
    }
};
