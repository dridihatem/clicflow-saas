<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayAction(): string
    {
        $entity = $this->auditable_type ? class_basename($this->auditable_type) : __('System');
        $label = (string) data_get($this->properties, 'label', '');

        return match ($this->action) {
            'created' => trim(__('Created :entity', ['entity' => $entity]).($label !== '' ? " — {$label}" : '')),
            'updated' => trim(__('Updated :entity', ['entity' => $entity]).($label !== '' ? " — {$label}" : '')),
            'deleted' => trim(__('Deleted :entity', ['entity' => $entity]).($label !== '' ? " — {$label}" : '')),
            'force_deleted' => trim(__('Permanently deleted :entity', ['entity' => $entity]).($label !== '' ? " — {$label}" : '')),
            'restored' => trim(__('Restored :entity', ['entity' => $entity]).($label !== '' ? " — {$label}" : '')),
            'platform_notice' => __('Platform notice'),
            default => (string) $this->action,
        };
    }

    public function displaySummary(): ?string
    {
        if ($this->action === 'platform_notice') {
            $message = data_get($this->properties, 'message');

            return filled($message) ? (string) $message : null;
        }

        $changed = data_get($this->properties, 'changed');
        if (is_array($changed) && $changed !== []) {
            return __('Changed: :fields', ['fields' => implode(', ', $changed)]);
        }

        return null;
    }
}
