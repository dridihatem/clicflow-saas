<?php

use App\Exceptions\GoogleMeetTranscriptException;
use App\Mail\ClientMeetingInviteMail;
use App\Models\Client;
use App\Models\ClientFeedback;
use App\Models\ClientNote;
use App\Models\Project;
use App\Models\Provider;
use App\Models\User;
use App\Models\WorkspaceCalendarEvent;
use App\Services\GoogleMeetTranscriptService;
use App\Services\PlatformLlmRouter;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('client show page displays follow-up hub', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Acme Corp',
        'source' => 'referral',
        'status' => 'potential',
    ]);

    $this->actingAs($user)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Acme Corp')
        ->assertSee(__('Client follow-up'))
        ->assertSee(__('client_status_potential'))
        ->assertSee(__('Timeline'));
});

test('google meet opens in a new tab instead of an iframe', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $meetingUrl = 'https://meet.google.com/abc-defg-hij';

    WorkspaceCalendarEvent::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'created_by' => $user->id,
        'title' => 'Review meeting',
        'starts_on' => now()->subDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'google_meet',
        'meeting_url' => $meetingUrl,
    ]);

    $this->actingAs($user)
        ->get(route('clients.show', [$client, 'tab' => 'meetings']))
        ->assertOk()
        ->assertSee('href="'.$meetingUrl.'"', false)
        ->assertSee('target="_blank"', false)
        ->assertSee(__('client_meeting_import_transcript'))
        ->assertDontSee('src="'.$meetingUrl.'"', false);
});

test('staff can add client note with portal visibility', function () {
    $user = User::factory()->create();
    $portalUser = User::factory()->create(['company_id' => $user->company_id]);
    $portalUser->assignRole('client');
    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'user_id' => $portalUser->id,
    ]);

    $this->actingAs($user)
        ->post(route('clients.notes.store', $client), [
            'author_kind' => 'team',
            'note_type' => 'meeting',
            'noted_on' => now()->toDateString(),
            'start_time' => '10:00',
            'title' => 'Kickoff call',
            'body' => 'Discussed scope and timeline.',
            'visible_to_client' => '1',
        ])
        ->assertRedirect(route('clients.show', [$client, 'tab' => 'notes']));

    $note = ClientNote::query()->where('client_id', $client->id)->first();
    expect($note)->not->toBeNull();
    expect($note->visible_to_client)->toBeTrue();
    expect($note->note_type?->value)->toBe('meeting');
});

test('portal dashboard shows shared notes', function () {
    $user = User::factory()->create();
    $user->assignRole('client');
    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
    ]);

    ClientNote::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'user_id' => $user->id,
        'author_kind' => 'company',
        'note_type' => 'reminder',
        'body' => 'Please send signed contract.',
        'noted_on' => now()->toDateString(),
        'visible_to_client' => true,
    ]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee(__('portal_shared_notes_heading'))
        ->assertSee('Please send signed contract.');
});

test('staff can add provider feedback', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $provider = Provider::factory()->create(['company_id' => $user->company_id]);

    $this->actingAs($user)
        ->post(route('clients.feedbacks.store', $client), [
            'kind' => 'provider',
            'provider_id' => $provider->id,
            'body' => 'Strong lead from partner.',
        ])
        ->assertRedirect(route('clients.show', [$client, 'tab' => 'feedback']));

    $feedback = ClientFeedback::query()->where('client_id', $client->id)->first();
    expect($feedback)->not->toBeNull();
    expect($feedback->kind?->value)->toBe('provider');
    expect((string) $feedback->provider_id)->toBe((string) $provider->id);
});

test('staff can add client feedback', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);

    $this->actingAs($user)
        ->post(route('clients.feedbacks.store', $client), [
            'rating' => 5,
            'body' => 'Excellent collaboration.',
        ])
        ->assertRedirect(route('clients.show', [$client, 'tab' => 'feedback']));

    expect(ClientFeedback::query()->where('client_id', $client->id)->count())->toBe(1);
});

test('staff can schedule client reminder', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);

    $this->actingAs($user)
        ->post(route('clients.reminders.store', $client), [
            'title' => 'Relance devis',
            'date' => now()->addDays(3)->toDateString(),
            'description' => 'Rappeler le client',
        ])
        ->assertRedirect(route('clients.show', [$client, 'tab' => 'reminders']));

    expect(WorkspaceCalendarEvent::query()
        ->where('client_id', $client->id)
        ->where('kind', 'reminder')
        ->exists())->toBeTrue();
});

test('staff can save meeting summary', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $event = WorkspaceCalendarEvent::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'created_by' => $user->id,
        'title' => 'Kickoff',
        'starts_on' => now()->addDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'none',
    ]);

    $this->actingAs($user)
        ->patch(route('clients.meetings.summary', [$client, $event]), [
            'meeting_summary' => 'Client approved the scope.',
        ])
        ->assertRedirect();

    expect($event->fresh()->meeting_summary)->toBe('Client approved the scope.');
});

test('meeting invite requires client email', function () {
    Mail::fake();

    $user = User::factory()->create();
    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'email' => 'client@example.com',
    ]);
    $event = WorkspaceCalendarEvent::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'created_by' => $user->id,
        'title' => 'Review',
        'starts_on' => now()->addDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'none',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ]);

    $this->actingAs($user)
        ->post(route('clients.meetings.invite', [$client, $event]))
        ->assertRedirect();

    Mail::assertSent(ClientMeetingInviteMail::class);
});

test('staff can generate ai meeting summary', function () {
    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);
    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Hatem Client',
    ]);
    $event = WorkspaceCalendarEvent::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'created_by' => $user->id,
        'title' => 'Google Meet suivi',
        'description' => 'Discussion sur les factures et le prochain rendez-vous.',
        'starts_on' => now()->addDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'none',
    ]);

    $mock = Mockery::mock(PlatformLlmRouter::class);
    $mock->shouldReceive('isAvailable')->once()->andReturnTrue();
    $mock->shouldReceive('complete')->once()->andReturn([
        'suggestion' => "## Résumé\n- Client intéressé\n\n## Prochaines étapes\n- Envoyer la facture",
        'model' => 'gemini-test',
        'input_tokens' => 10,
        'output_tokens' => 20,
        'total_tokens' => 30,
    ]);
    app()->instance(PlatformLlmRouter::class, $mock);

    $this->actingAs($user)
        ->postJson(route('clients.meetings.summary.generate', [$client, $event]), [
            'notes' => 'Le client veut recevoir la facture demain.',
        ])
        ->assertOk()
        ->assertJsonPath('model', 'gemini-test')
        ->assertJsonPath('suggestion', "## Résumé\n- Client intéressé\n\n## Prochaines étapes\n- Envoyer la facture");
});

test('staff can import a Google Meet transcript and save its AI summary', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);
    seedPaidPremiumTtsForCompany($user->company);
    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Acme Client',
    ]);
    $event = WorkspaceCalendarEvent::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'created_by' => $user->id,
        'title' => 'Meet review',
        'starts_on' => now()->subDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'google_meet',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ]);

    $transcripts = Mockery::mock(GoogleMeetTranscriptService::class);
    $transcripts->shouldReceive('transcriptFor')
        ->once()
        ->withArgs(fn ($company, $meeting): bool => $company->is($user->company) && $meeting->is($event))
        ->andReturn([
            'text' => 'Client approved the proposal. Send the contract on Friday.',
            'conference_name' => 'conferenceRecords/conference-1',
            'transcript_name' => 'conferenceRecords/conference-1/transcripts/transcript-1',
            'entry_count' => 2,
        ]);
    app()->instance(GoogleMeetTranscriptService::class, $transcripts);

    $llm = Mockery::mock(PlatformLlmRouter::class);
    $llm->shouldReceive('isAvailable')->once()->andReturnTrue();
    $llm->shouldReceive('complete')
        ->once()
        ->withArgs(fn ($system, $prompt): bool => str_contains($prompt, 'Google Meet transcript')
            && str_contains($prompt, 'Client approved the proposal.'))
        ->andReturn([
            'suggestion' => "Summary\n- Proposal approved\n\nNext steps\n- Send contract Friday",
            'model' => 'gemini-test',
        ]);
    app()->instance(PlatformLlmRouter::class, $llm);

    $this->actingAs($user)
        ->postJson(route('clients.meetings.summary.import-meet', [$client, $event]))
        ->assertOk()
        ->assertJsonPath('summary', "Summary\n- Proposal approved\n\nNext steps\n- Send contract Friday")
        ->assertJsonPath('transcript_entries', 2);

    expect($event->fresh()->meeting_summary)
        ->toBe("Summary\n- Proposal approved\n\nNext steps\n- Send contract Friday");
});

test('Meet transcript import returns actionable readiness errors', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $event = WorkspaceCalendarEvent::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'created_by' => $user->id,
        'title' => 'Meet review',
        'starts_on' => now()->subDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'google_meet',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ]);

    $transcripts = Mockery::mock(GoogleMeetTranscriptService::class);
    $transcripts->shouldReceive('transcriptFor')
        ->once()
        ->andThrow(new GoogleMeetTranscriptException('scope', __('client_meeting_transcript_reconnect')));
    app()->instance(GoogleMeetTranscriptService::class, $transcripts);

    $this->actingAs($user)
        ->postJson(route('clients.meetings.summary.import-meet', [$client, $event]))
        ->assertUnprocessable()
        ->assertJsonPath('reason', 'scope')
        ->assertJsonPath('message', __('client_meeting_transcript_reconnect'));
});

test('Meet transcript import rejects an event from another client', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $otherClient = Client::factory()->create(['company_id' => $user->company_id]);
    $event = WorkspaceCalendarEvent::query()->create([
        'company_id' => $user->company_id,
        'client_id' => $otherClient->id,
        'created_by' => $user->id,
        'title' => 'Other client meeting',
        'starts_on' => now()->subDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'google_meet',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ]);

    $this->actingAs($user)
        ->postJson(route('clients.meetings.summary.import-meet', [$client, $event]))
        ->assertNotFound();
});

test('Meet transcript import is unavailable to portal clients', function () {
    $staff = User::factory()->create();
    $portalUser = User::factory()->create(['company_id' => $staff->company_id]);
    $portalUser->syncRoles(['client']);
    $client = Client::factory()->create([
        'company_id' => $staff->company_id,
        'user_id' => $portalUser->id,
    ]);
    $event = WorkspaceCalendarEvent::query()->create([
        'company_id' => $staff->company_id,
        'client_id' => $client->id,
        'created_by' => $staff->id,
        'title' => 'Private staff meeting',
        'starts_on' => now()->subDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'google_meet',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ]);

    $this->actingAs($portalUser)
        ->postJson(route('clients.meetings.summary.import-meet', [$client, $event]))
        ->assertRedirect(route('portal.dashboard'));
});

test('Meet transcript import cannot access another workspace', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);
    $otherUser = User::factory()->create();
    $otherClient = Client::factory()->create(['company_id' => $otherUser->company_id]);
    $event = WorkspaceCalendarEvent::query()->withoutGlobalScopes()->create([
        'company_id' => $otherUser->company_id,
        'client_id' => $otherClient->id,
        'created_by' => $otherUser->id,
        'title' => 'Other workspace meeting',
        'starts_on' => now()->subDay()->toDateString(),
        'kind' => 'meeting',
        'meeting_link_type' => 'google_meet',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ]);

    $this->actingAs($user)
        ->postJson(route('clients.meetings.summary.import-meet', [$otherClient, $event]))
        ->assertNotFound();
});

test('staff can add task from client follow-up page', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['company_id' => $user->company_id]);
    $project = Project::factory()->create([
        'company_id' => $user->company_id,
        'client_id' => $client->id,
        'title' => 'Website redesign',
    ]);

    $this->actingAs($user)
        ->post(route('clients.tasks.store', $client), [
            'project_id' => $project->id,
            'title' => 'Send contract',
            'status' => 'todo',
            'ends_on' => now()->addWeek()->toDateString(),
        ])
        ->assertRedirect(route('clients.show', [$client, 'tab' => 'tasks']));

    expect($project->tasks()->count())->toBe(1)
        ->and($project->tasks()->first()->title)->toBe('Send contract');
});
