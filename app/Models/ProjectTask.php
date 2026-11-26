<?php

namespace App\Models;

use App\Enums\TaskAssigneeRole;
use App\Enums\TaskPriceMode;
use App\Enums\TaskPriority;
use App\Enums\TaskScope;
use App\Enums\TaskStatus;
use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ProjectTask extends Model
{
    use HasUlids, TenantScope;

    protected $table = 'project_tasks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'scope' => TaskScope::class,
            'price_mode' => TaskPriceMode::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'billable' => 'boolean',
            'amount_cents' => 'integer',
            'tracking_started_at' => 'datetime',
            'tracking_accumulated_seconds' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_task_assignees')
            ->using(ProjectTaskAssignee::class)
            ->withPivot(['id', 'role'])
            ->withTimestamps()
            ->orderBy('name');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectTaskFile::class, 'project_task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectTaskComment::class, 'project_task_id')->orderBy('created_at');
    }

    public function assignmentRoleFor(?User $user): ?TaskAssigneeRole
    {
        if (! $user) {
            return null;
        }

        if ($this->relationLoaded('assignees')) {
            $match = $this->assignees->firstWhere('id', $user->id);
        } else {
            $match = $this->assignees()->where('users.id', $user->id)->first();
        }

        if ($match) {
            $raw = $match->pivot->role ?? null;
            if ($raw instanceof TaskAssigneeRole) {
                return $raw;
            }

            return TaskAssigneeRole::tryFrom((string) $raw) ?? TaskAssigneeRole::Follower;
        }

        if ((string) $this->assignee_user_id === (string) $user->id) {
            return TaskAssigneeRole::Editor;
        }

        return null;
    }

    public function userCanSeeFinancials(?User $user): bool
    {
        return $user?->isCompanyAdmin() ?? false;
    }

    public function userCanManageAssignees(?User $user): bool
    {
        return $user?->isCompanyAdmin() ?? false;
    }

    public function userCanComment(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isCompanyAdmin()) {
            return true;
        }

        return $this->assignmentRoleFor($user)?->canComment() ?? false;
    }

    public function userCanChangeStatus(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isCompanyAdmin()) {
            return true;
        }

        return $this->assignmentRoleFor($user)?->canChangeStatus() ?? false;
    }

    public function userCanEditTaskDetails(?User $user): bool
    {
        return $user?->isCompanyAdmin() ?? false;
    }

    /**
     * @param  array<int, string>|list<array{user_id: int, role: string}>  $assignments
     */
    public function syncAssignees(array $assignments): void
    {
        $sync = [];
        foreach ($assignments as $key => $value) {
            if (is_array($value)) {
                $userId = (int) ($value['user_id'] ?? 0);
                $role = (string) ($value['role'] ?? TaskAssigneeRole::Follower->value);
            } else {
                $userId = (int) $key;
                $role = (string) $value;
            }
            if ($userId <= 0) {
                continue;
            }
            $roleEnum = TaskAssigneeRole::tryFrom($role) ?? TaskAssigneeRole::Follower;
            $sync[$userId] = ['role' => $roleEnum->value];
        }

        $this->assignees()->sync($sync);

        $primary = null;
        foreach ($sync as $userId => $pivot) {
            if ($pivot['role'] === TaskAssigneeRole::Editor->value) {
                $primary = $userId;
                break;
            }
        }
        if ($primary === null && $sync !== []) {
            $primary = array_key_first($sync);
        }

        $this->forceFill(['assignee_user_id' => $primary])->save();
    }

    public function displayCurrency(?string $fallback = null): string
    {
        $raw = $this->currency;
        if ($raw === null || trim((string) $raw) === '') {
            return flowdesk_normalize_currency_code($fallback);
        }

        return flowdesk_normalize_currency_code($raw);
    }

    public function formattedAmount(?string $currencyFallback = null): ?string
    {
        if ($this->amount_cents === null) {
            return null;
        }

        $code = $this->displayCurrency($currencyFallback);

        return flowdesk_format_minor((int) $this->amount_cents, $code).' '.$code;
    }

    public function isOverdue(): bool
    {
        if ($this->ends_on === null || $this->status === TaskStatus::Done) {
            return false;
        }

        return Carbon::today()->isAfter($this->ends_on);
    }

    /** Total tracked work seconds (accumulated + current running segment). */
    public function elapsedTrackingSeconds(): int
    {
        $acc = (int) ($this->tracking_accumulated_seconds ?? 0);
        if ($this->tracking_started_at === null) {
            return $acc;
        }

        return $acc + max(0, (int) $this->tracking_started_at->diffInSeconds(now()));
    }
}
