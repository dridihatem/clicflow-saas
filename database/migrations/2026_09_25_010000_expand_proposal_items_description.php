<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proposal_items') || ! Schema::hasColumn('proposal_items', 'description')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE proposal_items MODIFY description TEXT NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE proposal_items ALTER COLUMN description TYPE TEXT');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('proposal_items') || ! Schema::hasColumn('proposal_items', 'description')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE proposal_items MODIFY description VARCHAR(255) NOT NULL');
        }
    }
};
