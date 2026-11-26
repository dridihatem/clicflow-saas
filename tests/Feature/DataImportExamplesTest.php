<?php

use App\Models\User;
use App\Services\DataImportService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('data import index shows types and example downloads', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('import.index'))
        ->assertOk()
        ->assertSee(__('data_import_download_example'))
        ->assertSee('name')
        ->assertSee(route('import.example', 'clients'), false);
});

test('data import example csv downloads for each type', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $import = app(DataImportService::class);

    foreach (DataImportService::TYPES as $type) {
        $response = $this->actingAs($user)->get(route('import.example', $type));
        $response->assertOk();
        expect($response->streamedContent())->toContain($import->columnsFor($type)[0]['key']);
    }
});

test('data import upload page shows columns and prefilled example', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('import.preview.form', 'products'))
        ->assertOk()
        ->assertSee(__('data_import_columns_heading'))
        ->assertSee('sku')
        ->assertSee('stock_qty')
        ->assertSee('Website audit');
});

test('data import preview classifies matched and ignored columns', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $csv = "name,email,phone,extra_note\nAcme,a@example.com,+1000,ignore-me\n";

    $this->actingAs($user)
        ->post(route('import.preview', 'prospects'), [
            'import_input' => $csv,
        ])
        ->assertOk()
        ->assertSee('name="mapping[3]"', false)
        ->assertSee('extra_note')
        ->assertSee(__('data_import_mapping_ignore'));
});

test('client import maps société, contact, catégorie and statut headers', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $existing = \App\Models\Client::factory()->create([
        'company_id' => $user->company_id, 'email' => 'exist@example.test', 'status' => 'potential', 'category' => 'Old',
    ]);

    $csv = "Nom,E-mail,Société,Contact principal,Catégorie,Statut\n"
        ."Alpha,alpha@example.test,Alpha SARL,Ali Ben Salah,PME,Inactif\n"
        ."Beta,beta@example.test,,,,nimportequoi\n"
        ."Exist,exist@example.test,Exist SA,,,\n";

    $this->actingAs($user)->post(route('import.preview', 'clients'), ['import_input' => $csv])
        ->assertOk()
        ->assertSee('company_name')
        ->assertSee('contact_name');

    $this->actingAs($user)->post(route('import.store', 'clients'), ['raw' => $csv])->assertRedirect(route('import.index'));

    $q = fn (string $e) => \App\Models\Client::query()->withoutGlobalScopes()->where('company_id', $user->company_id)->where('email', $e)->first();
    $alpha = $q('alpha@example.test');
    expect($alpha->company_name)->toBe('Alpha SARL')
        ->and($alpha->contact_name)->toBe('Ali Ben Salah')
        ->and($alpha->category)->toBe('PME')
        ->and($alpha->status)->toBe(\App\Enums\ClientStatus::Inactive);
    expect($q('beta@example.test')->status)->toBe(\App\Enums\ClientStatus::Active);
    $existing->refresh();
    expect($existing->company_name)->toBe('Exist SA')
        ->and($existing->category)->toBe('Old')
        ->and($existing->status)->toBe(\App\Enums\ClientStatus::Potential);
});

test('client status text maps french and english values', function () {
    $s = app(DataImportService::class);
    expect($s->clientStatusFromText('Actif'))->toBe(\App\Enums\ClientStatus::Active)
        ->and($s->clientStatusFromText('INACTIVE'))->toBe(\App\Enums\ClientStatus::Inactive)
        ->and($s->clientStatusFromText('prospect'))->toBe(\App\Enums\ClientStatus::Potential)
        ->and($s->clientStatusFromText(''))->toBeNull()
        ->and($s->clientStatusFromText('xyz'))->toBeNull();
});

function hatemStyleCsv(): string
{
    return "#,Téléphone,Société,Contact principal,E-mail principal,Actif,Catégories,Date de création\n"
        ."1,+216 20 000 001,Alpha SARL,Ali Ben Salah,alpha@example.test,1,PME,15/03/2024\n"
        ."2,+216 20 000 002,,Sara Trabelsi,sara@example.test,non,Particulier,2024-04-02\n"
        ."3,,Gamma SA,,,oui,Startup,pas une date\n";
}

test('client preview auto-detects plural, actif and e-mail principal headers with name fallback', function () {
    $s = app(DataImportService::class);
    $rows = $s->parseDelimited(hatemStyleCsv());
    $c = $s->classifyHeaders('clients', array_keys($rows[0]));

    expect($c['mapping'])->toMatchArray([
        'téléphone' => 'phone',
        'société' => 'company_name',
        'contact_principal' => 'contact_name',
        'e_mail_principal' => 'email',
        'actif' => 'status',
        'catégories' => 'category',
        'date_de_création' => 'created_at',
    ])->and($c['mapping'])->not->toHaveKey('#')
        ->and($c['missing_required'])->toBe([])
        ->and($c['notes'])->toContain('name_fallback');

    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $this->actingAs($user)->post(route('import.preview', 'clients'), ['import_input' => hatemStyleCsv()])
        ->assertOk()
        ->assertSee('name="mapping[0]"', false)
        ->assertSee(__('data_import_mapping_ignore'))
        ->assertSee(__('data_import_mapping_name_fallback'));
});

test('client import uses auto mapping, name fallback, actif booleans and creation date', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $s = app(DataImportService::class);
    $rows = $s->parseDelimited(hatemStyleCsv());
    $auto = $s->classifyHeaders('clients', array_keys($rows[0]))['mapping'];
    $mapping = array_map(fn ($h) => $auto[$h] ?? '', array_keys($rows[0]));

    $this->actingAs($user)->post(route('import.store', 'clients'), ['raw' => hatemStyleCsv(), 'mapping' => $mapping])
        ->assertRedirect(route('import.index'));

    $all = \App\Models\Client::query()->withoutGlobalScopes()->where('company_id', $user->company_id)->get()->keyBy('name');
    expect($all->keys()->sort()->values()->all())->toBe(['Alpha SARL', 'Gamma SA', 'Sara Trabelsi']);
    expect($all['Alpha SARL']->status)->toBe(\App\Enums\ClientStatus::Active)
        ->and($all['Alpha SARL']->contact_name)->toBe('Ali Ben Salah')
        ->and($all['Alpha SARL']->category)->toBe('PME')
        ->and($all['Alpha SARL']->created_at->format('Y-m-d'))->toBe('2024-03-15')
        ->and($all['Sara Trabelsi']->status)->toBe(\App\Enums\ClientStatus::Inactive)
        ->and($all['Sara Trabelsi']->created_at->format('Y-m-d'))->toBe('2024-04-02')
        ->and($all['Gamma SA']->created_at->isToday())->toBeTrue();
});

test('manual mapping overrides detection and rejects duplicate fields', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $csv = "Col A,Col B,Col C\nAcme,acme@example.test,VIP\n";

    $this->actingAs($user)->post(route('import.store', 'clients'), ['raw' => $csv, 'mapping' => ['name', 'name', '']])
        ->assertOk()
        ->assertSee(e(__('data_import_mapping_duplicate', ['field' => __('data_import_field_name')])), false);
    expect(\App\Models\Client::query()->withoutGlobalScopes()->where('company_id', $user->company_id)->count())->toBe(0);

    $this->actingAs($user)->post(route('import.store', 'clients'), ['raw' => $csv, 'mapping' => ['name', 'email', 'category']])
        ->assertRedirect(route('import.index'));
    $c = \App\Models\Client::query()->withoutGlobalScopes()->where('company_id', $user->company_id)->first();
    expect($c->name)->toBe('Acme')->and($c->email)->toBe('acme@example.test')->and($c->category)->toBe('VIP');
});
