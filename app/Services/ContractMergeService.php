<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContract;
use App\Models\Company;
use Illuminate\Support\Carbon;

class ContractMergeService
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public function editorHints(): array
    {
        return [
            ['key' => 'client_name', 'label' => __('contract_tag_client_name')],
            ['key' => 'client_email', 'label' => __('contract_tag_client_email')],
            ['key' => 'client_phone', 'label' => __('contract_tag_client_phone')],
            ['key' => 'company_name', 'label' => __('contract_tag_company_name')],
            ['key' => 'company_address', 'label' => __('contract_tag_company_address')],
            ['key' => 'contract_title', 'label' => __('contract_tag_contract_title')],
            ['key' => 'contract_reference', 'label' => __('contract_tag_contract_reference')],
            ['key' => 'starts_on', 'label' => __('contract_tag_starts_on')],
            ['key' => 'ends_on', 'label' => __('contract_tag_ends_on')],
            ['key' => 'today', 'label' => __('contract_tag_today')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function placeholdersFor(ClientContract $contract): array
    {
        $contract->loadMissing(['client', 'company']);
        $client = $contract->client ?? new Client(['name' => '']);
        $company = $contract->company ?? new Company(['name' => '']);

        $addressParts = array_filter([
            $company->address_line1 ?? null,
            trim(implode(' ', array_filter([$company->postal_code ?? null, $company->city ?? null]))),
            $company->country ?? null,
        ]);

        return [
            'client_name' => (string) ($client->name ?? ''),
            'client_email' => (string) ($client->email ?? ''),
            'client_phone' => (string) ($client->phone ?? ''),
            'company_name' => (string) ($company->name ?? ''),
            'company_address' => implode(', ', $addressParts),
            'contract_title' => (string) ($contract->title ?? ''),
            'contract_reference' => (string) ($contract->reference ?? ''),
            'starts_on' => $this->formatDate($contract->starts_on),
            'ends_on' => $this->formatDate($contract->ends_on),
            'today' => now()->format('d/m/Y'),
        ];
    }

    public function interpolate(string $body, ClientContract $contract): string
    {
        $out = $body;
        foreach ($this->placeholdersFor($contract) as $key => $value) {
            $out = str_replace('{{'.$key.'}}', e($value), $out);
        }

        return $out;
    }

    public function bake(ClientContract $contract): string
    {
        return $this->interpolate((string) $contract->body_html, $contract);
    }

    private function formatDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if ($date instanceof Carbon) {
            return $date->format('d/m/Y');
        }

        try {
            return Carbon::parse((string) $date)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }
}
