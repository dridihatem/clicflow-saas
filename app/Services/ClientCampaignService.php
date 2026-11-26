<?php

namespace App\Services;

use App\Enums\ClientCampaignChannel;
use App\Enums\ClientCampaignStatus;
use App\Models\Client;
use App\Models\ClientCampaign;
use App\Models\User;

class ClientCampaignService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Client $client, array $data, ?User $actor = null): ClientCampaign
    {
        return ClientCampaign::query()->create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'name' => trim((string) $data['name']),
            'channel' => $this->channel($data['channel'] ?? null),
            'status' => $this->status($data['status'] ?? null),
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'budget' => $this->nullableNumber($data['budget'] ?? null),
            'currency' => $this->nullable($data['currency'] ?? null),
            'description' => $this->nullable($data['description'] ?? null),
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClientCampaign $campaign, array $data): ClientCampaign
    {
        $payload = [];
        foreach (['name', 'starts_on', 'ends_on', 'currency', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $field === 'name' ? trim((string) $data[$field]) : ($data[$field] ?: null);
            }
        }
        if (array_key_exists('budget', $data)) {
            $payload['budget'] = $this->nullableNumber($data['budget']);
        }
        if (array_key_exists('channel', $data)) {
            $payload['channel'] = $this->channel($data['channel']);
        }
        if (array_key_exists('status', $data)) {
            $payload['status'] = $this->status($data['status']);
        }
        $campaign->update($payload);

        return $campaign->fresh();
    }

    public function delete(ClientCampaign $campaign): void
    {
        $campaign->delete();
    }

    /**
     * Find or create a campaign for free-text names so prospect.campaign stays compatible.
     */
    public function resolveForName(Client $client, ?string $name, ?User $actor = null): ?ClientCampaign
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $existing = ClientCampaign::query()
            ->where('client_id', $client->id)
            ->where('name', $name)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->create($client, [
            'name' => $name,
            'channel' => ClientCampaignChannel::Other,
            'status' => ClientCampaignStatus::Running,
        ], $actor);
    }

    private function channel(mixed $value): ClientCampaignChannel
    {
        if ($value instanceof ClientCampaignChannel) {
            return $value;
        }

        return ClientCampaignChannel::tryFrom((string) $value) ?? ClientCampaignChannel::Other;
    }

    private function status(mixed $value): ClientCampaignStatus
    {
        if ($value instanceof ClientCampaignStatus) {
            return $value;
        }

        return ClientCampaignStatus::tryFrom((string) $value) ?? ClientCampaignStatus::Draft;
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableNumber(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }
}
