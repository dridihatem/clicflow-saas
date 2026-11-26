<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_limits') && Schema::hasTable('plans')) {
            $enterpriseIds = DB::table('plans')->where('slug', 'enterprise')->pluck('id');
            if ($enterpriseIds->isNotEmpty()) {
                DB::table('plan_limits')
                    ->whereIn('plan_id', $enterpriseIds)
                    ->where('feature_key', 'odoo')
                    ->update(['limit_value' => 0, 'updated_at' => now()]);
            }
        }

        if (! Schema::hasTable('company_module_entitlements')) {
            return;
        }

        $companyIds = collect();

        if (Schema::hasTable('subscriptions') && Schema::hasTable('plans')) {
            $enterpriseIds = DB::table('plans')->where('slug', 'enterprise')->pluck('id');
            if ($enterpriseIds->isNotEmpty()) {
                $fromSubscriptions = DB::table('subscriptions')
                    ->whereIn('plan_id', $enterpriseIds)
                    ->where('status', 'active')
                    ->pluck('company_id');
                $companyIds = $companyIds->merge($fromSubscriptions);
            }
        }

        if (Schema::hasTable('companies') && Schema::hasTable('plans')) {
            $enterpriseIds = DB::table('plans')->where('slug', 'enterprise')->pluck('id');
            if ($enterpriseIds->isNotEmpty()) {
                $fromCompanies = DB::table('companies')
                    ->whereIn('plan_id', $enterpriseIds)
                    ->pluck('id');
                $companyIds = $companyIds->merge($fromCompanies);
            }
        }

        $now = now();
        foreach ($companyIds->unique()->filter()->values() as $companyId) {
            $exists = DB::table('company_module_entitlements')
                ->where('company_id', $companyId)
                ->where('module_key', 'odoo')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('company_module_entitlements')->insert([
                'id' => (string) Str::ulid(),
                'company_id' => $companyId,
                'module_key' => 'odoo',
                'source' => 'plan_migration',
                'marketplace_order_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_limits') && Schema::hasTable('plans')) {
            $enterpriseIds = DB::table('plans')->where('slug', 'enterprise')->pluck('id');
            if ($enterpriseIds->isNotEmpty()) {
                DB::table('plan_limits')
                    ->whereIn('plan_id', $enterpriseIds)
                    ->where('feature_key', 'odoo')
                    ->update(['limit_value' => 1, 'updated_at' => now()]);
            }
        }

        if (Schema::hasTable('company_module_entitlements')) {
            DB::table('company_module_entitlements')
                ->where('module_key', 'odoo')
                ->where('source', 'plan_migration')
                ->delete();
        }
    }
};
