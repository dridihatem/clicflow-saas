<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('flowdesk.platform_admin_email', env('FLOWDESK_PLATFORM_ADMIN_EMAIL', 'platform@demo.local'));
        $password = (string) env('FLOWDESK_PLATFORM_ADMIN_PASSWORD', 'password');

        Admin::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Platform Admin',
                'password' => $password,
                'locale' => 'en',
                'email_verified_at' => now(),
            ],
        );
    }
}
