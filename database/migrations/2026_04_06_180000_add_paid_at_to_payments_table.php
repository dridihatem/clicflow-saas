<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'paid_at')) {
            Schema::table('payments', function (Blueprint $table) {
                // Do not use after('payment_method') — that column is added in a later migration.
                $table->timestamp('paid_at')->nullable()->after('status');
            });
        }

        DB::table('payments')->whereNull('paid_at')->update([
            'paid_at' => DB::raw('created_at'),
        ]);

        if (! $this->hasIndex('payments', 'payments_company_id_paid_at_index')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['company_id', 'paid_at']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'paid_at')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if ($this->hasIndex('payments', 'payments_company_id_paid_at_index')) {
                $table->dropIndex(['company_id', 'paid_at']);
            }
            $table->dropColumn('paid_at');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (method_exists(Schema::class, 'hasIndex')) {
            return Schema::hasIndex($table, $indexName);
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");

            return collect($rows)->contains(fn ($row) => ($row->name ?? null) === $indexName);
        }

        $rows = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
