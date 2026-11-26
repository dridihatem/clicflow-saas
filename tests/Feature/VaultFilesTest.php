<?php

use App\Mail\VaultFileSharedMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use App\Models\VaultFile;
use App\Services\Vault\VaultAccessService;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
});

function vaultUser(string $role, ?string $companyId = null): User
{
    $u = User::factory()->create($companyId ? ['company_id' => $companyId] : []);
    $u->syncRoles([$role]);

    return $u;
}

function vaultSetup(): array
{
    $admin = vaultUser('company_admin');
    $cid = $admin->company_id;
    $member = vaultUser('team_member', $cid);
    $outsider = vaultUser('team_member', $cid);
    $clientUser = vaultUser('client', $cid);
    $client = Client::factory()->create(['company_id' => $cid, 'user_id' => $clientUser->id, 'email' => 'vault-client@example.test']);
    $otherClientUser = vaultUser('client', $cid);
    $otherClient = Client::factory()->create(['company_id' => $cid, 'user_id' => $otherClientUser->id]);
    $providerUser = vaultUser('business_provider', $cid);
    $provider = Provider::factory()->create(['company_id' => $cid, 'user_id' => $providerUser->id]);
    $otherProviderUser = vaultUser('business_provider', $cid);
    Provider::factory()->create(['company_id' => $cid, 'user_id' => $otherProviderUser->id]);
    $project = Project::factory()->create(['company_id' => $cid, 'client_id' => $client->id, 'provider_id' => $provider->id]);
    $project->teamMembers()->attach($member->id);

    return compact('admin', 'member', 'outsider', 'clientUser', 'client', 'otherClientUser', 'otherClient', 'providerUser', 'provider', 'otherProviderUser', 'project');
}

function vaultFile(array $attrs): VaultFile
{
    Storage::disk('local')->put($path = 'vault/companies/'.$attrs['company_id'].'/t/'.uniqid().'.pdf', '%PDF-1.4 test');

    return VaultFile::query()->withoutGlobalScopes()->create(array_merge([
        'original_name' => 'doc.pdf', 'extension' => 'pdf', 'disk' => 'local', 'path' => $path,
        'mime' => 'application/pdf', 'size' => 12, 'uploader_type' => 'staff',
    ], $attrs));
}

test('staff upload stores privately, shares with client and notifies via mail', function () {
    Mail::fake();
    $s = vaultSetup();

    $this->actingAs($s['admin'])->post(route('vault.store'), [
        'files' => [UploadedFile::fake()->create('contrat.pdf', 20, 'application/pdf')],
        'project_id' => $s['project']->id,
        'share_client' => 1, 'share_project' => 1, 'notify_client' => 1,
        'folder' => 'Contrats',
    ])->assertRedirect(route('vault.index'))->assertSessionHasNoErrors();

    $file = VaultFile::query()->withoutGlobalScopes()->firstOrFail();
    expect($file->disk)->toBe('local')
        ->and($file->client_id)->toBe($s['client']->id)
        ->and($file->share_client)->toBeTrue()
        ->and($file->folder)->toBe('Contrats')
        ->and(str_starts_with($file->path, 'vault/companies/'.$s['admin']->company_id.'/'))->toBeTrue();
    Storage::disk('local')->assertExists($file->path);
    Storage::disk('public')->assertMissing($file->path);
    Mail::assertSent(VaultFileSharedMail::class);
});

test('blocked and unknown extensions are refused', function () {
    $s = vaultSetup();
    foreach (['shell.php', 'run.exe', 'x.sh', 'app.js', 'page.html', 'logo.svg', 'image.php.jpg'] as $name) {
        $this->actingAs($s['admin'])->post(route('vault.store'), [
            'files' => [UploadedFile::fake()->create($name, 5)],
        ])->assertSessionHasErrors();
    }
    $this->actingAs($s['clientUser'])->post(route('portal.vault.store'), [
        'files' => [UploadedFile::fake()->create('evil.phtml', 5)],
    ])->assertSessionHasErrors();
    expect(VaultFile::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('client sees only files shared with them or uploaded by them', function () {
    $s = vaultSetup();
    $cid = $s['admin']->company_id;
    $shared = vaultFile(['company_id' => $cid, 'client_id' => $s['client']->id, 'share_client' => true, 'original_name' => 'shared-client.pdf']);
    $viaProject = vaultFile(['company_id' => $cid, 'project_id' => $s['project']->id, 'share_client' => true, 'original_name' => 'via-project.pdf']);
    $internal = vaultFile(['company_id' => $cid, 'client_id' => $s['client']->id, 'share_client' => false, 'original_name' => 'internal-only.pdf']);
    $other = vaultFile(['company_id' => $cid, 'client_id' => $s['otherClient']->id, 'share_client' => true, 'original_name' => 'other-client.pdf']);

    $this->actingAs($s['clientUser'])->get(route('portal.vault.index'))->assertOk()
        ->assertSee('shared-client.pdf')->assertSee('via-project.pdf')
        ->assertDontSee('internal-only.pdf')->assertDontSee('other-client.pdf');

    $this->actingAs($s['clientUser'])->get(route('portal.vault.download', $shared->id))->assertOk();
    $this->actingAs($s['clientUser'])->get(route('portal.vault.download', $internal->id))->assertForbidden();
    $this->actingAs($s['clientUser'])->get(route('portal.vault.download', $other->id))->assertForbidden();
    $this->actingAs($s['otherClientUser'])->get(route('portal.vault.download', $viaProject->id))->assertForbidden();
});

test('client upload is visible to company and project team but not to other clients', function () {
    $s = vaultSetup();
    $this->actingAs($s['clientUser'])->post(route('portal.vault.store'), [
        'files' => [UploadedFile::fake()->image('brief.png')],
        'project_id' => $s['project']->id,
    ])->assertRedirect(route('portal.vault.index'))->assertSessionHasNoErrors();

    $file = VaultFile::query()->withoutGlobalScopes()->firstOrFail();
    expect($file->uploader_type)->toBe('client')->and($file->share_project)->toBeTrue();
    $access = app(VaultAccessService::class);
    expect($access->canView($s['admin'], $file, 'company'))->toBeTrue()
        ->and($access->canView($s['member'], $file, 'company'))->toBeTrue()
        ->and($access->canView($s['outsider'], $file, 'company'))->toBeFalse()
        ->and($access->canView($s['otherClientUser'], $file, 'portal'))->toBeFalse()
        ->and($access->canView($s['providerUser'], $file, 'provider'))->toBeFalse();

    // A client cannot attach an upload to someone else's project.
    $foreign = Project::factory()->create(['company_id' => $s['admin']->company_id, 'client_id' => $s['otherClient']->id]);
    $this->actingAs($s['clientUser'])->post(route('portal.vault.store'), [
        'files' => [UploadedFile::fake()->create('x.pdf', 3, 'application/pdf')],
        'project_id' => $foreign->id,
    ])->assertSessionHasErrors('project_id');
});

test('project members and team sharing rules for staff', function () {
    $s = vaultSetup();
    $cid = $s['admin']->company_id;
    $projectFile = vaultFile(['company_id' => $cid, 'project_id' => $s['project']->id, 'share_project' => true, 'original_name' => 'project-members.pdf']);
    $teamFile = vaultFile(['company_id' => $cid, 'share_team' => true, 'original_name' => 'whole-team.pdf']);
    $internal = vaultFile(['company_id' => $cid, 'uploaded_by' => $s['admin']->id, 'original_name' => 'admin-internal.pdf']);
    $targeted = vaultFile(['company_id' => $cid, 'original_name' => 'targeted.pdf']);
    $targeted->sharedUsers()->attach($s['outsider']->id);

    $this->actingAs($s['member'])->get(route('vault.index'))->assertOk()
        ->assertSee('project-members.pdf')->assertSee('whole-team.pdf')
        ->assertDontSee('admin-internal.pdf')->assertDontSee('targeted.pdf');
    $this->actingAs($s['outsider'])->get(route('vault.index'))->assertOk()
        ->assertDontSee('project-members.pdf')->assertSee('whole-team.pdf')->assertSee('targeted.pdf');
    $this->actingAs($s['admin'])->get(route('vault.index'))->assertOk()
        ->assertSee('admin-internal.pdf')->assertSee('targeted.pdf');

    $this->actingAs($s['member'])->get(route('vault.download', $internal->id))->assertForbidden();
    $this->actingAs($s['member'])->delete(route('vault.destroy', $teamFile->id))->assertForbidden();
});

test('provider access is limited to assigned projects shared with providers', function () {
    $s = vaultSetup();
    $cid = $s['admin']->company_id;
    $forProviders = vaultFile(['company_id' => $cid, 'project_id' => $s['project']->id, 'share_providers' => true, 'original_name' => 'for-providers.pdf']);
    $projectOnly = vaultFile(['company_id' => $cid, 'project_id' => $s['project']->id, 'share_project' => true, 'original_name' => 'members-only.pdf']);

    $this->actingAs($s['providerUser'])->get(route('provider.vault.index'))->assertOk()
        ->assertSee('for-providers.pdf')->assertDontSee('members-only.pdf');
    $this->actingAs($s['providerUser'])->get(route('provider.vault.preview', $forProviders->id))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($s['otherProviderUser'])->get(route('provider.vault.download', $forProviders->id))->assertForbidden();
    $this->actingAs($s['providerUser'])->get(route('provider.vault.download', $projectOnly->id))->assertForbidden();

    $this->actingAs($s['providerUser'])->post(route('provider.vault.store'), [
        'files' => [UploadedFile::fake()->create('livrable.zip', 10, 'application/zip')],
        'project_id' => $s['project']->id,
    ])->assertSessionHasNoErrors();
    $upload = VaultFile::query()->withoutGlobalScopes()->where('original_name', 'livrable.zip')->firstOrFail();
    expect($upload->uploader_type)->toBe('provider')
        ->and(app(VaultAccessService::class)->canView($s['member'], $upload, 'company'))->toBeTrue()
        ->and(app(VaultAccessService::class)->canView($s['clientUser'], $upload, 'portal'))->toBeFalse();
});

test('tenant isolation: another company cannot list or download', function () {
    $s = vaultSetup();
    $file = vaultFile(['company_id' => $s['admin']->company_id, 'share_team' => true, 'share_client' => true, 'client_id' => $s['client']->id, 'original_name' => 'tenant-a-secret.pdf']);
    $otherAdmin = vaultUser('company_admin', Company::factory()->create()->id);

    $this->actingAs($otherAdmin)->get(route('vault.index'))->assertOk()->assertDontSee('tenant-a-secret.pdf');
    $this->actingAs($otherAdmin)->get(route('vault.download', $file->id))->assertNotFound();
    $this->actingAs($otherAdmin)->delete(route('vault.destroy', $file->id))->assertNotFound();
});

test('delete is soft and limited to uploader or admin; files are never public', function () {
    $s = vaultSetup();
    $file = vaultFile(['company_id' => $s['admin']->company_id, 'uploaded_by' => $s['clientUser']->id, 'uploader_type' => 'client', 'client_id' => $s['client']->id, 'share_client' => true]);

    expect(file_exists(public_path($file->path)))->toBeFalse()
        ->and(file_exists(public_path('storage/'.$file->path)))->toBeFalse();
    expect($this->get('/storage/'.$file->path)->status())->toBeIn([403, 404]);
    auth()->logout();
    $this->get(route('portal.vault.download', $file->id))->assertRedirect();

    $this->actingAs($s['clientUser'])->delete(route('portal.vault.destroy', $file->id))->assertRedirect();
    $fresh = VaultFile::query()->withoutGlobalScopes()->withTrashed()->find($file->id);
    expect($fresh->trashed())->toBeTrue();
    Storage::disk('local')->assertExists($file->path);
});

test('company admin can change sharing and portal visibility toggle hides the vault', function () {
    $s = vaultSetup();
    $file = vaultFile(['company_id' => $s['admin']->company_id, 'original_name' => 'later-shared.pdf']);
    $this->actingAs($s['admin'])->put(route('vault.update', $file->id), [
        'client_id' => $s['client']->id, 'share_client' => 1,
    ])->assertRedirect();
    expect($file->fresh()->share_client)->toBeTrue();
    $this->actingAs($s['clientUser'])->get(route('portal.vault.index'))->assertOk()->assertSee('later-shared.pdf');

    app(\App\Services\PortalVisibilityService::class)->saveCompanyHidden(Company::find($s['admin']->company_id), ['vault']);
    $this->actingAs($s['clientUser'])->get(route('portal.vault.index'))->assertStatus(302);
});
