<?php

namespace App\Services\Integrations;

use App\Models\Company;
use App\Models\User;
use App\Services\DataImportService;
use App\Services\ProspectService;

class ThirdPartyRecordImporter
{
    public function __construct(
        private DataImportService $import,
        private ProspectService $prospects,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importClients(Company $company, array $rows): array
    {
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        return $this->import->importClients($company, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importProducts(Company $company, array $rows): array
    {
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        return $this->import->importProducts($company, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importProspects(Company $company, User $user, array $rows, string $sourceLabel): array
    {
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        return $this->prospects->importRows($company, $user, $rows, $sourceLabel);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importInvoices(Company $company, array $rows): array
    {
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        return $this->import->importInvoices($company, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function importProposals(Company $company, array $rows): array
    {
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        return $this->import->importProposals($company, $rows);
    }

    /**
     * @param  array<string, array{created?: int, updated?: int, errors?: list<string>}|int>  $parts
     */
    public function summarize(array $parts): string
    {
        $bits = [];
        foreach ($parts as $label => $result) {
            $created = is_array($result) ? (int) ($result['created'] ?? 0) : (int) $result;
            $updated = is_array($result) ? (int) ($result['updated'] ?? 0) : 0;
            $errors = is_array($result) ? count($result['errors'] ?? []) : 0;
            $bits[] = $label.': +'.$created.($updated ? '/'.$updated : '').($errors ? ' ('.$errors.' err)' : '');
        }

        return implode(', ', $bits);
    }
}
