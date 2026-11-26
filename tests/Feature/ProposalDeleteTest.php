<?php

use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('quotes list shows a delete button and company admin can remove a quote', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $proposal = Proposal::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Devis vitrine',
    ]);

    $this->actingAs($user)
        ->get(route('proposals.index'))
        ->assertOk()
        ->assertSee(__('Delete this quote?'), false)
        ->assertSee(route('proposals.destroy', $proposal), false);

    $this->actingAs($user)
        ->delete(route('proposals.destroy', $proposal))
        ->assertRedirect(route('proposals.index'))
        ->assertSessionHas('status', __('Quote deleted.'));

    $this->assertSoftDeleted('proposals', ['id' => $proposal->id]);
});
