<?php

use App\Enums\ClientStatus;
use App\Enums\ProspectActivityType;
use App\Enums\ProspectStatus;
use App\Mail\ProspectMessageMail;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\User;
use App\Models\WorkspaceCalendarEvent;
use App\Services\PlatformLlmRouter;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('prospect show displays lifecycle pipeline', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Leila Ben',
        'source' => 'referral',
    ]);

    $this->actingAs($user)
        ->get(route('prospects.show', $prospect))
        ->assertOk()
        ->assertSee('Leila Ben')
        ->assertSee(__('prospect_lifecycle_title'))
        ->assertSee(__('prospect_next_step'))
        ->assertSee(__('prospect_activity_call'))
        ->assertSee(__('prospect_timeline_heading'));
});

test('logging a call redirects to the next lifecycle panel', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'company_id' => $user->company_id,
        'email' => 'next@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('prospects.calls.store', $prospect), [
            'summary' => 'First contact',
        ])
        ->assertRedirect(route('prospects.show', ['prospect' => $prospect, 'panel' => 'feedback']));
});

test('staff can log call feedback meeting reminder and mail on prospect', function () {
    Mail::fake();

    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'company_id' => $user->company_id,
        'email' => 'lead@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('prospects.calls.store', $prospect), [
            'summary' => 'Discussed needs',
            'outcome' => 'Interested',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('prospects.feedbacks.store', $prospect), [
            'feedback' => 'Liked the pricing',
            'rating' => 4,
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('prospects.meetings.store', $prospect), [
            'title' => 'Discovery call',
            'date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:30',
            'notes' => 'Bring deck',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('prospects.reminders.store', $prospect), [
            'title' => 'Follow up',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00',
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('prospects.mail.store', $prospect), [
            'subject' => 'Nice to meet you',
            'body' => 'Thanks for your time.',
        ])
        ->assertRedirect();

    Mail::assertSent(ProspectMessageMail::class);

    $prospect->refresh();
    expect($prospect->status)->toBe(ProspectStatus::Engaged);
    expect(ProspectActivity::query()->where('prospect_id', $prospect->id)->count())->toBeGreaterThanOrEqual(5);
    expect(WorkspaceCalendarEvent::query()->where('company_id', $user->company_id)->count())->toBe(2);
});

test('prepare quote creates potential client without marking converted', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'company_id' => $user->company_id,
        'email' => 'quote-lead@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('prospects.quote.prepare', $prospect), [
            'notes' => 'Send website redesign quote',
        ])
        ->assertRedirect();

    $prospect->refresh();
    expect($prospect->status)->not->toBe(ProspectStatus::Converted);
    expect($prospect->converted_client_id)->not->toBeNull();
    expect($prospect->convertedClient->status)->toBe(ClientStatus::Potential);
    expect($prospect->activities()->where('type', ProspectActivityType::Quote->value)->exists())->toBeTrue();
});

test('final convert marks prospect converted and client active', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'company_id' => $user->company_id,
        'email' => 'final@example.com',
    ]);

    $this->actingAs($user)
        ->post(route('prospects.convert', $prospect))
        ->assertRedirect();

    $prospect->refresh();
    expect($prospect->status)->toBe(ProspectStatus::Converted);
    expect($prospect->convertedClient->status)->toBe(ClientStatus::Active);
    expect($prospect->activities()->where('type', ProspectActivityType::Converted->value)->exists())->toBeTrue();
});

test('ai catch stores suggestion activity', function () {
    $user = User::factory()->create();
    $prospect = Prospect::factory()->create([
        'company_id' => $user->company_id,
        'name' => 'Sam Prospect',
    ]);

    $this->mock(PlatformLlmRouter::class, function ($mock) {
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('completeFast')->andReturn([
            'suggestion' => json_encode([
                'pitch' => 'Sam, we can cut your ops time in half.',
                'next_steps' => ['Send case study', 'Book demo'],
                'talking_points' => ['ROI in 30 days'],
            ]),
            'model' => 'test-model',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
        ]);
    });

    $this->actingAs($user)
        ->post(route('prospects.ai-catch', $prospect))
        ->assertRedirect()
        ->assertSessionHas('status');

    $activity = $prospect->activities()->where('type', ProspectActivityType::AiSuggestion->value)->first();
    expect($activity)->not->toBeNull();
    expect($activity->body)->toContain('Sam, we can cut your ops time in half.');
});
