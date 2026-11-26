<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TenantStorageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo logins (password: password unless overridden by env for platform admin):
 *
 * - platform@demo.local  → Admin model (/admin/login)
 * - admin@demo.local     → company_admin  (workspace demo)
 * - team@demo.local      → team_member
 * - client@demo.local    → client
 * - provider@demo.local  → business_provider
 * - test@example.com     → company_admin (legacy)
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlatformAdminSeeder::class);

        $company = Company::query()->firstOrCreate(
            ['subdomain' => 'demo'],
            [
                'name' => 'ClicFlow Demo',
                'slug' => 'demo',
                'default_locale' => 'en',
                'default_currency' => 'TND',
                'country' => 'TN',
                'contact_email' => 'admin@demo.local',
                'is_enabled' => true,
            ],
        );

        $company->forceFill([
            'name' => 'ClicFlow Demo',
            'is_enabled' => true,
        ])->save();

        CompanySetting::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'branding' => ['logo_url' => null],
                'theme' => [
                    'mode' => 'light',
                    'layout_type' => 'sidebar',
                    'primary_color' => '#4f46e5',
                    'secondary_color' => '#64748b',
                    'font_family' => 'Figtree',
                    'dark_mode' => false,
                ],
                'dashboard' => [],
            ],
        );

        $pro = Plan::query()->where('slug', 'pro')->first();
        if ($pro) {
            Subscription::query()->updateOrCreate(
                ['company_id' => $company->id],
                [
                    'plan_id' => $pro->id,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => now()->addMonth(),
                ],
            );
            $company->forceFill(['plan_id' => $pro->id])->saveQuietly();
        }

        app(TenantStorageService::class)->bootstrap($company);

        $password = Hash::make('password');

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@demo.local'],
            [
                'name' => 'Demo Admin',
                'password' => $password,
                'company_id' => $company->id,
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );
        $admin->forceFill([
            'company_id' => $company->id,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();
        $admin->syncRoles(['company_admin']);

        $team = User::query()->firstOrCreate(
            ['email' => 'team@demo.local'],
            [
                'name' => 'Demo Team Member',
                'password' => $password,
                'company_id' => $company->id,
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );
        $team->forceFill(['company_id' => $company->id])->save();
        $team->syncRoles(['team_member']);

        $clientUser = User::query()->firstOrCreate(
            ['email' => 'client@demo.local'],
            [
                'name' => 'Demo Client',
                'password' => $password,
                'company_id' => $company->id,
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );
        $clientUser->forceFill(['company_id' => $company->id])->save();
        $clientUser->syncRoles(['client']);

        Client::query()->firstOrCreate(
            ['company_id' => $company->id, 'email' => 'client@demo.local'],
            [
                'user_id' => $clientUser->id,
                'name' => 'Demo Client Co',
                'phone' => '+216 70 000 000',
                'address' => ['line1' => 'Demo Street', 'city' => 'Tunis', 'country' => 'TN'],
            ],
        );

        $providerUser = User::query()->firstOrCreate(
            ['email' => 'provider@demo.local'],
            [
                'name' => 'Demo Provider',
                'password' => $password,
                'company_id' => $company->id,
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );
        $providerUser->forceFill(['company_id' => $company->id])->save();
        $providerUser->syncRoles(['business_provider']);

        Provider::query()->firstOrCreate(
            ['user_id' => $providerUser->id],
            [
                'company_id' => $company->id,
                'name' => 'Demo Provider',
                'email' => $providerUser->email,
                'commission_rate' => 0.1,
            ],
        );

        $legacy = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => $password,
                'company_id' => $company->id,
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );
        $legacy->forceFill(['company_id' => $company->id])->save();
        $legacy->syncRoles(['company_admin']);
    }
}
