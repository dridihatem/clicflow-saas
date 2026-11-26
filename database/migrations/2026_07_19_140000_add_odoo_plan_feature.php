<?php

use App\Models\Plan;
use App\Models\PlanLimit;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $limits = [
            'starter' => 0,
            'pro' => 0,
            'enterprise' => 1,
        ];

        foreach ($limits as $slug => $value) {
            $plan = Plan::query()->where('slug', $slug)->first();
            if (! $plan) {
                continue;
            }

            PlanLimit::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'feature_key' => 'odoo',
                ],
                ['limit_value' => $value],
            );
        }
    }

    public function down(): void
    {
        PlanLimit::query()->where('feature_key', 'odoo')->delete();
    }
};
