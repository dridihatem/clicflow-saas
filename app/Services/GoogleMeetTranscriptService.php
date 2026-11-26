<?php

namespace App\Services;

use App\Exceptions\GoogleMeetTranscriptException;
use App\Models\Company;
use App\Models\WorkspaceCalendarEvent;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class GoogleMeetTranscriptService
{
    private const API_BASE = 'https://meet.googleapis.com/v2';

    private const MAX_TRANSCRIPT_CHARACTERS = 80000;

    public function __construct(
        private GoogleCalendarSyncService $calendar,
    ) {}

    /**
     * @return array{text: string, conference_name: string, transcript_name: string, entry_count: int}
     */
    public function transcriptFor(Company $company, WorkspaceCalendarEvent $event): array
    {
        $meetingCode = $this->meetingCode((string) ($event->google_meet_url ?: $event->meeting_url));
        if ($meetingCode === null) {
            throw $this->failure('invalid_url', 'client_meeting_transcript_invalid_url');
        }

        $accessToken = $this->calendar->accessTokenFor($company);
        if ($accessToken === null) {
            throw $this->failure('disconnected', 'client_meeting_transcript_disconnected');
        }

        $conferenceResponse = $this->get($accessToken, '/conferenceRecords', [
            'filter' => 'space.meeting_code = "'.$meetingCode.'"',
            'pageSize' => 100,
        ]);
        $this->ensureSuccessful($conferenceResponse);

        $records = collect($conferenceResponse->json('conferenceRecords', []))
            ->filter(fn (mixed $record): bool => is_array($record));
        if ($records->isEmpty()) {
            throw $this->failure('not_found', 'client_meeting_transcript_conference_not_found');
        }

        $ended = $records->filter(fn (array $record): bool => filled($record['endTime'] ?? null));
        if ($ended->isEmpty()) {
            throw $this->failure('ongoing', 'client_meeting_transcript_ongoing');
        }

        $scheduledAt = $this->scheduledAt($event);
        $conference = $ended
            ->sortBy(function (array $record) use ($scheduledAt): int {
                try {
                    return (int) abs(Carbon::parse((string) ($record['startTime'] ?? ''))->diffInSeconds($scheduledAt, false));
                } catch (\Throwable) {
                    return PHP_INT_MAX;
                }
            })
            ->first();
        $conferenceName = (string) ($conference['name'] ?? '');
        if ($conferenceName === '') {
            throw $this->failure('not_found', 'client_meeting_transcript_conference_not_found');
        }

        $transcriptResponse = $this->get($accessToken, '/'.$conferenceName.'/transcripts', [
            'pageSize' => 100,
        ]);
        $this->ensureSuccessful($transcriptResponse);

        $transcripts = collect($transcriptResponse->json('transcripts', []))
            ->filter(fn (mixed $transcript): bool => is_array($transcript));
        if ($transcripts->isEmpty()) {
            throw $this->failure('missing', 'client_meeting_transcript_missing');
        }

        $transcript = $transcripts
            ->first(fn (array $item): bool => ($item['state'] ?? null) === 'FILE_GENERATED');
        if (! is_array($transcript)) {
            throw $this->failure('processing', 'client_meeting_transcript_processing');
        }

        $transcriptName = (string) ($transcript['name'] ?? '');
        if ($transcriptName === '') {
            throw $this->failure('processing', 'client_meeting_transcript_processing');
        }

        [$text, $entryCount] = $this->entries($accessToken, $transcriptName);
        if ($text === '') {
            throw $this->failure('empty', 'client_meeting_transcript_empty');
        }

        return [
            'text' => $text,
            'conference_name' => $conferenceName,
            'transcript_name' => $transcriptName,
            'entry_count' => $entryCount,
        ];
    }

    private function meetingCode(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($host !== 'meet.google.com' || preg_match('/^[a-z]{3}-[a-z]{4}-[a-z]{3}$/', $path) !== 1) {
            return null;
        }

        return $path;
    }

    private function scheduledAt(WorkspaceCalendarEvent $event): Carbon
    {
        $date = $event->starts_on?->toDateString() ?? now()->toDateString();
        $time = filled($event->start_time) ? (string) $event->start_time : '00:00:00';

        return Carbon::parse($date.' '.$time, (string) config('app.timezone', 'UTC'))->utc();
    }

    /**
     * @return array{string, int}
     */
    private function entries(string $accessToken, string $transcriptName): array
    {
        $parts = [];
        $entryCount = 0;
        $characterCount = 0;
        $pageToken = null;

        for ($page = 0; $page < 50; $page++) {
            $query = ['pageSize' => 100];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }

            $response = $this->get($accessToken, '/'.$transcriptName.'/entries', $query);
            $this->ensureSuccessful($response);

            foreach ($response->json('transcriptEntries', []) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $text = trim((string) ($entry['text'] ?? ''));
                if ($text === '') {
                    continue;
                }

                $parts[] = $text;
                $entryCount++;
                $characterCount += mb_strlen($text) + 1;

                if ($characterCount >= self::MAX_TRANSCRIPT_CHARACTERS) {
                    return [mb_substr(implode("\n", $parts), 0, self::MAX_TRANSCRIPT_CHARACTERS), $entryCount];
                }
            }

            $pageToken = filled($response->json('nextPageToken'))
                ? (string) $response->json('nextPageToken')
                : null;
            if ($pageToken === null) {
                break;
            }
        }

        return [implode("\n", $parts), $entryCount];
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function get(string $accessToken, string $path, array $query = []): Response
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(20)
            ->get(self::API_BASE.$path, $query);
    }

    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw $this->failure('scope', 'client_meeting_transcript_reconnect');
        }

        throw $this->failure('api_error', 'client_meeting_transcript_api_error');
    }

    private function failure(string $reason, string $translationKey): GoogleMeetTranscriptException
    {
        return new GoogleMeetTranscriptException($reason, __($translationKey));
    }
}
