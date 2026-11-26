<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $action,
        ?Model $auditable = null,
        array $properties = [],
        ?string $companyId = null,
        ?int $userId = null,
    ): ?AuditLog {
        $resolvedCompanyId = $companyId
            ?? ($auditable !== null && isset($auditable->company_id) ? (string) $auditable->company_id : null)
            ?? $this->currentCompanyId();

        if (! filled($resolvedCompanyId)) {
            return null;
        }

        $payload = array_merge($this->summaryFor($auditable), $properties);

        return AuditLog::query()->withoutGlobalScopes()->create([
            'company_id' => $resolvedCompanyId,
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable ? (string) $auditable->getKey() : null,
            'properties' => $payload === [] ? null : $payload,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryFor(?Model $auditable): array
    {
        if ($auditable === null) {
            return [];
        }

        $summary = [
            'label' => $this->labelFor($auditable),
        ];

        foreach (['name', 'title', 'subject', 'number', 'reference', 'email', 'status'] as $field) {
            if (! array_key_exists($field, $auditable->getAttributes())) {
                continue;
            }
            $value = $auditable->getAttribute($field);
            if ($value === null || $value === '') {
                continue;
            }
            $summary[$field] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return array_filter($summary, static fn ($value) => $value !== null && $value !== '');
    }

    public function labelFor(?Model $auditable): ?string
    {
        if ($auditable === null) {
            return null;
        }

        foreach (['name', 'title', 'subject', 'number', 'reference', 'email'] as $field) {
            $value = $auditable->getAttribute($field);
            if (filled($value) && is_scalar($value)) {
                return (string) $value;
            }
        }

        return class_basename($auditable).' #'.$auditable->getKey();
    }

    private function currentCompanyId(): ?string
    {
        $company = app()->bound('currentCompany') ? app()->make('currentCompany') : null;
        if ($company instanceof Company) {
            return (string) $company->id;
        }

        $user = Auth::user();

        return $user && filled($user->company_id) ? (string) $user->company_id : null;
    }
}
