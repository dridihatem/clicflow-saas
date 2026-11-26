<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\OdooSyncMap;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ModuleEntitlementService;
use App\Services\Odoo\OdooConfigService;
use App\Services\Odoo\OdooSyncService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
});

function assignEnterprisePlan(Company $company): void
{
    $plan = Plan::query()->where('slug', 'enterprise')->firstOrFail();
    $company->forceFill(['plan_id' => $plan->id])->saveQuietly();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);
    app(ModuleEntitlementService::class)->grant($company, 'odoo', 'marketplace');
}

function configureOdoo(Company $company): void
{
    app(OdooConfigService::class)->saveFromRequest($company, [
        'enabled' => true,
        'url' => 'https://odoo.example.test',
        'database' => 'flowdesk',
        'username' => 'admin@example.com',
        'api_key' => 'secret-api-key',
        'auto_push' => false,
        'sync_clients' => true,
        'sync_projects' => false,
        'sync_invoices' => false,
        'sync_proposals' => false,
    ]);
    $company->refresh();
    $company->load('settings');
}

test('company admin can open odoo settings page', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    assignEnterprisePlan($user->company);

    $this->actingAs($user)
        ->get(route('settings.odoo'))
        ->assertRedirect(route('settings.integrations', ['configure' => 'odoo']));

    $this->actingAs($user)
        ->get(route('settings.integrations', ['configure' => 'odoo']))
        ->assertOk()
        ->assertSee(__('odoo_settings_title'))
        ->assertSee(__('odoo_enable'));
});

test('non enterprise plan cannot open odoo settings', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $plan = Plan::query()->where('slug', 'pro')->firstOrFail();
    $user->company->forceFill(['plan_id' => $plan->id])->saveQuietly();
    Subscription::factory()->create([
        'company_id' => $user->company_id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('settings.odoo'))
        ->assertForbidden();
});

test('company admin can save odoo connection settings', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    assignEnterprisePlan($company);

    $this->actingAs($user)
        ->put(route('settings.odoo.update'), [
            'enabled' => '1',
            'url' => 'https://odoo.example.test',
            'database' => 'flowdesk',
            'username' => 'admin@example.com',
            'api_key' => 'secret-api-key',
            'auto_push' => '1',
            'sync_clients' => '1',
            'sync_projects' => '1',
            'sync_invoices' => '1',
            'sync_proposals' => '1',
        ])
        ->assertRedirect(route('settings.integrations', ['configure' => 'odoo']));

    $form = app(OdooConfigService::class)->toFormArray($company->fresh()->load('settings'));
    expect($form['connected'])->toBeTrue()
        ->and($form['database'])->toBe('flowdesk')
        ->and($form['has_api_key'])->toBeTrue();
});

test('odoo sync pulls clients from json rpc and maps them', function () {
    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        $body = $request->data();
        $method = $body['params']['method'] ?? null;

        if ($method === 'authenticate') {
            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => 7]);
        }

        if ($method === 'execute_kw') {
            $args = $body['params']['args'] ?? [];
            $model = $args[3] ?? null;
            $op = $args[4] ?? null;
            if ($model === 'res.partner' && $op === 'search_read') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [[
                        'id' => 42,
                        'name' => 'Acme Corp',
                        'email' => 'acme@example.com',
                        'phone' => '+1 555',
                        'street' => '1 Main',
                        'city' => 'Paris',
                        'zip' => '75001',
                        'write_date' => '2026-07-01 10:00:00',
                    ]],
                ]);
            }

            if ($model === 'res.partner' && $op === 'create') {
                return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => 99]);
            }

            if ($model === 'res.partner' && $op === 'write') {
                return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => true]);
            }
        }

        return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => null]);
    });

    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    assignEnterprisePlan($company);
    configureOdoo($company);

    Client::factory()->create([
        'company_id' => $company->id,
        'name' => 'Local Client',
        'email' => 'local@example.com',
    ]);

    $result = app(OdooSyncService::class)->syncCompany($company->fresh()->load('settings'));

    expect($result['pulled']['clients'])->toBe(1);

    $client = Client::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('email', 'acme@example.com')
        ->first();

    expect($client)->not->toBeNull()
        ->and($client->name)->toBe('Acme Corp');

    $map = OdooSyncMap::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('entity_type', OdooSyncMap::ENTITY_CLIENT)
        ->where('odoo_id', 42)
        ->first();

    expect($map)->not->toBeNull()
        ->and((string) $map->local_id)->toBe((string) $client->id);
});

test('odoo test connection authenticates against json rpc', function () {
    Http::fake([
        'https://odoo.example.test/jsonrpc' => Http::sequence()
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => 7])
            ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['server_version' => '17.0']]),
    ]);

    $user = User::factory()->create();
    $user->assignRole('company_admin');
    assignEnterprisePlan($user->company);
    configureOdoo($user->company);

    $this->actingAs($user)
        ->post(route('settings.odoo.test'))
        ->assertRedirect()
        ->assertSessionHas('status');
});
