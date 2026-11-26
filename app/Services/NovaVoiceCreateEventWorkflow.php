<?php

namespace App\Services;

use App\Enums\CalendarMeetingLinkType;
use App\Enums\ProspectVerifiedVia;
use App\Mail\ClientMeetingInviteMail;
use App\Models\Client;
use App\Models\User;
use App\Models\WorkspaceCalendarEvent;
use Illuminate\Support\Facades\Route;

/**
 * Conversational Nova flow: create calendar event
 * (subject, date, time, meeting type, Google sync, message, optional client + invite).
 */
class NovaVoiceCreateEventWorkflow
{
    public function __construct(
        private CalendarMeetingLinkService $meetings,
        private GoogleCalendarSyncService $google,
        private ProspectService $prospects,
    ) {}

    /**
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>}
     */
    public function start(User $user): array
    {
        return [
            'active' => true,
            'workflow' => 'create_event',
            'reply' => __('nova_workflow_create_event_prompt'),
            'done' => false,
            'redirect_url' => null,
            'step' => 'collect_subject',
            'data' => [],
        ];
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>}
     */
    public function advance(User $user, array $session, string $input): array
    {
        $step = (string) ($session['step'] ?? 'collect_subject');
        $data = is_array($session['data'] ?? null) ? $session['data'] : [];

        if ($step === 'collect_subject' && empty($data)) {
            $data = $this->mergeFromFreeform($data, $input);
            $data = $this->autoFillGoogleSync($user, $data);
            $missing = $this->missingFields($data);
            if ($missing === []) {
                return $this->execute($user, $data);
            }
            if ($this->filledSomething($data)) {
                $next = $missing[0];

                return $this->continue($next, $data, $this->promptFor($next));
            }
        }

        $data = $this->applyStep($user, $step, $data, $input);
        $data = $this->autoFillGoogleSync($user, $data);
        $missing = $this->missingFields($data);

        if ($missing !== []) {
            $next = $missing[0];

            return $this->continue($next, $data, $this->promptFor($next, $data));
        }

        return $this->execute($user, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeFromFreeform(array $data, string $input): array
    {
        $date = NovaVoiceInputParser::parseDate($input);
        $time = NovaVoiceInputParser::parseTime($input);
        if ($date && empty($data['date'])) {
            $data['date'] = $date;
        }
        if ($time && empty($data['time'])) {
            $data['time'] = $time;
        }

        $yesNo = NovaVoiceInputParser::parseYesNo($input);
        // Only treat as attach-client when the utterance mentions client.
        $n = mb_strtolower($input);
        if ($yesNo !== null && preg_match('/\b(client|cliente|clients|klien)\b/u', $n) === 1 && ! isset($data['attach_client'])) {
            $data['attach_client'] = $yesNo;
        }

        // Subject: strip known date/time fragments when possible.
        $subject = trim(preg_replace(
            '/\b(today|tomorrow|demain|aujourd\'hui|aujourdhui|\d{1,2}[\/.\-]\d{1,2}(?:[\/.\-]\d{2,4})?|\d{1,2}\s*(?:h|:)\s*\d{0,2}|am|pm)\b/iu',
            ' ',
            $input,
        ) ?? $input);
        $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);
        if ($subject !== '' && mb_strlen($subject) >= 2 && empty($data['subject'])) {
            // Avoid treating bare yes/no as subject.
            if (NovaVoiceInputParser::parseYesNo($subject) === null) {
                $data['subject'] = mb_substr($subject, 0, 200);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyStep(User $user, string $step, array $data, string $input): array
    {
        return match ($step) {
            'collect_subject' => $this->applySubject($data, $input),
            'collect_date' => $this->applyDate($data, $input),
            'collect_time' => $this->applyTime($data, $input),
            'collect_meeting_type' => $this->applyMeetingType($data, $input),
            'collect_sync_google' => $this->applySyncGoogle($data, $input),
            'collect_message' => $this->applyMessage($data, $input),
            'collect_attach_client' => $this->applyAttachClient($data, $input),
            'collect_client' => $this->applyClient($user, $data, $input),
            'collect_send_invite' => $this->applySendInvite($data, $input),
            default => $data,
        };
    }

    /**
     * When Google Calendar is connected, always sync — never ask.
     * When not connected, skip the sync question (nothing to sync to).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function autoFillGoogleSync(User $user, array $data): array
    {
        if (array_key_exists('sync_google', $data)) {
            return $data;
        }

        if (empty($data['meeting_link_type']) || ! empty($data['_meeting_type_invalid'])) {
            return $data;
        }

        $company = $user->company;
        $data['sync_google'] = $company !== null && $this->google->isConnected($company);
        unset($data['_sync_invalid']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applySubject(array $data, string $input): array
    {
        $subject = trim($input);
        if ($subject !== '') {
            $data['subject'] = mb_substr($subject, 0, 200);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyTime(array $data, string $input): array
    {
        if ($this->isSkip($input)) {
            $data['time'] = '';

            return $data;
        }

        // Accept already-normalized HH:MM from mobile time picker.
        if (preg_match('/^\s*([01]?\d|2[0-3]):([0-5]\d)\s*$/u', $input, $m) === 1) {
            $data['time'] = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            unset($data['_time_invalid']);

            return $data;
        }

        $time = NovaVoiceInputParser::parseTime($input);
        if ($time) {
            $data['time'] = $time;
            unset($data['_time_invalid']);
        } else {
            $data['_time_invalid'] = true;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyMeetingType(array $data, string $input): array
    {
        $type = $this->parseMeetingType($input);
        if ($type === null) {
            $data['_meeting_type_invalid'] = true;

            return $data;
        }

        $data['meeting_link_type'] = $type->value;
        unset($data['_meeting_type_invalid']);

        // Google Meet always syncs via Meet conference creation.
        if ($type === CalendarMeetingLinkType::GoogleMeet) {
            $data['sync_google'] = true;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applySyncGoogle(array $data, string $input): array
    {
        $yesNo = NovaVoiceInputParser::parseYesNo($input);
        if ($yesNo === null) {
            $data['_sync_invalid'] = true;

            return $data;
        }

        $data['sync_google'] = $yesNo;
        unset($data['_sync_invalid']);

        return $data;
    }

    private function parseMeetingType(string $input): ?CalendarMeetingLinkType
    {
        $n = mb_strtolower(trim($input));
        if ($n === '') {
            return null;
        }

        // Mobile chip values.
        if (in_array($n, ['google_meet', 'meet', 'google meet', 'google'], true)
            || str_contains($n, 'google meet')
            || str_contains($n, 'googlemeet')
            || preg_match('/\bmeet\b/u', $n) === 1) {
            return CalendarMeetingLinkType::GoogleMeet;
        }

        if (in_array($n, ['zoom'], true) || str_contains($n, 'zoom')) {
            return CalendarMeetingLinkType::Zoom;
        }

        if (in_array($n, ['none', 'event', 'plain', 'anniversary', 'reminder', 'note', 'just event'], true)
            || str_contains($n, 'anniversary')
            || str_contains($n, 'sans lien')
            || str_contains($n, 'no link')
            || str_contains($n, 'juste')
            || str_contains($n, 'événement simple')
            || str_contains($n, 'evenement simple')) {
            return CalendarMeetingLinkType::None;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyDate(array $data, string $input): array
    {
        // Accept already-normalized Y-m-d from mobile date picker.
        if (preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})\s*$/u', $input, $m) === 1) {
            $data['date'] = sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            unset($data['_date_invalid']);

            return $data;
        }

        $date = NovaVoiceInputParser::parseDate($input);
        if ($date) {
            $data['date'] = $date;
            unset($data['_date_invalid']);
            $time = NovaVoiceInputParser::parseTime($input);
            if ($time && empty($data['time'])) {
                $data['time'] = $time;
            }
        } else {
            $data['_date_invalid'] = true;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyMessage(array $data, string $input): array
    {
        if ($this->isSkip($input)) {
            $data['message'] = '';

            return $data;
        }

        $data['message'] = mb_substr(trim($input), 0, 5000);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyAttachClient(array $data, string $input): array
    {
        $yesNo = NovaVoiceInputParser::parseYesNo($input);
        if ($yesNo === null) {
            $data['_attach_invalid'] = true;

            return $data;
        }

        $data['attach_client'] = $yesNo;
        if (! $yesNo) {
            $data['client_id'] = null;
            $data['send_invite'] = false;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyClient(User $user, array $data, string $input): array
    {
        $target = trim(mb_strtolower($input));
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        $matches = Client::query()
            ->where('company_id', $user->company_id)
            ->where(function ($q) use ($target, $digits): void {
                $q->where('name', 'like', '%'.$target.'%');
                if (strlen($digits) >= 6) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'.',''),'+',''),'(','') LIKE ?",
                        ['%'.$digits.'%'],
                    );
                }
                if (str_contains($target, '@')) {
                    $q->orWhere('email', 'like', '%'.$target.'%');
                }
            })
            ->limit(8)
            ->get();

        if ($matches->isEmpty()) {
            $data['_client_not_found'] = true;

            return $data;
        }

        $client = $matches->count() === 1
            ? $matches->first()
            : ($matches->first(fn (Client $c) => mb_strtolower(trim((string) $c->name)) === $target)
                ?: $matches->first(fn (Client $c) => str_starts_with(mb_strtolower((string) $c->name), $target))
                ?: $matches->first());

        if (! $client) {
            $data['_client_not_found'] = true;

            return $data;
        }

        $data['client_id'] = (string) $client->id;
        $data['client_name'] = (string) $client->name;
        $data['client_email'] = trim((string) ($client->email ?? ''));
        unset($data['_client_not_found']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applySendInvite(array $data, string $input): array
    {
        $yesNo = NovaVoiceInputParser::parseYesNo($input);
        if ($yesNo === null) {
            $data['_invite_invalid'] = true;

            return $data;
        }

        $data['send_invite'] = $yesNo;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function missingFields(array $data): array
    {
        $missing = [];

        if (empty($data['subject'])) {
            $missing[] = 'collect_subject';
        }
        if (empty($data['date']) || ! empty($data['_date_invalid'])) {
            $missing[] = 'collect_date';
        }
        if (! array_key_exists('time', $data) || ! empty($data['_time_invalid'])) {
            $missing[] = 'collect_time';
        }
        if (empty($data['meeting_link_type']) || ! empty($data['_meeting_type_invalid'])) {
            $missing[] = 'collect_meeting_type';
        }
        if (! array_key_exists('sync_google', $data) || ! empty($data['_sync_invalid'])) {
            $missing[] = 'collect_sync_google';
        }
        if (! array_key_exists('message', $data)) {
            $missing[] = 'collect_message';
        }
        if (! array_key_exists('attach_client', $data) || ! empty($data['_attach_invalid'])) {
            $missing[] = 'collect_attach_client';
        }
        if (($data['attach_client'] ?? false) === true && (empty($data['client_id']) || ! empty($data['_client_not_found']))) {
            $missing[] = 'collect_client';
        }
        if (($data['attach_client'] ?? false) === true
            && ! empty($data['client_id'])
            && ! array_key_exists('send_invite', $data)
        ) {
            $missing[] = 'collect_send_invite';
        }
        if (! empty($data['_invite_invalid'])) {
            $missing[] = 'collect_send_invite';
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function promptFor(string $step, array $data = []): string
    {
        if (! empty($data['_date_invalid'])) {
            return __('nova_workflow_create_event_invalid_date');
        }
        if (! empty($data['_time_invalid'])) {
            return __('nova_workflow_create_event_invalid_time');
        }
        if (! empty($data['_meeting_type_invalid'])) {
            return __('nova_workflow_create_event_invalid_meeting_type');
        }
        if (! empty($data['_sync_invalid']) || ! empty($data['_attach_invalid']) || ! empty($data['_invite_invalid'])) {
            return __('nova_workflow_empty_input');
        }
        if (! empty($data['_client_not_found'])) {
            return __('nova_workflow_create_event_client_not_found');
        }

        return match ($step) {
            'collect_subject' => __('nova_workflow_create_event_ask_subject'),
            'collect_date' => __('nova_workflow_create_event_ask_date'),
            'collect_time' => __('nova_workflow_create_event_ask_time'),
            'collect_meeting_type' => __('nova_workflow_create_event_ask_meeting_type'),
            'collect_sync_google' => __('nova_workflow_create_event_ask_sync_google'),
            'collect_message' => __('nova_workflow_create_event_ask_message'),
            'collect_attach_client' => __('nova_workflow_create_event_ask_client'),
            'collect_client' => __('nova_workflow_create_event_ask_client_name'),
            'collect_send_invite' => __('nova_workflow_create_event_ask_invite'),
            default => __('nova_workflow_create_event_prompt'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>}
     */
    private function continue(string $step, array $data, string $reply): array
    {
        // Clear one-shot error flags after prompting.
        unset(
            $data['_date_invalid'],
            $data['_time_invalid'],
            $data['_meeting_type_invalid'],
            $data['_sync_invalid'],
            $data['_attach_invalid'],
            $data['_invite_invalid'],
            $data['_client_not_found'],
        );

        return [
            'active' => true,
            'workflow' => 'create_event',
            'reply' => $reply,
            'done' => false,
            'redirect_url' => null,
            'step' => $step,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string}
     */
    private function execute(User $user, array $data): array
    {
        $company = $user->company;
        if (! $company) {
            return [
                'active' => false,
                'workflow' => null,
                'reply' => __('nova_workflow_no_company'),
                'done' => true,
                'redirect_url' => null,
            ];
        }

        $clientId = ($data['attach_client'] ?? false) ? ($data['client_id'] ?? null) : null;
        $client = null;
        if ($clientId) {
            $client = Client::query()->where('company_id', $company->id)->find($clientId);
            if (! $client) {
                return $this->continue('collect_client', $data, __('nova_workflow_create_event_client_not_found'));
            }
        }

        $title = (string) $data['subject'];
        $date = (string) $data['date'];
        $time = isset($data['time']) && $data['time'] !== '' ? (string) $data['time'] : null;
        $message = isset($data['message']) && $data['message'] !== '' ? (string) $data['message'] : null;
        $sendInvite = (bool) ($data['send_invite'] ?? false);
        $syncGoogle = (bool) ($data['sync_google'] ?? $this->google->isConnected($company));

        $linkType = CalendarMeetingLinkType::tryFrom((string) ($data['meeting_link_type'] ?? 'none'))
            ?? CalendarMeetingLinkType::None;

        if ($linkType === CalendarMeetingLinkType::GoogleMeet && ! $this->google->isConnected($company)) {
            $linkType = CalendarMeetingLinkType::None;
        }

        try {
            $meetingFields = $this->meetings->resolveForNewEvent(
                $company,
                $linkType,
                null,
                $title,
                $date,
            );
        } catch (\Throwable $e) {
            return [
                'active' => false,
                'workflow' => null,
                'reply' => __('nova_workflow_create_event_link_failed', ['error' => $e->getMessage()]),
                'done' => true,
                'redirect_url' => null,
            ];
        }

        $kind = $client || $linkType !== CalendarMeetingLinkType::None ? 'meeting' : 'appointment';

        $event = WorkspaceCalendarEvent::query()->create([
            'company_id' => $company->id,
            'client_id' => $client?->id,
            'created_by' => $user->id,
            'title' => $title,
            'description' => $message,
            'starts_on' => $date,
            'start_time' => $time,
            'kind' => $kind,
            'source_type' => 'nova_voice',
            ...$meetingFields,
        ]);

        if ($linkType === CalendarMeetingLinkType::GoogleMeet && $this->google->isConnected($company)) {
            $this->meetings->finalizeGoogleMeet($event);
            $event->refresh();
        }

        if ($syncGoogle && $this->google->isConnected($company)) {
            $this->google->syncWorkspaceEvent($event, $linkType === CalendarMeetingLinkType::GoogleMeet);
            $event->refresh();
        }

        $inviteNote = '';
        if ($client && $sendInvite) {
            $email = trim((string) ($client->email ?? ''));
            if ($email === '') {
                $inviteNote = ' '.__('nova_workflow_create_event_invite_no_email');
            } else {
                $meetingUrl = $this->meetings->publicMeetingUrl($event);
                $mailable = new ClientMeetingInviteMail(
                    $client,
                    $company,
                    $event,
                    $meetingUrl,
                    (string) $user->name,
                );
                app(TenantSmtpMailer::class)->send($company, $mailable, $email);
                $event->update(['invite_sent_at' => now()]);
                $this->prospects->verifyByEmail($company, $email, ProspectVerifiedVia::Meeting);
                $inviteNote = ' '.__('nova_workflow_create_event_invite_sent');
            }
        }

        $when = $date.($time ? ' '.$time : '');
        $typeLabel = $linkType->label();
        $reply = __('nova_workflow_create_event_success', [
            'title' => $title,
            'when' => $when,
        ]);
        $reply .= ' '.__('nova_workflow_create_event_type_note', ['type' => $typeLabel]);
        if ($syncGoogle && filled($event->google_calendar_event_id)) {
            $reply .= ' '.__('nova_workflow_create_event_synced');
        }
        $reply .= $inviteNote;

        $redirect = Route::has('calendar.index')
            ? route('calendar.index')
            : null;
        if ($client && Route::has('clients.show')) {
            $redirect = route('clients.show', [$client, 'tab' => 'meetings', 'meeting' => $event->id]);
        }

        return [
            'active' => false,
            'workflow' => null,
            'reply' => $reply,
            'done' => true,
            'redirect_url' => $redirect,
            'result' => [
                'type' => 'event',
                'id' => (string) $event->id,
                'title' => $title,
                'date' => $date,
                'time' => $time,
                'meeting_link_type' => $linkType->value,
                'meeting_url' => $this->meetings->publicMeetingUrl($event),
                'google_synced' => filled($event->google_calendar_event_id),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function filledSomething(array $data): bool
    {
        foreach (['subject', 'date', 'time', 'message', 'attach_client'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    private function isSkip(string $input): bool
    {
        $normalized = mb_strtolower(trim($input));

        return in_array($normalized, [
            'skip', 'none', 'no', 'non', 'pas de', 'sans', 'aucun', 'n/a', 'na',
            'tidak', 'بدون', 'खाली',
        ], true);
    }
}
