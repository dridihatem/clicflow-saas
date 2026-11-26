<?php

use App\Mail\VaultShareLinkMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Models\VaultFile;
use App\Models\VaultFileShare;
use App\Services\Vault\VaultAccessService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
});

function v48User(string $role, ?string $companyId = null): User
{
    $u = User::factory()->create($companyId ? ['company_id' => $companyId] : []);
    $u->syncRoles([$role]);

    return $u;
}

function v48File(string $companyId, array $attrs = []): VaultFile
{
    Storage::disk('local')->put($path = 'vault/companies/'.$companyId.'/t/'.uniqid().'.pdf', '%PDF-1.4 test');

    return VaultFile::query()->withoutGlobalScopes()->create(array_merge([
        'company_id' => $companyId, 'original_name' => 'rapport.pdf', 'extension' => 'pdf', 'disk' => 'local',
        'path' => $path, 'mime' => 'application/pdf', 'size' => 13, 'uploader_type' => 'staff',
    ], $attrs));
}

function v48Link(VaultFile $file, array $attrs = []): VaultFileShare
{
    return VaultFileShare::query()->withoutGlobalScopes()->create(array_merge([
        'company_id' => $file->company_id, 'vault_file_id' => $file->id, 'token' => VaultFileShare::newToken(),
        'allow_preview' => true,
    ], $attrs));
}

test('client upload without project is visible to the whole team', function () {
    $admin = v48User('company_admin');
    $member = v48User('team_member', $admin->company_id);
    $cu = v48User('client', $admin->company_id);
    Client::factory()->create(['company_id' => $admin->company_id, 'user_id' => $cu->id]);

    $this->actingAs($cu)->post(route('portal.vault.store'), ['files' => [UploadedFile::fake()->create('kbis.pdf', 5, 'application/pdf')]])
        ->assertSessionHasNoErrors();
    $file = VaultFile::query()->withoutGlobalScopes()->firstOrFail();
    expect($file->share_team)->toBeTrue()
        ->and(app(VaultAccessService::class)->canView($member, $file, 'company'))->toBeTrue();
    $this->actingAs($member)->get(route('vault.index'))->assertSee('kbis.pdf');
});

test('team members can upload only to their own projects or unlinked', function () {
    $admin = v48User('company_admin');
    $member = v48User('team_member', $admin->company_id);
    $mine = Project::factory()->create(['company_id' => $admin->company_id]);
    $other = Project::factory()->create(['company_id' => $admin->company_id]);
    $mine->teamMembers()->attach($member->id);

    $this->actingAs($member)->post(route('vault.store'), ['files' => [UploadedFile::fake()->create('a.pdf', 3, 'application/pdf')], 'project_id' => $other->id])
        ->assertSessionHasErrors('project_id');
    $this->actingAs($member)->post(route('vault.store'), ['files' => [UploadedFile::fake()->create('b.pdf', 3, 'application/pdf')], 'project_id' => $mine->id])
        ->assertSessionHasNoErrors();
    $this->actingAs($member)->post(route('vault.store'), ['files' => [UploadedFile::fake()->create('c.pdf', 3, 'application/pdf')]])
        ->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('vault.store'), ['files' => [UploadedFile::fake()->create('d.pdf', 3, 'application/pdf')], 'project_id' => $other->id])
        ->assertSessionHasNoErrors();
    expect(VaultFile::query()->withoutGlobalScopes()->count())->toBe(3);
});

test('trash: uploader delete goes to trash, admin restores and force deletes, purge after 30 days', function () {
    $admin = v48User('company_admin');
    $member = v48User('team_member', $admin->company_id);
    $file = v48File($admin->company_id, ['uploaded_by' => $member->id, 'share_team' => true, 'original_name' => 'to-trash.pdf']);

    $this->actingAs($member)->delete(route('vault.destroy', $file->id))->assertRedirect();
    $trashed = VaultFile::query()->withoutGlobalScopes()->withTrashed()->find($file->id);
    expect($trashed->trashed())->toBeTrue()->and((int) $trashed->deleted_by)->toBe($member->id);

    $this->actingAs($member)->get(route('vault.trash'))->assertForbidden();
    $this->actingAs($admin)->get(route('vault.trash'))->assertOk()->assertSee('to-trash.pdf')->assertSee($member->name);

    $this->actingAs($admin)->post(route('vault.restore', $file->id))->assertRedirect(route('vault.trash'));
    expect(VaultFile::query()->withoutGlobalScopes()->find($file->id))->not->toBeNull();

    $file->delete();
    $this->actingAs($member)->delete(route('vault.force-destroy', $file->id))->assertForbidden();
    $this->actingAs($admin)->delete(route('vault.force-destroy', $file->id))->assertRedirect();
    expect(VaultFile::query()->withoutGlobalScopes()->withTrashed()->find($file->id))->toBeNull();
    Storage::disk('local')->assertMissing($file->path);

    // Purge: only trashed rows older than 30 days are removed.
    $old = v48File($admin->company_id, ['original_name' => 'old.pdf']);
    $recent = v48File($admin->company_id, ['original_name' => 'recent.pdf']);
    $alive = v48File($admin->company_id, ['original_name' => 'alive.pdf']);
    $old->delete(); $recent->delete();
    VaultFile::query()->withoutGlobalScopes()->withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(31)]);
    $this->artisan('vault:purge-trash')->assertSuccessful();
    expect(VaultFile::query()->withoutGlobalScopes()->withTrashed()->find($old->id))->toBeNull()
        ->and(VaultFile::query()->withoutGlobalScopes()->withTrashed()->find($recent->id))->not->toBeNull()
        ->and(VaultFile::query()->withoutGlobalScopes()->find($alive->id))->not->toBeNull();
    Storage::disk('local')->assertMissing($old->path);
    Storage::disk('local')->assertExists($recent->path);
});

test('admin creates a password link; password is hashed and shown once', function () {
    $admin = v48User('company_admin');
    $file = v48File($admin->company_id);

    $this->actingAs($admin)->post(route('vault.links.store', $file->id), [
        'password' => 'Secret123', 'expiry' => '7', 'max_downloads' => 2, 'allow_preview' => 1,
    ])->assertRedirect()->assertSessionHas('vault_share_created');
    $link = VaultFileShare::query()->withoutGlobalScopes()->firstOrFail();
    expect($link->password_hash)->not->toBe('Secret123')
        ->and(Hash::check('Secret123', $link->password_hash))->toBeTrue()
        ->and(strlen($link->token))->toBe(64)
        ->and($link->expires_at->isFuture())->toBeTrue();

    $this->actingAs($admin)->post(route('vault.links.store', $file->id), ['expiry' => 'never'])->assertRedirect();
    expect(VaultFileShare::query()->withoutGlobalScopes()->whereNull('expires_at')->count())->toBe(1);
});

test('public link without password downloads, respects max downloads', function () {
    $admin = v48User('company_admin');
    $file = v48File($admin->company_id, ['original_name' => 'public.pdf']);
    $link = v48Link($file, ['max_downloads' => 1]);
    auth()->logout();

    $this->get(route('vault-share.show', $link->token))->assertOk()->assertSee('public.pdf');
    $this->get(route('vault-share.download', $link->token))->assertOk();
    $this->get(route('vault-share.download', $link->token))->assertNotFound();
    $this->get(route('vault-share.show', $link->token))->assertNotFound();
    expect($link->fresh()->download_count)->toBe(1);
});

test('public password link requires the password and throttles attempts', function () {
    $admin = v48User('company_admin');
    $file = v48File($admin->company_id, ['original_name' => 'secret.pdf']);
    $link = v48Link($file, ['password_hash' => Hash::make('Sesame42')]);
    RateLimiter::clear('vault-share-pw:'.$link->id.'|127.0.0.1');

    $this->get(route('vault-share.show', $link->token))->assertOk()->assertSee('name="password"', false);
    $this->get(route('vault-share.download', $link->token))->assertRedirect(route('vault-share.show', $link->token));
    $this->post(route('vault-share.unlock', $link->token), ['password' => 'nope'])->assertSessionHasErrors('password');
    $this->post(route('vault-share.unlock', $link->token), ['password' => 'Sesame42'])->assertRedirect(route('vault-share.show', $link->token));
    $this->get(route('vault-share.download', $link->token))->assertOk();

    // Throttle: after 5 wrong attempts even the right password is refused.
    $this->flushSession();
    RateLimiter::clear('vault-share-pw:'.$link->id.'|127.0.0.1');
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('vault-share.unlock', $link->token), ['password' => 'bad'.$i]);
    }
    $this->post(route('vault-share.unlock', $link->token), ['password' => 'Sesame42'])->assertSessionHasErrors('password');
    $this->get(route('vault-share.download', $link->token))->assertRedirect();
});

test('expired, revoked, trashed and bogus tokens give 404', function () {
    $admin = v48User('company_admin');
    $file = v48File($admin->company_id);
    $expired = v48Link($file, ['expires_at' => now()->subMinute()]);
    $revoked = v48Link($file);
    $this->actingAs($admin)->delete(route('vault.links.destroy', [$file->id, $revoked->id]))->assertRedirect();
    expect($revoked->fresh()->revoked_at)->not->toBeNull();
    auth()->logout();

    $this->get(route('vault-share.show', $expired->token))->assertNotFound();
    $this->get(route('vault-share.show', $revoked->token))->assertNotFound();
    $this->get('/v/s/bogus-token')->assertNotFound();
    $this->get('/v/s/'.str_repeat('a', 64))->assertNotFound();

    $live = v48Link($file);
    $file->delete();
    $this->get(route('vault-share.show', $live->token))->assertNotFound();
});

test('cross-tenant: another company cannot manage a link', function () {
    $admin = v48User('company_admin');
    $file = v48File($admin->company_id);
    $link = v48Link($file);
    $other = v48User('company_admin', Company::factory()->create()->id);

    $this->actingAs($other)->post(route('vault.links.store', $file->id), ['expiry' => '7'])->assertNotFound();
    $this->actingAs($other)->delete(route('vault.links.destroy', [$file->id, $link->id]))->assertNotFound();
    $this->actingAs($other)->post(route('vault.links.email', [$file->id, $link->id]), ['recipients' => 'x@example.test'])->assertNotFound();
    expect($link->fresh()->revoked_at)->toBeNull();
});

test('email includes the password only when chosen and verified', function () {
    Mail::fake();
    $admin = v48User('company_admin');
    $file = v48File($admin->company_id, ['original_name' => 'devis.pdf']);
    $link = v48Link($file, ['password_hash' => Hash::make('Clef2026')]);

    $this->actingAs($admin)->post(route('vault.links.email', [$file->id, $link->id]), [
        'recipients' => 'a@example.test, b@example.test', 'include_password' => 1, 'link_password' => 'wrong',
    ])->assertSessionHasErrors('link_password');
    Mail::assertNothingSent();

    $this->actingAs($admin)->post(route('vault.links.email', [$file->id, $link->id]), [
        'recipients' => 'a@example.test, b@example.test', 'include_password' => 1, 'link_password' => 'Clef2026', 'message' => 'Voici le devis',
    ])->assertSessionHasNoErrors();
    Mail::assertSent(VaultShareLinkMail::class, 2);
    Mail::assertSent(VaultShareLinkMail::class, fn ($m) => $m->linkPassword === 'Clef2026' && str_contains($m->render(), 'Clef2026') && str_contains($m->render(), $link->token));

    Mail::fake();
    $this->actingAs($admin)->post(route('vault.links.email', [$file->id, $link->id]), [
        'recipients' => 'c@example.test', 'include_password' => 0,
    ])->assertSessionHasNoErrors();
    Mail::assertSent(VaultShareLinkMail::class, fn ($m) => $m->linkPassword === null && ! str_contains($m->render(), 'Clef2026'));

    $this->actingAs($admin)->post(route('vault.links.email', [$file->id, $link->id]), ['recipients' => 'not-an-email'])
        ->assertSessionHasErrors('recipients');

    // The password never lands in the activity log.
    expect(\App\Models\AuditLog::query()->withoutGlobalScopes()->get()->pluck('properties')->toJson())->not->toContain('Clef2026');
});
