<?php

use App\Exceptions\GoogleMeetTranscriptException;
use App\Models\Company;
use App\Models\WorkspaceCalendarEvent;
use App\Services\GoogleCalendarSyncService;
use App\Services\GoogleMeetTranscriptService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function meetTranscriptService(): GoogleMeetTranscriptService
{
    $calendar = Mockery::mock(GoogleCalendarSyncService::class);
    $calendar->shouldReceive('accessTokenFor')->andReturn('meet-access-token');

    return new GoogleMeetTranscriptService($calendar);
}

function meetTranscriptEvent(): WorkspaceCalendarEvent
{
    return new WorkspaceCalendarEvent([
        'starts_on' => '2026-07-20',
        'start_time' => '10:00:00',
        'meeting_url' => 'https://meet.google.com/abc-defg-hij',
    ]);
}

test('it retrieves and paginates a completed Google Meet transcript', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/entries')) {
            $pageToken = $request->data()['pageToken'] ?? null;

            return $pageToken === null
                ? Http::response([
                    'transcriptEntries' => [
                        ['text' => 'Client approved the proposal.'],
                    ],
                    'nextPageToken' => 'page-2',
                ])
                : Http::response([
                    'transcriptEntries' => [
                        ['text' => 'Send the contract on Friday.'],
                    ],
                ]);
        }

        if (str_contains($url, '/transcripts')) {
            return Http::response([
                'transcripts' => [[
                    'name' => 'conferenceRecords/conference-1/transcripts/transcript-1',
                    'state' => 'FILE_GENERATED',
                ]],
            ]);
        }

        return Http::response([
            'conferenceRecords' => [[
                'name' => 'conferenceRecords/conference-1',
                'startTime' => '2026-07-20T09:00:00Z',
                'endTime' => '2026-07-20T10:00:00Z',
            ]],
        ]);
    });

    $result = meetTranscriptService()->transcriptFor(new Company, meetTranscriptEvent());

    expect($result['text'])->toBe("Client approved the proposal.\nSend the contract on Friday.")
        ->and($result['entry_count'])->toBe(2)
        ->and($result['conference_name'])->toBe('conferenceRecords/conference-1');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/conferenceRecords')
        && str_contains((string) ($request->data()['filter'] ?? ''), 'abc-defg-hij'));
});

test('it reports when a completed call has no transcript', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/transcripts')) {
            return Http::response(['transcripts' => []]);
        }

        return Http::response([
            'conferenceRecords' => [[
                'name' => 'conferenceRecords/conference-1',
                'startTime' => '2026-07-20T09:00:00Z',
                'endTime' => '2026-07-20T10:00:00Z',
            ]],
        ]);
    });

    try {
        meetTranscriptService()->transcriptFor(new Company, meetTranscriptEvent());
        $this->fail('Expected a missing transcript exception.');
    } catch (GoogleMeetTranscriptException $e) {
        expect($e->reason)->toBe('missing')
            ->and($e->getMessage())->toBe(__('client_meeting_transcript_missing'));
    }
});

test('it asks users to reconnect when Google rejects the Meet scope', function () {
    Http::fake(fn () => Http::response(['error' => ['message' => 'insufficient_scope']], 403));

    try {
        meetTranscriptService()->transcriptFor(new Company, meetTranscriptEvent());
        $this->fail('Expected an OAuth scope exception.');
    } catch (GoogleMeetTranscriptException $e) {
        expect($e->reason)->toBe('scope')
            ->and($e->getMessage())->toBe(__('client_meeting_transcript_reconnect'));
    }
});
