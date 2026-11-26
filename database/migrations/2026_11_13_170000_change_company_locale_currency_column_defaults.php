<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change column defaults only. Existing company rows are left unchanged.
     * MySQL-specific ALTER is skipped on SQLite (tests).
     */
    public function up(): void
    {
        $this->modifyDefaults('fr', 'EUR');
    }

    public function down(): void
    {
        $this->modifyDefaults('en', 'USD');
    }

    private function modifyDefaults(string $locale, string $currency): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return;
        }

        if (Schema::hasColumn('companies', 'default_locale')) {
            DB::statement("ALTER TABLE companies MODIFY default_locale VARCHAR(10) NOT NULL DEFAULT '".str_replace("'", "''", $locale)."'");
        }
        if (Schema::hasColumn('companies', 'default_currency')) {
            DB::statement("ALTER TABLE companies MODIFY default_currency VARCHAR(3) NOT NULL DEFAULT '".str_replace("'", "''", $currency)."'");
        }
    }
};
