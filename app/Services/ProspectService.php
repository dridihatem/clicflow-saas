<?php

namespace App\Services;

use App\Enums\CalendarMeetingLinkType;
use App\Enums\ClientSource;
use App\Enums\ClientStatus;
use App\Enums\ProspectActivityType;
use App\Enums\ProspectSource;
use App\Enums\ProspectStatus;
use App\Enums\ProspectVerifiedVia;
use App\Mail\ProspectMessageMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\EmailMarketingAudience;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Models\ProspectImport;
use App\Models\User;
use App\Models\WorkspaceCalendarEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class ProspectService
{
    private const SOCIAL_KEYS = ['linkedin', 'twitter', 'facebook', 'instagram', 'website'];

    public function __construct(
        private ?PlatformLlmRouter $llm = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{import: ProspectImport, created: int, updated: int, errors: list<string>}
     */
    public function importRows(Company $company, User $user, array $rows, ?string $filename = null): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 1;
            $email = $this->normalizeEmail($row['email'] ?? null);
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '' && $email === null) {
                $errors[] = __('prospect_import_row_missing_identity', ['line' => $line]);

                continue;
            }

            if ($name === '') {
                $name = Str::before((string) $email, '@') ?: __('Prospect');
            }

            $payload = [
                'name' => mb_substr($name, 0, 255),
                'phone' => $this->nullableString($row['phone'] ?? null, 60),
                'whatsapp' => $this->nullableString($row['whatsapp'] ?? null, 60),
                'company_name' => $this->nullableString($row['company_name'] ?? $row['company'] ?? null, 255),
                'job_title' => $this->nullableString($row['job_title'] ?? $row['title'] ?? null, 255),
                'socials' => $this->socialsFromRow($row),
                'source' => $this->resolveSource($row['source'] ?? null, ProspectSource::Import),
                'notes' => $this->nullableString($row['notes'] ?? null, 5000),
            ];
            $importedStatus = $this->statusFromText($row['status'] ?? null);
            // On update only overwrite what the row provides (empty cells keep existing data).
            $updatePayload = array_filter($payload, fn ($v) => $v !== null && $v !== []);
            if (! is_string($row['source'] ?? null) || trim((string) $row['source']) === '') {
                unset($updatePayload['source']);
            }

            if ($email !== null) {
                $existing = Prospect::query()
                    ->withoutGlobalScopes()
                    ->withTrashed()
                    ->where('company_id', $company->id)
                    ->where('email', $email)
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    if (isset($updatePayload['socials'])) {
                        $updatePayload['socials'] = array_merge((array) ($existing->socials ?? []), $updatePayload['socials']);
                    }
                    $existing->fill($updatePayload);
                    if ($importedStatus !== null && $existing->status !== ProspectStatus::Converted) {
                        $existing->status = $importedStatus;
                    } elseif ($existing->status === ProspectStatus::Archived || $existing->status === ProspectStatus::Converted) {
                        // Keep converted; reopen archived on re-import.
                        if ($existing->status === ProspectStatus::Archived) {
                            $existing->status = ProspectStatus::New;
                        }
                    }
                    $existing->save();
                    $updated++;
                } else {
                    Prospect::query()->create([
                        ...$payload,
                        'company_id' => $company->id,
                        'email' => $email,
                        'status' => $importedStatus ?? ProspectStatus::New,
                    ]);
                    $created++;
                }
            } else {
                Prospect::query()->create([
                    ...$payload,
                    'company_id' => $company->id,
                    'email' => null,
                    'status' => $importedStatus ?? ProspectStatus::New,
                ]);
                $created++;
            }
        }

        $import = ProspectImport::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'filename' => $filename,
            'row_count' => count($rows),
            'created_count' => $created,
            'updated_count' => $updated,
            'error_count' => count($errors),
            'errors' => $errors === [] ? null : array_slice($errors, 0, 50),
        ]);

        return compact('import', 'created', 'updated', 'errors');
    }

    /**
     * @return list<array<string, string|null>>
     */
    public function parseDelimited(string $raw): array
    {
        // RFC 4180 parsing: quoted cells may span several lines ("Description" with bullets),
        // quotes are escaped by doubling them. Splitting on "\n" first broke such rows apart.
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (trim($raw) === '') {
            return [];
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $raw);
        rewind($handle);

        $firstLine = strtok(ltrim($raw, "\n"), "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $headers = null;
        $rows = [];
        while (($cols = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($cols === [null] || collect($cols)->every(fn ($c) => trim((string) $c) === '')) {
                continue;
            }
            if ($headers === null) {
                $headers = $this->normalizeHeaders($cols);

                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                if ($header === null) {
                    continue;
                }
                $row[$header] = isset($cols[$i]) ? trim((string) $cols[$i]) : null;
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $headerLine): string
    {
        // Count delimiters outside quoted sections of the header line.
        $unquoted = (string) preg_replace('/"[^"]*"/', '', $headerLine);
        $counts = [
            "\t" => substr_count($unquoted, "\t"),
            ';' => substr_count($unquoted, ';'),
            ',' => substr_count($unquoted, ','),
        ];
        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    public function markContactedForEmails(Company $company, iterable $emails): int
    {
        $normalized = collect($emails)
            ->map(fn ($email) => $this->normalizeEmail($email))
            ->filter()
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return 0;
        }

        return Prospect::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('email', $normalized->all())
            ->whereIn('status', [ProspectStatus::New->value])
            ->update(['status' => ProspectStatus::Contacted->value]);
    }

    public function verifyByEmail(Company $company, ?string $email, ProspectVerifiedVia $via): ?Prospect
    {
        $email = $this->normalizeEmail($email);
        if ($email === null) {
            return null;
        }

        $prospect = Prospect::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('email', $email)
            ->whereNotIn('status', [ProspectStatus::Converted->value, ProspectStatus::Archived->value])
            ->first();

        if (! $prospect) {
            return null;
        }

        if ($prospect->status !== ProspectStatus::Verified && $prospect->status !== ProspectStatus::Converted) {
            $prospect->forceFill([
                'status' => ProspectStatus::Verified,
                'verified_at' => now(),
                'verified_via' => $via,
            ])->save();
        } elseif ($prospect->verified_at === null) {
            $prospect->forceFill([
                'verified_at' => now(),
                'verified_via' => $via,
            ])->save();
        }

        return $prospect->fresh();
    }

    public function convertToClient(Prospect $prospect, ClientStatus $clientStatus = ClientStatus::Active, ?User $actor = null): Client
    {
        $companyId = $prospect->company_id;
        $email = $this->normalizeEmail($prospect->email);

        $clientSource = match ($prospect->source) {
            ProspectSource::WebsiteForm, ProspectSource::Web => ClientSource::WebsiteForm,
            ProspectSource::Referral => ClientSource::Referral,
            ProspectSource::SocialMedia, ProspectSource::Linkedin, ProspectSource::Facebook, ProspectSource::Instagram => ClientSource::SocialMedia,
            ProspectSource::Advertising, ProspectSource::Google => ClientSource::Advertising,
            ProspectSource::Inquiry => ClientSource::Inquiry,
            ProspectSource::BusinessProvider => ClientSource::BusinessProvider,
            ProspectSource::Manual, ProspectSource::Internal => ClientSource::Manual,
            default => ClientSource::Other,
        };

        if ($prospect->converted_client_id) {
            $client = Client::query()->withoutGlobalScopes()->findOrFail($prospect->converted_client_id);
            if ($clientStatus === ClientStatus::Active && $client->status !== ClientStatus::Active) {
                $client->forceFill(['status' => ClientStatus::Active])->save();
            }
        } else {
            $attributes = [
                'name' => $prospect->name,
                'phone' => $prospect->phone,
                'source' => $clientSource->value,
                'status' => $clientStatus,
            ];

            if ($email !== null) {
                $client = Client::query()->withoutGlobalScopes()->firstOrCreate(
                    ['company_id' => $companyId, 'email' => $email],
                    $attributes,
                );
                if (! $client->wasRecentlyCreated) {
                    $fill = array_filter([
                        'name' => $attributes['name'],
                        'phone' => $attributes['phone'],
                        'source' => $attributes['source'],
                    ], fn ($v) => $v !== null);
                    if ($clientStatus === ClientStatus::Active) {
                        $fill['status'] = ClientStatus::Active;
                    } elseif ($client->status === null) {
                        $fill['status'] = $clientStatus;
                    }
                    $client->fill($fill)->save();
                }
            } else {
                $client = Client::query()->withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'email' => null,
                    ...$attributes,
                ]);
            }
        }

        if ($clientStatus === ClientStatus::Active) {
            $prospect->forceFill([
                'converted_client_id' => $client->id,
                'status' => ProspectStatus::Converted,
                'verified_at' => $prospect->verified_at ?? now(),
                'verified_via' => $prospect->verified_via ?? ProspectVerifiedVia::Manual,
            ])->save();

            $this->logActivity($prospect, ProspectActivityType::Converted, [
                'title' => __('prospect_activity_converted'),
                'body' => __('prospect_converted_activity_body', ['client' => $client->name]),
                'user' => $actor,
                'meta' => ['client_id' => $client->id, 'client_status' => $clientStatus->value],
            ]);
        } else {
            $prospect->forceFill([
                'converted_client_id' => $client->id,
                'verified_at' => $prospect->verified_at ?? now(),
                'verified_via' => $prospect->verified_via ?? ProspectVerifiedVia::Manual,
            ])->save();

            if (! in_array($prospect->status, [ProspectStatus::Converted, ProspectStatus::Archived, ProspectStatus::Engaged, ProspectStatus::Verified], true)) {
                $prospect->forceFill(['status' => ProspectStatus::Engaged])->save();
            }
        }

        return $client->fresh();
    }

    /**
     * Soft-convert to a potential client so a quote (devis) can be created.
     */
    public function prepareQuote(Prospect $prospect, User $actor, ?string $notes = null): Client
    {
        $client = $this->convertToClient($prospect, ClientStatus::Potential, $actor);

        $this->logActivity($prospect->fresh(), ProspectActivityType::Quote, [
            'title' => __('prospect_quote_prepared'),
            'body' => $notes,
            'user' => $actor,
            'meta' => ['client_id' => $client->id],
        ]);

        return $client;
    }

    /**
     * @param  array{title?: string|null, body?: string|null, user?: User|null, meta?: array<string, mixed>|null, occurred_at?: Carbon|null, due_at?: Carbon|null}  $payload
     */
    public function logActivity(Prospect $prospect, ProspectActivityType $type, array $payload = []): ProspectActivity
    {
        $activity = ProspectActivity::query()->create([
            'company_id' => $prospect->company_id,
            'prospect_id' => $prospect->id,
            'user_id' => $payload['user']?->id ?? null,
            'type' => $type,
            'title' => $payload['title'] ?? $type->label(),
            'body' => $payload['body'] ?? null,
            'meta' => $payload['meta'] ?? null,
            'occurred_at' => $payload['occurred_at'] ?? now(),
            'due_at' => $payload['due_at'] ?? null,
        ]);

        $this->advanceStatusForActivity($prospect, $type);

        return $activity;
    }

    public function ensureSourceActivity(Prospect $prospect, ?User $actor = null): void
    {
        $exists = ProspectActivity::query()
            ->where('prospect_id', $prospect->id)
            ->where('type', ProspectActivityType::Source->value)
            ->exists();

        if ($exists) {
            return;
        }

        $this->logActivity($prospect, ProspectActivityType::Source, [
            'title' => $prospect->source?->label() ?? __('prospect_source_label'),
            'body' => __('prospect_source_captured_body'),
            'user' => $actor,
            'meta' => ['source' => $prospect->source?->value],
            'occurred_at' => $prospect->created_at ?? now(),
        ]);
    }

    /**
     * @return array{activity: ProspectActivity, event: WorkspaceCalendarEvent|null}
     */
    public function logCall(Prospect $prospect, User $actor, string $summary, ?string $outcome = null, ?Carbon $occurredAt = null): array
    {
        $body = trim($summary.($outcome ? "\n\n".__('prospect_call_outcome').': '.$outcome : ''));

        $activity = $this->logActivity($prospect, ProspectActivityType::Call, [
            'title' => __('prospect_call_logged'),
            'body' => $body,
            'user' => $actor,
            'meta' => array_filter(['outcome' => $outcome]),
            'occurred_at' => $occurredAt ?? now(),
        ]);

        return ['activity' => $activity, 'event' => null];
    }

    public function logFeedback(Prospect $prospect, User $actor, string $feedback, ?int $rating = null): ProspectActivity
    {
        return $this->logActivity($prospect, ProspectActivityType::Feedback, [
            'title' => __('prospect_feedback_logged'),
            'body' => $feedback,
            'user' => $actor,
            'meta' => array_filter(['rating' => $rating]),
        ]);
    }

    /**
     * @return array{activity: ProspectActivity, event: WorkspaceCalendarEvent}
     */
    public function scheduleMeeting(
        Prospect $prospect,
        User $actor,
        string $title,
        string $date,
        ?string $startTime = null,
        ?string $notes = null,
    ): array {
        $event = WorkspaceCalendarEvent::query()->create([
            'company_id' => $prospect->company_id,
            'client_id' => $prospect->converted_client_id,
            'created_by' => $actor->id,
            'title' => $title,
            'description' => $notes,
            'starts_on' => $date,
            'start_time' => $startTime,
            'kind' => 'meeting',
            'meeting_link_type' => CalendarMeetingLinkType::None,
        ]);

        $activity = $this->logActivity($prospect, ProspectActivityType::Meeting, [
            'title' => $title,
            'body' => $notes,
            'user' => $actor,
            'meta' => [
                'calendar_event_id' => $event->id,
                'date' => $date,
                'start_time' => $startTime,
            ],
            'occurred_at' => Carbon::parse($date.($startTime ? ' '.$startTime : '')),
        ]);

        return ['activity' => $activity, 'event' => $event];
    }

    /**
     * @return array{activity: ProspectActivity, event: WorkspaceCalendarEvent}
     */
    public function scheduleReminder(
        Prospect $prospect,
        User $actor,
        string $title,
        string $date,
        ?string $startTime = null,
        ?string $notes = null,
    ): array {
        $event = WorkspaceCalendarEvent::query()->create([
            'company_id' => $prospect->company_id,
            'client_id' => $prospect->converted_client_id,
            'created_by' => $actor->id,
            'title' => $title,
            'description' => $notes,
            'starts_on' => $date,
            'start_time' => $startTime,
            'kind' => 'reminder',
            'meeting_link_type' => CalendarMeetingLinkType::None,
        ]);

        $dueAt = Carbon::parse($date.($startTime ? ' '.$startTime : ''));

        $activity = $this->logActivity($prospect, ProspectActivityType::Reminder, [
            'title' => $title,
            'body' => $notes,
            'user' => $actor,
            'meta' => [
                'calendar_event_id' => $event->id,
                'date' => $date,
                'start_time' => $startTime,
            ],
            'due_at' => $dueAt,
        ]);

        return ['activity' => $activity, 'event' => $event];
    }

    public function sendMail(Prospect $prospect, User $actor, Company $company, string $subject, string $body): ProspectActivity
    {
        $email = $this->normalizeEmail($prospect->email);
        if ($email === null) {
            throw new RuntimeException(__('prospect_mail_no_email'));
        }

        $mailable = new ProspectMessageMail(
            $company,
            $prospect,
            $subject,
            $body,
            (string) $actor->name,
        );
        app(TenantSmtpMailer::class)->send($company, $mailable, $email);

        return $this->logActivity($prospect, ProspectActivityType::Mailing, [
            'title' => $subject,
            'body' => $body,
            'user' => $actor,
            'meta' => ['to' => $email],
        ]);
    }

    /**
     * @return array{activity: ProspectActivity, pitch: string, next_steps: list<string>, talking_points: list<string>}
     */
    public function generateCatchProposal(Prospect $prospect, User $actor, Company $company): array
    {
        $llm = $this->llm ?? app(PlatformLlmRouter::class);

        if (! $llm->isAvailable($company)) {
            throw new RuntimeException(__('prospect_ai_unavailable'));
        }

        $activities = $prospect->activities()->limit(20)->get();
        $history = $activities->map(fn (ProspectActivity $a) => sprintf(
            '- [%s] %s%s',
            $a->type->value,
            $a->title ?? '',
            $a->body ? ': '.Str::limit(strip_tags((string) $a->body), 180) : '',
        ))->implode("\n");

        $system = 'You help sales teams convert prospects into clients. '
            .'Return strict JSON with keys: pitch (string, persuasive short email/call pitch in the same language as the prospect context), '
            .'next_steps (array of 3-5 concrete actions), talking_points (array of 3-5 bullets). No markdown.';

        $user = "Prospect:\n"
            ."Name: {$prospect->name}\n"
            .'Company: '.($prospect->company_name ?? 'n/a')."\n"
            .'Job: '.($prospect->job_title ?? 'n/a')."\n"
            .'Source: '.($prospect->source?->label() ?? 'n/a')."\n"
            .'Status: '.($prospect->status?->label() ?? 'n/a')."\n"
            .'Notes: '.($prospect->notes ?? 'n/a')."\n"
            ."Workspace: {$company->name}\n"
            ."Activity history:\n".($history !== '' ? $history : '(none yet)');

        $result = $llm->completeFast($system, $user, 900, $company, true);
        $parsed = $this->parseAiJson((string) ($result['suggestion'] ?? ''));

        $pitch = trim((string) ($parsed['pitch'] ?? ''));
        $nextSteps = array_values(array_filter(array_map('strval', $parsed['next_steps'] ?? [])));
        $talkingPoints = array_values(array_filter(array_map('strval', $parsed['talking_points'] ?? [])));

        if ($pitch === '') {
            throw new RuntimeException(__('prospect_ai_failed'));
        }

        $body = $pitch;
        if ($nextSteps !== []) {
            $body .= "\n\n".__('prospect_ai_next_steps').":\n- ".implode("\n- ", $nextSteps);
        }
        if ($talkingPoints !== []) {
            $body .= "\n\n".__('prospect_ai_talking_points').":\n- ".implode("\n- ", $talkingPoints);
        }

        $activity = $this->logActivity($prospect, ProspectActivityType::AiSuggestion, [
            'title' => __('prospect_ai_catch_title'),
            'body' => $body,
            'user' => $actor,
            'meta' => [
                'pitch' => $pitch,
                'next_steps' => $nextSteps,
                'talking_points' => $talkingPoints,
                'model' => $result['model'] ?? null,
            ],
        ]);

        return [
            'activity' => $activity,
            'pitch' => $pitch,
            'next_steps' => $nextSteps,
            'talking_points' => $talkingPoints,
        ];
    }

    private function advanceStatusForActivity(Prospect $prospect, ProspectActivityType $type): void
    {
        if (in_array($prospect->status, [ProspectStatus::Converted, ProspectStatus::Archived], true)) {
            return;
        }

        $target = match ($type) {
            ProspectActivityType::Call, ProspectActivityType::Mailing, ProspectActivityType::Reminder => ProspectStatus::Contacted,
            ProspectActivityType::Feedback, ProspectActivityType::Meeting, ProspectActivityType::Quote, ProspectActivityType::AiSuggestion => ProspectStatus::Engaged,
            ProspectActivityType::Converted => ProspectStatus::Converted,
            default => null,
        };

        if ($target === null) {
            return;
        }

        $rank = [
            ProspectStatus::New->value => 0,
            ProspectStatus::Contacted->value => 1,
            ProspectStatus::Engaged->value => 2,
            ProspectStatus::Verified->value => 3,
            ProspectStatus::Converted->value => 4,
        ];

        $current = $rank[$prospect->status->value] ?? 0;
        $wanted = $rank[$target->value] ?? 0;

        if ($wanted > $current) {
            $prospect->forceFill(['status' => $target])->save();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAiJson(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['pitch' => $raw, 'next_steps' => [], 'talking_points' => []];
    }

    /**
     * @param  Collection<int, Prospect>|list<Prospect>  $prospects
     */
    public function syncAudience(Company $company, EmailMarketingAudience $audience, $prospects): int
    {
        $count = 0;
        foreach ($prospects as $prospect) {
            $email = $this->normalizeEmail($prospect->email);
            if ($email === null) {
                continue;
            }

            $audience->contacts()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $prospect->name,
                    'prospect_id' => $prospect->id,
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<string|null>  $headers
     * @return list<string|null>
     */
    private function normalizeHeaders(array $headers): array
    {
        $map = [
            'name' => 'name',
            'full_name' => 'name',
            'fullname' => 'name',
            'email' => 'email',
            'e-mail' => 'email',
            'mail' => 'email',
            'phone' => 'phone',
            'telephone' => 'phone',
            'mobile' => 'phone',
            'whatsapp' => 'whatsapp',
            'wa' => 'whatsapp',
            'company' => 'company_name',
            'company_name' => 'company_name',
            'organisation' => 'company_name',
            'organization' => 'company_name',
            'title' => 'job_title',
            'job_title' => 'job_title',
            'linkedin' => 'linkedin',
            'twitter' => 'twitter',
            'x' => 'twitter',
            'facebook' => 'facebook',
            'instagram' => 'instagram',
            'website' => 'website',
            'web' => 'website',
            'source' => 'source',
            'notes' => 'notes',
            'note' => 'notes',
        ];

        return array_map(function ($header) use ($map) {
            $key = Str::of((string) $header)->lower()->replace([' ', '-'], '_')->toString();

            return $map[$key] ?? ($key !== '' ? $key : null);
        }, $headers);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function socialsFromRow(array $row): array
    {
        $socials = [];
        foreach (self::SOCIAL_KEYS as $key) {
            $value = $this->nullableString($row[$key] ?? null, 500);
            if ($value !== null) {
                $socials[$key] = $value;
            }
        }

        return $socials;
    }

    /**
     * Imported prospect status (FR/EN); Converted is never set from a file.
     */
    private function statusFromText(mixed $value): ?ProspectStatus
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii((string) $value))), '_');

        return match ($s) {
            'nouveau', 'nouvelle', 'new', 'a_contacter' => ProspectStatus::New,
            'contacte', 'contactee', 'contacted', 'relance', 'relancee' => ProspectStatus::Contacted,
            'engage', 'engagee', 'engaged', 'interesse', 'interessee', 'en_cours', 'chaud' => ProspectStatus::Engaged,
            'verifie', 'verifiee', 'verified', 'qualifie', 'qualifiee' => ProspectStatus::Verified,
            'archive', 'archivee', 'archived', 'perdu', 'perdue', 'lost', 'froid' => ProspectStatus::Archived,
            default => null,
        };
    }

    private function resolveSource(mixed $value, ProspectSource $default): ProspectSource
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        $normalized = Str::of($value)->lower()->replace([' ', '-'], '_')->toString();
        $direct = ProspectSource::tryFrom($normalized);
        if ($direct) {
            return $direct;
        }
        $ascii = trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::ascii($value))), '_');

        return match ($ascii) {
            'site', 'site_web', 'site_internet', 'website', 'formulaire', 'formulaire_web', 'formulaire_site' => ProspectSource::WebsiteForm,
            'recommandation', 'parrainage', 'bouche_a_oreille', 'referral' => ProspectSource::Referral,
            'reseaux_sociaux', 'reseau_social', 'social', 'social_media' => ProspectSource::SocialMedia,
            'publicite', 'pub', 'ads', 'annonce' => ProspectSource::Advertising,
            'campagne', 'emailing', 'e_mailing' => ProspectSource::Campaign,
            'demande', 'demande_de_devis', 'inquiry' => ProspectSource::Inquiry,
            'manuel', 'manuelle', 'saisie' => ProspectSource::Manual,
            'autre', 'autres', 'divers' => ProspectSource::Other,
            'interne' => ProspectSource::Internal,
            'apporteur', 'apporteur_d_affaires' => ProspectSource::BusinessProvider,
            default => ProspectSource::tryFrom($ascii) ?? $default,
        };
    }

    private function normalizeEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }
        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
