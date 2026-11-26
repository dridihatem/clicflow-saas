<?php

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\User;
use App\Services\NovaVoiceEntityActionService;
use App\Services\NovaVoiceIntentService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('voice entity parse understands french delete client with name or phone', function () {
    $service = app(NovaVoiceEntityActionService::class);

    expect($service->parse('supprimer client marie dupont'))->toMatchArray([
        'verb' => 'delete',
        'entity' => 'client',
        'target' => 'marie dupont',
    ])->and($service->parse('modifier client 0612345678'))->toMatchArray([
        'verb' => 'edit',
        'entity' => 'client',
        'target' => '0612345678',
    ])->and($service->parse('supprimer devis DEV-2024-01'))->toMatchArray([
        'verb' => 'delete',
        'entity' => 'proposal',
        'target' => 'dev-2024-01',
    ])->and($service->parse('supprimer client'))->toBeNull();
});

test('voice entity action opens client edit and delete record urls', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Marie Dupont',
        'phone' => '06 12 34 56 78',
    ]);

    $byName = app(NovaVoiceEntityActionService::class)->resolve($user, 'supprimer client marie dupont');
    expect($byName)->not->toBeNull()
        ->and($byName['id'])->toBe('clients.delete')
        ->and($byName['url'])->toContain('/clients/'.$client->id)
        ->and($byName['url'])->toContain('nova_action=delete')
        ->and($byName['label'])->toContain('Marie Dupont');

    $byPhone = app(NovaVoiceEntityActionService::class)->resolve($user, 'modifier client 0612345678');
    expect($byPhone)->not->toBeNull()
        ->and($byPhone['id'])->toBe('clients.edit')
        ->and($byPhone['url'])->toContain('/clients/'.$client->id.'/edit');
});

test('voice entity action opens quote by reference', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $proposal = Proposal::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'number' => 'DEV-2024-77',
        'name' => 'Website redesign',
    ]);

    $result = app(NovaVoiceEntityActionService::class)->resolve($user, 'supprimer devis DEV-2024-77');
    expect($result)->not->toBeNull()
        ->and($result['id'])->toBe('proposals.delete')
        ->and($result['url'])->toContain('/proposals/'.$proposal->id)
        ->and($result['label'])->toContain('DEV-2024-77');
});

test('voice intent resolve prefers entity action over llm for french delete client', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    Client::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Jean Martin',
        'phone' => '0700000000',
    ]);

    $result = app(NovaVoiceIntentService::class)->resolve($user, 'supprimer client Jean Martin');

    expect($result['type'])->toBe('command')
        ->and($result['id'])->toBe('clients.delete')
        ->and($result['url'])->toContain('/clients/')
        ->and($result['cached'])->toBeFalse()
        ->and(mb_strtolower($result['label']))->not->toContain('delete invoice')
        ->and(mb_strtolower($result['label']))->not->toContain('supprimer la facture');
});

test('french delete invoice label is translated for voice chrome', function () {
    app()->setLocale('fr');

    expect(__('Delete invoice'))->toBe('Supprimer la facture')
        ->and(__('Delete client'))->toBe('Supprimer le client')
        ->and(__('Delete quote'))->toBe('Supprimer le devis');
});
