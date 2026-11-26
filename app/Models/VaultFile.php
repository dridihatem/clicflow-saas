<?php

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VaultFile extends Model
{
    use HasUlids, SoftDeletes, TenantScope;

    public const UPLOADER_STAFF = 'staff';

    public const UPLOADER_CLIENT = 'client';

    public const UPLOADER_PROVIDER = 'provider';

    protected $fillable = [
        'company_id', 'project_id', 'client_id', 'uploaded_by', 'uploader_type', 'folder',
        'original_name', 'extension', 'disk', 'path', 'mime', 'size', 'description',
        'share_client', 'share_project', 'share_team', 'share_providers', 'client_notified_at', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'share_client' => 'boolean',
            'share_project' => 'boolean',
            'share_team' => 'boolean',
            'share_providers' => 'boolean',
            'client_notified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withoutGlobalScopes();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withoutGlobalScopes();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function shareLinks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VaultFileShare::class, 'vault_file_id')->withoutGlobalScopes();
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vault_file_user')->withTimestamps();
    }

    public function isImage(): bool
    {
        return in_array(strtolower((string) $this->extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function isPdf(): bool
    {
        return strtolower((string) $this->extension) === 'pdf';
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }

    /** image | pdf | document | sheet | presentation | archive | other */
    public function typeKey(): string
    {
        $ext = strtolower((string) $this->extension);

        return match (true) {
            $this->isImage() => 'image',
            $ext === 'pdf' => 'pdf',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf', 'txt'], true) => 'document',
            in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true) => 'sheet',
            in_array($ext, ['ppt', 'pptx', 'odp'], true) => 'presentation',
            in_array($ext, ['zip'], true) => 'archive',
            default => 'other',
        };
    }

    public function humanSize(): string
    {
        $b = (int) $this->size;
        if ($b >= 1048576) {
            return number_format($b / 1048576, 1, ',', ' ').' Mo';
        }
        if ($b >= 1024) {
            return number_format($b / 1024, 0, ',', ' ').' Ko';
        }

        return $b.' o';
    }
}
