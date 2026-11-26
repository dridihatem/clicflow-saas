<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_push_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('token', 512);
            $table->string('provider', 16)->default('expo'); // expo | fcm | apns
            $table->string('platform', 16)->nullable(); // ios | android | web
            $table->string('device_name', 120)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['token']);
            $table->index(['company_id', 'user_id']);
            $table->index(['user_id', 'provider']);
        });

        Schema::table('workspace_calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('workspace_calendar_events', 'push_notified_at')) {
                $table->timestamp('push_notified_at')->nullable()->after('updated_at');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'staff_due_push_sent_at')) {
                $table->timestamp('staff_due_push_sent_at')->nullable()->after('reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'staff_due_push_sent_at')) {
                $table->dropColumn('staff_due_push_sent_at');
            }
        });

        Schema::table('workspace_calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('workspace_calendar_events', 'push_notified_at')) {
                $table->dropColumn('push_notified_at');
            }
        });

        Schema::dropIfExists('device_push_tokens');
    }
};
