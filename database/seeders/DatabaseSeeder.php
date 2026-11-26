<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PlanSeeder::class);
        $this->call(CurrencyRateSeeder::class);
        $this->call(BusinessModulesSeeder::class);
        $this->call(MarketplaceModuleSeeder::class);
        $this->call(MarketplaceNotionModuleSeeder::class);
        $this->call(GeminiPlatformSettingsSeeder::class);
        $this->call(ThemeLibrarySeeder::class);
        $this->call(InvoicePdfTemplateLibrarySeeder::class);
        $this->call(PlatformAdminSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(ExampleDataSeeder::class);
        $this->call(DemoDataSeeder::class);
        $this->call(ClientEmailSampleSeeder::class);
    }
}
