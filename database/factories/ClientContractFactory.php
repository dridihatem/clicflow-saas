<?php

namespace Database\Factories;

use App\Enums\ClientContractStatus;
use App\Models\Client;
use App\Models\ClientContract;
use App\Models\Company;
use App\Models\ContractTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContract>
 */
class ClientContractFactory extends Factory
{
    protected $model = ClientContract::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'client_id' => Client::factory(),
            'contract_template_id' => null,
            'title' => 'Service Agreement',
            'type_key' => 'service',
            'body_html' => '<p>This agreement is between {{company_name}} and {{client_name}}.</p>',
            'status' => ClientContractStatus::Draft,
            'reference' => null,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'notes' => null,
            'meta' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ClientContract $contract): void {
            if ($contract->client_id && ! $contract->company_id) {
                $client = Client::query()->withoutGlobalScopes()->find($contract->client_id);
                if ($client) {
                    $contract->company_id = $client->company_id;
                }
            }
        })->afterCreating(function (ClientContract $contract): void {
            if ($contract->client && $contract->client->company_id !== $contract->company_id) {
                $contract->update(['company_id' => $contract->client->company_id]);
            }
        });
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => [
            'company_id' => $company->id,
            'client_id' => Client::factory()->state(['company_id' => $company->id]),
        ]);
    }

    public function withTemplate(?ContractTemplate $template = null): static
    {
        return $this->state(function () use ($template): array {
            $template ??= ContractTemplate::factory()->create();

            return [
                'company_id' => $template->company_id,
                'contract_template_id' => $template->id,
                'title' => $template->name,
                'type_key' => $template->type_key,
                'body_html' => $template->body_html,
            ];
        });
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => ClientContractStatus::Sent,
            'sent_at' => now(),
            'company_signed_at' => now(),
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (): array => [
            'status' => ClientContractStatus::Signed,
            'sent_at' => now()->subDay(),
            'company_signed_at' => now()->subDay(),
            'client_signed_at' => now(),
        ]);
    }
}
