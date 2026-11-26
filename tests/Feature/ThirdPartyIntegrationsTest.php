<?php

use App\Models\Client;
use App\Models\CommerceIntegration;
use App\Models\Project;
use App\Models\Prospect;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('company admin can open third-party integrations hub', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('settings.integrations'))
        ->assertOk()
        ->assertSee(__('integrations_hub_title'))
        ->assertSee(__('perfex_settings_title'))
        ->assertSee(__('Shopify sync'))
        ->assertSee(__('WooCommerce sync'))
        ->assertSee(__('odoo_settings_title'))
        ->assertSee(__('quickbooks_settings_title'))
        ->assertSee(__('trello_settings_title'))
        ->assertSee(__('notion_settings_title'))
        ->assertSee('data-integration-logo="perfex"', false)
        ->assertSee('data-integration-logo="shopify"', false)
        ->assertSee('data-integration-logo="woocommerce"', false)
        ->assertSee('data-integration-logo="odoo"', false)
        ->assertSee('data-integration-logo="quickbooks"', false)
        ->assertSee('data-integration-logo="trello"', false)
        ->assertSee('data-integration-logo="notion"', false)
        ->assertSee('data-integration-sheet', false);
});

test('legacy configure pages open the hub sheet', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('settings.trello'))
        ->assertRedirect(route('settings.integrations', ['configure' => 'trello']));
});

test('settings workspace lists third-party integrations card', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('settings.workspace'))
        ->assertOk()
        ->assertSee(__('integrations_hub_title'))
        ->assertSee(route('settings.integrations'), false);
});

test('company admin can save perfex settings and import leads and customers', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->put(route('settings.perfex.update'), [
            'base_url' => 'https://crm.example.test',
            'api_token' => 'perfex-token',
            'sync_prospects' => '1',
            'sync_clients' => '1',
            'sync_invoices' => '1',
            'sync_products' => '1',
        ])
        ->assertRedirect(route('settings.integrations', ['configure' => 'perfex']));

    expect(CommerceIntegration::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('provider', 'perfex')
        ->exists())->toBeTrue();

    Http::fake([
        'https://crm.example.test/api/customers*' => Http::response([
            ['company' => 'Acme Corp', 'email' => 'acme@example.com', 'phonenumber' => '+1000'],
        ]),
        'https://crm.example.test/api/leads*' => Http::response([
            ['name' => 'Lead One', 'email' => 'lead@example.com', 'company' => 'Lead Co'],
        ]),
        'https://crm.example.test/api/items*' => Http::response([]),
        'https://crm.example.test/api/invoices*' => Http::response([]),
    ]);

    $this->actingAs($user)
        ->post(route('settings.perfex.sync'))
        ->assertRedirect();

    expect(Client::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('email', 'acme@example.com')
        ->exists())->toBeTrue()
        ->and(Prospect::query()->withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('email', 'lead@example.com')
            ->exists())->toBeTrue();
});

test('company admin can save quickbooks settings and import customers', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->put(route('settings.quickbooks.update'), [
            'client_id' => 'qb-client',
            'client_secret' => 'qb-secret',
            'realm_id' => '12345',
            'refresh_token' => 'qb-refresh',
            'environment' => 'sandbox',
            'sync_clients' => '1',
            'sync_invoices' => '1',
            'sync_products' => '1',
            'sync_proposals' => '1',
        ])
        ->assertRedirect(route('settings.integrations', ['configure' => 'quickbooks']));

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (str_contains($request->url(), 'oauth2/v1/tokens/bearer')) {
            return Http::response(['access_token' => 'qb-access', 'refresh_token' => 'qb-refresh-2']);
        }

        $query = (string) $request['query'];
        if (str_contains($query, 'from Customer')) {
            return Http::response([
                'QueryResponse' => [
                    'Customer' => [[
                        'DisplayName' => 'QB Co',
                        'PrimaryEmailAddr' => ['Address' => 'qb@example.com'],
                        'PrimaryPhone' => ['FreeFormNumber' => '+1555'],
                    ]],
                ],
            ]);
        }

        return Http::response(['QueryResponse' => []]);
    });

    $this->actingAs($user)
        ->post(route('settings.quickbooks.sync'))
        ->assertRedirect();

    expect(Client::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
            ->where('email', 'qb@example.com')
            ->exists())->toBeTrue();
});

test('company admin can activate trello and sync a board into a project', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->put(route('settings.trello.update'), [
            'api_key' => 'trello-key',
            'api_token' => 'trello-token',
            'sync_enabled' => '1',
        ])
        ->assertRedirect(route('settings.integrations', ['configure' => 'trello']));

    Http::fake([
        'https://api.trello.com/1/members/me/boards*' => Http::response([
            ['id' => 'board-1', 'name' => 'Website rebuild', 'desc' => 'From Trello', 'closed' => false],
        ]),
        'https://api.trello.com/1/boards/board-1/cards*' => Http::response([
            ['id' => 'card-1', 'name' => 'Design homepage', 'desc' => '', 'closed' => false],
        ]),
        'https://api.trello.com/1/*' => Http::response(['id' => 'ok']),
    ]);

    $this->actingAs($user)
        ->post(route('settings.trello.sync'))
        ->assertRedirect();

    $project = Project::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('trello_board_id', 'board-1')
        ->first();

    expect($project)->not->toBeNull()
        ->and($project->title)->toBe('Website rebuild')
        ->and($project->tasks)->toHaveCount(1)
        ->and($project->tasks->first()->title)->toBe('Design homepage');
});

test('company admin can activate notion and sync a page into a project', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->put(route('settings.notion.update'), [
            'api_token' => 'secret_notion',
            'database_id' => '11111111111111111111111111111111',
            'sync_enabled' => '1',
        ])
        ->assertRedirect(route('settings.integrations', ['configure' => 'notion']));

    Http::fake([
        'https://api.notion.com/v1/databases/*/query' => Http::response([
            'results' => [[
                'id' => 'page-1',
                'properties' => [
                    'Name' => [
                        'type' => 'title',
                        'title' => [['plain_text' => 'Brand identity']],
                    ],
                ],
            ]],
        ]),
        'https://api.notion.com/v1/*' => Http::response(['id' => 'page-created']),
    ]);

    $this->actingAs($user)
        ->post(route('settings.notion.sync'))
        ->assertRedirect();

    expect(Project::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('notion_page_id', 'page-1')
        ->where('title', 'Brand identity')
        ->exists())->toBeTrue();
});

test('project show offers trello and notion sync when integrations are active', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    CommerceIntegration::query()->withoutGlobalScopes()->create([
        'company_id' => $user->company_id,
        'provider' => 'trello',
        'credentials' => ['api_key' => 'k', 'api_token' => 't'],
        'settings' => ['sync_enabled' => true],
        'auto_push' => false,
    ]);
    CommerceIntegration::query()->withoutGlobalScopes()->create([
        'company_id' => $user->company_id,
        'provider' => 'notion',
        'credentials' => ['api_token' => 'n', 'database_id' => 'db-1'],
        'settings' => ['sync_enabled' => true],
        'auto_push' => false,
    ]);

    $project = Project::factory()->create([
        'company_id' => $user->company_id,
        'created_by' => $user->id,
        'title' => 'Launch site',
    ]);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee(__('Sync to Trello'))
        ->assertSee(__('Sync to Notion'));
});
