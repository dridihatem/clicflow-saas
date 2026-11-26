<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('recurrence_interval')->nullable()->after('due_date');
            $table->unsignedTinyInteger('recurrence_every_months')->nullable()->after('recurrence_interval');
            $table->date('recurrence_next_on')->nullable()->after('recurrence_every_months');
            $table->date('recurrence_ends_on')->nullable()->after('recurrence_next_on');
            $table->boolean('recurrence_active')->default(false)->after('recurrence_ends_on');
            $table->foreignUlid('recurrence_parent_id')->nullable()->after('recurrence_active')
                ->constrained('invoices')->nullOnDelete();

            $table->index(['company_id', 'recurrence_active', 'recurrence_next_on'], 'invoices_recurrence_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_recurrence_due_idx');
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn([
                'recurrence_interval',
                'recurrence_every_months',
                'recurrence_next_on',
                'recurrence_ends_on',
                'recurrence_active',
            ]);
        });
    }
};
