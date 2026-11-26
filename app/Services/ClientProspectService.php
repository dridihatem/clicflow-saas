<?php

namespace App\Services;

use App\Enums\ClientProspectStatus;
use App\Models\Client;
use App\Models\ClientProspect;
use App\Models\ClientProspectComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientProspectService
{
    /**
     * @param  array{
     *     name: string,
     *     phone?: ?string,
     *     email?: ?string,
     *     campaign?: ?string,
     *     source?: ?string,
     *     status?: string|ClientProspectStatus|null,
     *     comment?: ?string,
     *     captured_on?: mixed,
     *     appointment_at?: mixed
     * }  $data
     */
    public function create(Client $client, array $data, ?User $actor = null): ClientProspect
    {
        return DB::transaction(function () use ($client, $data, $actor): ClientProspect {
            $status = $this->resolveStatus($data['status'] ?? null);
            [$campaignName, $campaignId] = $this->resolveCampaign($client, $data, $actor);
            $prospect = ClientProspect::query()->create([
                'company_id' => $client->company_id,
                'client_id' => $client->id,
                'name' => trim((string) $data['name']),
                'phone' => $this->nullable($data['phone'] ?? null),
                'email' => $this->nullable($data['email'] ?? null),
                'campaign' => $campaignName,
                'campaign_id' => $campaignId,
                'source' => $this->nullable($data['source'] ?? null),
                'status' => $status,
                'comment' => $this->nullable($data['comment'] ?? null),
                'captured_on' => $this->parseDate($data['captured_on'] ?? null) ?? now()->toDateString(),
                'appointment_at' => $this->parseDateTime($data['appointment_at'] ?? null),
                'created_by' => $actor?->id,
            ]);

            $comment = $this->nullable($data['comment'] ?? null);
            if ($comment !== null) {
                $this->addComment($prospect, $comment, $actor, 'comment');
            }

            return $prospect;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClientProspect $prospect, array $data, ?User $actor = null): ClientProspect
    {
        return DB::transaction(function () use ($prospect, $data, $actor): ClientProspect {
            $payload = [];
            foreach (['name', 'phone', 'email', 'source'] as $field) {
                if (array_key_exists($field, $data)) {
                    $payload[$field] = $field === 'name' ? trim((string) $data[$field]) : $this->nullable($data[$field]);
                }
            }
            if (array_key_exists('campaign', $data) || array_key_exists('campaign_id', $data)) {
                [$campaignName, $campaignId] = $this->resolveCampaign($prospect->client ?? $prospect->client()->first(), $data, $actor);
                $payload['campaign'] = $campaignName;
                $payload['campaign_id'] = $campaignId;
            }
            if (array_key_exists('captured_on', $data)) {
                $payload['captured_on'] = $this->parseDate($data['captured_on']) ?? $prospect->captured_on;
            }
            if (array_key_exists('appointment_at', $data)) {
                $payload['appointment_at'] = $this->parseDateTime($data['appointment_at']);
            }
            if (array_key_exists('appointment_status', $data)) {
                $payload['appointment_status'] = $data['appointment_status'] ?: null;
            }
            if (array_key_exists('comment', $data) && $this->nullable($data['comment']) !== null) {
                $payload['comment'] = $this->nullable($data['comment']);
            }
            if (! empty($payload)) {
                $prospect->update($payload);
            }

            if (array_key_exists('status', $data) && $data['status'] !== null) {
                $this->changeStatus($prospect, $this->resolveStatus($data['status']), $actor);
            }

            if (array_key_exists('comment', $data)) {
                $body = $this->nullable($data['comment']);
                if ($body !== null && $body !== (string) $prospect->getOriginal('comment')) {
                    $this->addComment($prospect->fresh(), $body, $actor, 'comment');
                }
            }

            return $prospect->fresh();
        });
    }

    public function changeStatus(ClientProspect $prospect, ClientProspectStatus $status, ?User $actor = null, ?string $note = null): ClientProspect
    {
        $current = $prospect->status instanceof ClientProspectStatus
            ? $prospect->status
            : ClientProspectStatus::tryFrom((string) $prospect->status);

        if ($current === $status) {
            return $prospect;
        }

        $old = $current;
        $prospect->update(['status' => $status]);

        $body = __('client_prospect_status_changed', [
            'from' => $old?->label() ?? '—',
            'to' => $status->label(),
        ]);
        if ($note) {
            $body .= ' — '.$note;
        }

        $this->addComment($prospect, $body, $actor, 'status', [
            'from' => $old?->value,
            'to' => $status->value,
        ]);

        if ($status === ClientProspectStatus::RdvProgramme && $prospect->appointment_at === null) {
            // leave appointment to be set by the caller
        }

        return $prospect->fresh();
    }

    public function addComment(ClientProspect $prospect, string $body, ?User $actor = null, string $kind = 'comment', ?array $meta = null): ClientProspectComment
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages([
                'comment' => __('client_prospect_comment_required'),
            ]);
        }

        $comment = ClientProspectComment::query()->create([
            'company_id' => $prospect->company_id,
            'client_prospect_id' => $prospect->id,
            'user_id' => $actor?->id,
            'kind' => $kind,
            'body' => $body,
            'meta' => $meta,
        ]);

        if ($kind === 'comment') {
            $prospect->update(['comment' => $body]);
        }

        return $comment;
    }

    public function delete(ClientProspect $prospect): void
    {
        $prospect->delete();
    }

    /**
     * @return array{created: int, errors: list<string>}
     */
    public function importCsv(Client $client, UploadedFile $file, ?User $actor = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'csv' => __('client_prospect_import_unreadable'),
            ]);
        }

        $created = 0;
        $errors = [];
        $header = null;
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($row === [null] || $row === false) {
                    continue;
                }
                $cells = array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $row);
                if ($header === null) {
                    $header = $this->mapHeader($cells);
                    if ($header !== null) {
                        continue;
                    }
                    $header = $this->defaultHeader(count($cells));
                }

                $mapped = $this->mapRow($header, $cells);
                if ($mapped === null || ($mapped['name'] ?? '') === '') {
                    if ($this->rowHasAnything($cells)) {
                        $errors[] = __('client_prospect_import_row_missing_name', ['row' => $rowNumber]);
                    }

                    continue;
                }

                try {
                    $this->create($client, $mapped, $actor);
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = __('client_prospect_import_row_failed', [
                        'row' => $rowNumber,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            fclose($handle);
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, int>|null
     */
    private function mapHeader(array $cells): ?array
    {
        $aliases = [
            'name' => ['name', 'nom', 'nom & prenom', 'nom et prenom', 'nom & prénom', 'nom et prénom', 'full name', 'fullname'],
            'phone' => ['phone', 'telephone', 'téléphone', 'tel', 'tél', 'mobile'],
            'email' => ['email', 'e-mail', 'mail', 'courriel'],
            'campaign' => ['campaign', 'campagne'],
            'source' => ['source'],
            'status' => ['status', 'statut', 'etat', 'état'],
            'comment' => ['comment', 'commentaire', 'commentaires', 'notes', 'note'],
            'captured_on' => ['date', 'captured_on', 'captured', 'jour'],
            'appointment_at' => ['appointment', 'appointment_at', 'rdv', 'rendez-vous', 'rendez vous'],
        ];

        $map = [];
        $matched = 0;
        foreach ($cells as $i => $cell) {
            $key = mb_strtolower(trim($cell));
            $key = str_replace(['é', 'è', 'ê', 'à'], ['e', 'e', 'e', 'a'], $key);
            foreach ($aliases as $field => $names) {
                $normalized = array_map(fn ($n) => str_replace(['é', 'è', 'ê', 'à'], ['e', 'e', 'e', 'a'], $n), $names);
                if (in_array($key, $normalized, true)) {
                    $map[$field] = $i;
                    $matched++;
                    break;
                }
            }
        }

        return $matched >= 1 && isset($map['name']) ? $map : null;
    }

    /**
     * @return array<string, int>
     */
    private function defaultHeader(int $count): array
    {
        $order = ['captured_on', 'campaign', 'name', 'phone', 'comment', 'status'];
        $map = [];
        for ($i = 0; $i < min($count, count($order)); $i++) {
            $map[$order[$i]] = $i;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $header
     * @param  list<string>  $cells
     * @return array<string, mixed>|null
     */
    private function mapRow(array $header, array $cells): ?array
    {
        $get = fn (string $field) => isset($header[$field], $cells[$header[$field]]) ? $cells[$header[$field]] : null;
        $name = trim((string) $get('name'));
        if ($name === '') {
            return null;
        }

        return [
            'name' => $name,
            'phone' => $get('phone'),
            'email' => $get('email'),
            'campaign' => $get('campaign'),
            'source' => $get('source'),
            'status' => $get('status'),
            'comment' => $get('comment'),
            'captured_on' => $get('captured_on'),
            'appointment_at' => $get('appointment_at'),
        ];
    }

    /** @param  list<string>  $cells */
    private function rowHasAnything(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    private function resolveStatus(mixed $value): ClientProspectStatus
    {
        if ($value instanceof ClientProspectStatus) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return ClientProspectStatus::Nouveau;
        }

        return ClientProspectStatus::tryFromLabel($value) ?? ClientProspectStatus::Nouveau;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value))->toDateString();
        }
        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            foreach (['d-m-Y', 'd/m/Y', 'd-m-y', 'd/m/y', 'Y-m-d'] as $fmt) {
                try {
                    $dt = Carbon::createFromFormat($fmt, (string) $value);
                    if ($dt !== false) {
                        return $dt->toDateString();
                    }
                } catch (\Throwable) {
                }
            }

            return null;
        }
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($value));
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveCampaign(?Client $client, array $data, ?User $actor): array
    {
        if (! $client) {
            return [$this->nullable($data['campaign'] ?? null), $data['campaign_id'] ?? null];
        }
        $name = $this->nullable($data['campaign'] ?? null);
        $id = $data['campaign_id'] ?? null;
        if (is_string($id) && trim($id) === '') {
            $id = null;
        }
        if ($id) {
            $found = \App\Models\ClientCampaign::query()
                ->where('client_id', $client->id)
                ->where('id', $id)
                ->first();
            if ($found) {
                return [$found->name, $found->id];
            }
        }
        if ($name === null) {
            return [null, null];
        }
        if (! class_exists(ClientCampaignService::class)) {
            return [$name, null];
        }
        $resolved = app(ClientCampaignService::class)->resolveForName($client, $name, $actor);

        return [$resolved?->name ?? $name, $resolved?->id];
    }

}
