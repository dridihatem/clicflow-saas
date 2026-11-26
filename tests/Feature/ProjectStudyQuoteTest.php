<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectStudy;
use App\Models\User;
use App\Services\ProjectStudyQuoteService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('project study quote uses project name understanding and financial prices', function () {
    Http::fake();

    $company = Company::factory()->create(['default_currency' => 'EUR']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    $client = Client::factory()->create(['company_id' => $company->id]);
    $project = Project::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'title' => 'Boutique e-commerce relaunch',
    ]);

    Product::query()->create([
        'company_id' => $company->id,
        'name' => 'UX audit',
        'kind' => 'service',
        'base_price_minor' => 150000,
        'is_active' => true,
        'unit' => 'forfait',
    ]);

    $study = ProjectStudy::query()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'title' => 'Study title ignored when project linked',
        'status' => 'generated',
        'currency' => 'EUR',
        'budget_minor' => 500000,
        'executive_summary' => "Understanding: redesign checkout and catalog.",
        'financial_study' => "- UX audit: 1500\n- Theme build: 3500",
    ]);

    $this->actingAs($user)
        ->get(route('project-studies.show', $study))
        ->assertOk()
        ->assertSee(__('project_study_create_quote_manual'), false)
        ->assertDontSee(__('project_study_create_quote_heading'), false);

    $this->actingAs($user)
        ->post(route('project-studies.proposal', $study), ['mode' => 'manual'])
        ->assertRedirect();

    $proposal = $company->proposals()->latest('id')->first();
    expect($proposal)->not->toBeNull();
    expect($proposal->name)->toBe('Boutique e-commerce relaunch');
    expect($proposal->items)->not->toBeEmpty();
    expect($proposal->items->pluck('description')->implode(' '))->toContain('UX audit');
});

test('project study quote accepts a long financial paragraph', function () {
    Http::fake();

    $company = Company::factory()->create(['default_currency' => 'TND']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');
    $client = Client::factory()->create(['company_id' => $company->id]);

    $paragraph = "L'évaluation financière du projet se scinde en deux volets distincts : le coût de la présente étude de cadrage, fixé à un montant forfaitaire de 35 000,000 TND, et les projections budgétaires liées à la mise en œuvre ultérieure de la plateforme (CAPEX et OPEX).";

    $study = ProjectStudy::query()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'title' => 'Étude de cadrage',
        'status' => 'generated',
        'currency' => 'TND',
        'financial_study' => $paragraph,
    ]);

    $this->actingAs($user)
        ->post(route('project-studies.proposal', $study), ['mode' => 'manual'])
        ->assertRedirect();

    $proposal = $company->proposals()->latest('id')->first();
    expect($proposal)->not->toBeNull();
    expect($proposal->items)->not->toBeEmpty();
    expect($proposal->items->first()->description)->toBe('Étude de cadrage');
    expect((int) $proposal->items->first()->unit_amount)->toBe(35_000_000);
});

test('project study quote uses project description elements and study budget', function () {
    Http::fake();

    $company = Company::factory()->create(['default_currency' => 'TND']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');
    $client = Client::factory()->create(['company_id' => $company->id]);
    $project = Project::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'title' => 'Plateforme CRM',
        'description' => "- Cadrage et architecture\n- Développement des modules clients\n- Mise en production",
        'final_price' => 12_000_000,
    ]);

    $study = ProjectStudy::query()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'title' => 'Étude CRM',
        'status' => 'generated',
        'currency' => 'TND',
        'budget_minor' => 20_000_000,
        'executive_summary' => 'Le projet vise à unifier le suivi commercial.',
        'financial_study' => "L'évaluation financière du projet se scinde en deux volets distincts : le coût de la présente étude de cadrage, fixé à un montant forfaitaire de 35 000,000 TND, et les projections budgétaires liées à la mise en œuvre ultérieure de la plateforme (CAPEX et OPEX).",
    ]);

    $this->actingAs($user)
        ->post(route('project-studies.proposal', $study), ['mode' => 'manual'])
        ->assertRedirect();

    $proposal = $company->proposals()->latest('id')->first();
    expect($proposal)->not->toBeNull();
    $descriptions = $proposal->items->pluck('description')->all();
    expect($descriptions)->toContain('Cadrage et architecture');
    expect($descriptions)->toContain('Développement des modules clients');
    expect($descriptions)->toContain('Mise en production');
    expect((int) $proposal->items->sum(fn ($item) => $item->quantity * $item->unit_amount))->toBe(20_000_000);
});

test('project study quote service builds labeled brief', function () {
    $company = Company::factory()->create();
    $study = ProjectStudy::query()->create([
        'company_id' => $company->id,
        'title' => 'Alpha study',
        'status' => 'draft',
        'currency' => 'USD',
        'executive_summary' => 'Need CRM automation',
        'financial_study' => 'Setup: 900',
    ]);

    $brief = app(ProjectStudyQuoteService::class)->studyBriefForQuote($study);

    expect($brief)->toContain('Understanding / executive summary');
    expect($brief)->toContain('Need CRM automation');
    expect($brief)->toContain('Financial study');
});
