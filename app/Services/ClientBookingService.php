<?php

namespace App\Services;

use App\Enums\ClientBookingStatus;
use App\Models\Client;
use App\Models\ClientBooking;
use App\Models\ClientBookingComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientBookingService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Client $client, array $data, ?User $actor = null): ClientBooking
    {
        return DB::transaction(function () use ($client, $data, $actor): ClientBooking {
            $booking = ClientBooking::query()->create([
                'company_id' => $client->company_id,
                'client_id' => $client->id,
                'starts_at' => $this->parseDateTime($data['starts_at'] ?? null),
                'customer_name' => trim((string) $data['customer_name']),
                'phone' => $this->nullable($data['phone'] ?? null),
                'email' => $this->nullable($data['email'] ?? null),
                'service' => $this->nullable($data['service'] ?? null),
                'status' => $this->status($data['status'] ?? null),
                'notes' => $this->nullable($data['notes'] ?? null),
                'created_by' => $actor?->id,
            ]);
            if ($booking->notes) {
                $this->addComment($booking, $booking->notes, $actor, 'comment');
            }

            return $booking;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClientBooking $booking, array $data, ?User $actor = null): ClientBooking
    {
        $payload = [];
        foreach (['customer_name', 'phone', 'email', 'service'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $field === 'customer_name'
                    ? trim((string) $data[$field])
                    : $this->nullable($data[$field]);
            }
        }
        if (array_key_exists('starts_at', $data)) {
            $payload['starts_at'] = $this->parseDateTime($data['starts_at']);
        }
        if (array_key_exists('notes', $data) && $this->nullable($data['notes']) !== null) {
            $payload['notes'] = $this->nullable($data['notes']);
        }
        if ($payload !== []) {
            $booking->update($payload);
        }
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $this->changeStatus($booking, $this->status($data['status']), $actor);
        }
        if (array_key_exists('notes', $data) && $this->nullable($data['notes']) !== null) {
            $this->addComment($booking->fresh(), (string) $this->nullable($data['notes']), $actor, 'comment');
        }

        return $booking->fresh();
    }

    public function changeStatus(ClientBooking $booking, ClientBookingStatus $status, ?User $actor = null): ClientBooking
    {
        $current = $booking->status instanceof ClientBookingStatus
            ? $booking->status
            : ClientBookingStatus::tryFrom((string) $booking->status);
        if ($current === $status) {
            return $booking;
        }
        $booking->update(['status' => $status]);
        $this->addComment($booking, __('client_booking_status_changed', [
            'from' => $current?->label() ?? '—',
            'to' => $status->label(),
        ]), $actor, 'status', ['from' => $current?->value, 'to' => $status->value]);

        return $booking->fresh();
    }

    public function addComment(ClientBooking $booking, string $body, ?User $actor = null, string $kind = 'comment', ?array $meta = null): ClientBookingComment
    {
        $body = trim($body);
        if ($body === '') {
            throw ValidationException::withMessages([
                'notes' => __('client_booking_comment_required'),
            ]);
        }
        $comment = ClientBookingComment::query()->create([
            'company_id' => $booking->company_id,
            'client_booking_id' => $booking->id,
            'user_id' => $actor?->id,
            'kind' => $kind,
            'body' => $body,
            'meta' => $meta,
        ]);
        if ($kind === 'comment') {
            $booking->update(['notes' => $body]);
        }

        return $comment;
    }

    public function delete(ClientBooking $booking): void
    {
        $booking->delete();
    }

    /**
     * @return array{created: int, errors: list<string>}
     */
    public function importCsv(Client $client, UploadedFile $file, ?User $actor = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages(['csv' => __('client_booking_import_unreadable')]);
        }
        $created = 0;
        $errors = [];
        $header = null;
        $rowNumber = 0;
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $cells = array_map(fn ($v) => trim((string) $v), $row);
                if ($header === null) {
                    $header = $this->mapHeader($cells) ?? $this->defaultHeader(count($cells));
                    if ($this->mapHeader($cells) !== null) {
                        continue;
                    }
                }
                $mapped = $this->mapRow($header, $cells);
                if ($mapped === null) {
                    if (implode('', $cells) !== '') {
                        $errors[] = __('client_booking_import_row_missing_name', ['row' => $rowNumber]);
                    }
                    continue;
                }
                try {
                    $this->create($client, $mapped, $actor);
                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = __('client_booking_import_row_failed', ['row' => $rowNumber, 'message' => $e->getMessage()]);
                }
            }
        } finally {
            fclose($handle);
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /** @param list<string> $cells @return array<string,int>|null */
    private function mapHeader(array $cells): ?array
    {
        $aliases = [
            'customer_name' => ['name', 'nom', 'customer', 'client', 'customer_name', 'nom et prenom', 'nom et prénom'],
            'phone' => ['phone', 'telephone', 'téléphone', 'tel', 'tél'],
            'email' => ['email', 'e-mail', 'mail'],
            'service' => ['service', 'prestation', 'offre'],
            'status' => ['status', 'statut'],
            'notes' => ['notes', 'note', 'comment', 'commentaire'],
            'starts_at' => ['date', 'datetime', 'starts_at', 'rdv', 'date_heure', 'horaire'],
        ];
        $map = [];
        foreach ($cells as $i => $cell) {
            $key = mb_strtolower(str_replace(['é', 'è', 'ê'], 'e', trim($cell)));
            foreach ($aliases as $field => $names) {
                $norm = array_map(fn ($n) => str_replace(['é', 'è', 'ê'], 'e', $n), $names);
                if (in_array($key, $norm, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return isset($map['customer_name']) ? $map : null;
    }

    /** @return array<string,int> */
    private function defaultHeader(int $count): array
    {
        $order = ['starts_at', 'customer_name', 'phone', 'email', 'service', 'status', 'notes'];
        $map = [];
        for ($i = 0; $i < min($count, count($order)); $i++) {
            $map[$order[$i]] = $i;
        }

        return $map;
    }

    /** @param array<string,int> $header @param list<string> $cells */
    private function mapRow(array $header, array $cells): ?array
    {
        $get = fn (string $f) => isset($header[$f], $cells[$header[$f]]) ? $cells[$header[$f]] : null;
        $name = trim((string) $get('customer_name'));
        if ($name === '') {
            return null;
        }

        return [
            'customer_name' => $name,
            'phone' => $get('phone'),
            'email' => $get('email'),
            'service' => $get('service'),
            'status' => $get('status'),
            'notes' => $get('notes'),
            'starts_at' => $get('starts_at'),
        ];
    }

    private function status(mixed $value): ClientBookingStatus
    {
        if ($value instanceof ClientBookingStatus) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return ClientBookingStatus::Nouvelle;
        }

        return ClientBookingStatus::tryFromLabel($value) ?? ClientBookingStatus::Nouvelle;
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
