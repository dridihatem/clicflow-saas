<?php

use App\Models\Client;
use App\Models\ClientContract;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('contract edit form uses compact colored cards', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id, 'name' => 'Acme Layout']);
    $contract = ClientContract::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'title' => 'Layout agreement',
    ]);

    $this->actingAs($user)
        ->get(route('contracts.edit', $contract))
        ->assertOk()
        ->assertSee(__('contract_form_parties'), false)
        ->assertSee(__('contract_form_period'), false)
        ->assertSee('Layout agreement', false)
        ->assertSee('Acme Layout', false);
});

test('invoice create form uses the simplified shared editor', function () {
    $user = User::factory()->create();

    // t62u: one shared editor (header, lines, totals, « Options avancées » with notes + recurrence, sticky actions).
    $this->actingAs($user)
        ->get(route('invoices.create'))
        ->assertOk()
        ->assertSee('data-doc-editor-header', false)
        ->assertSee('data-doc-editor-totals', false)
        ->assertSee('data-doc-editor-actions', false)
        ->assertSee(__('doc_editor_advanced'), false)
        ->assertSee(__('Customer notes (shown on PDF)'), false)
        ->assertSee(__('invoice_recurrence.title'), false);
});
