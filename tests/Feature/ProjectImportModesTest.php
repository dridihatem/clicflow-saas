<?php

use App\Models\Company;
use App\Models\User;
use App\Services\PlatformLlmRouter;
use App\Services\ProjectPdfImportService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('projects import page offers excel and pdf modes', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('projects.import', ['type' => 'pdf']))
        ->assertOk()
        ->assertSee(__('Import PDF'), false)
        ->assertSee(__('project_import_pdf_card'), false);

    $this->actingAs($user)
        ->get(route('projects.import', ['type' => 'excel']))
        ->assertOk()
        ->assertSee(__('project_import_excel_card'), false);
});

test('projects index shows excel and pdf import menu links', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee(route('projects.import', ['type' => 'excel']), false)
        ->assertSee(route('projects.import', ['type' => 'pdf']), false);
});

test('project spreadsheet import reads project details per multi-tab section', function () {
    $company = Company::factory()->create(['default_currency' => 'USD']);
    $client = \App\Models\Client::factory()->create([
        'company_id' => $company->id,
        'email' => 'billing@acme.example',
        'name' => 'Acme Corp',
    ]);

    $csv = <<<'CSV'
# Website rebuild
title,Website rebuild
description,Full redesign
client_email,billing@acme.example
status,draft
budget,25000
currency,USD
deadline,2026-12-31

title,description,status,priority,starts_on,ends_on
Discovery,Workshops,todo,high,2026-10-01,2026-10-15
Build,Front-end,todo,medium,,

# Mobile app
title,Mobile app MVP
description,iOS MVP
client_name,Acme Corp
status,pending
budget,10000

title,description,status,priority
Brief,Scope,todo,urgent
CSV;

    $file = UploadedFile::fake()->createWithContent('projects.csv', $csv);
    $result = app(\App\Services\ProjectSpreadsheetImportService::class)->importFile($company, $file);

    expect($result['projects'])->toBe(2)
        ->and($result['tasks'])->toBe(3);

    $this->assertDatabaseHas('projects', [
        'company_id' => $company->id,
        'title' => 'Website rebuild',
        'client_id' => $client->id,
        'description' => 'Full redesign',
        'final_price' => 2500000,
    ]);

    $this->assertDatabaseHas('projects', [
        'company_id' => $company->id,
        'title' => 'Mobile app MVP',
        'client_id' => $client->id,
    ]);

    $website = \App\Models\Project::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('title', 'Website rebuild')
        ->first();
    expect($website->tasks()->count())->toBe(2)
        ->and($website->final_deadline?->format('Y-m-d'))->toBe('2026-12-31');
});

test('project spreadsheet example csv downloads', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('projects.import.example'))
        ->assertOk();
});

test('project pdf import service creates project and tasks from ai json', function () {
    $company = Company::factory()->create();

    $llm = Mockery::mock(PlatformLlmRouter::class);
    $llm->shouldReceive('isAvailable')->andReturn(true);
    $llm->shouldReceive('completeWithDocument')->once()->andReturn([
        'suggestion' => json_encode([
            'projects' => [[
                'title' => 'Website rebuild',
                'description' => 'From SOW PDF',
                'tasks' => [
                    ['title' => 'Discovery', 'description' => 'Workshops'],
                    ['title' => 'Build', 'description' => null],
                ],
            ]],
        ], JSON_THROW_ON_ERROR),
        'model' => 'test',
    ]);
    app()->instance(PlatformLlmRouter::class, $llm);

    $file = UploadedFile::fake()->createWithContent('scope.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    $result = app(ProjectPdfImportService::class)->importFile($company, $file);

    expect($result['projects'])->toBe(1)
        ->and($result['tasks'])->toBe(2);

    $this->assertDatabaseHas('projects', [
        'company_id' => $company->id,
        'title' => 'Website rebuild',
    ]);
});
