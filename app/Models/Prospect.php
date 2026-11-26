<?php

namespace App\Models;

use App\Enums\ProspectActivityType;
use App\Enums\ProspectSource;
use App\Enums\ProspectStatus;
use App\Enums\ProspectVerifiedVia;
use App\Models\Concerns\TenantScope;
use Database\Factories\ProspectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Prospect extends Model
{
    /** @use HasFactory<ProspectFactory> */
    use HasFactory, HasUlids, SoftDeletes, TenantScope;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
            'source' => ProspectSource::class,
            'status' => ProspectStatus::class,
            'verified_at' => 'datetime',
            'verified_via' => ProspectVerifiedVia::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function convertedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'converted_client_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProspectActivity::class)->orderByDesc('occurred_at');
    }

    public function isVerified(): bool
    {
        return $this->status === ProspectStatus::Verified
            || $this->status === ProspectStatus::Converted
            || $this->verified_at !== null;
    }

    public function isConverted(): bool
    {
        return $this->status === ProspectStatus::Converted && $this->converted_client_id !== null;
    }

    /**
     * @param  Collection<int, ProspectActivity>|null  $activities
     */
    public function hasActivityType(ProspectActivityType $type, ?Collection $activities = null): bool
    {
        if ($type === ProspectActivityType::Source) {
            return $this->source !== null;
        }

        if ($type === ProspectActivityType::Converted) {
            return $this->isConverted();
        }

        $set = $activities ?? $this->activities;

        return $set->contains(fn (ProspectActivity $activity) => $activity->type === $type);
    }

    /**
     * @return list<array{type: ProspectActivityType, done: bool, label: string}>
     */
    public function lifecycleSteps(): array
    {
        $activities = $this->relationLoaded('activities')
            ? $this->activities
            : $this->activities()->get();

        return array_map(fn (ProspectActivityType $type): array => [
            'type' => $type,
            'done' => $this->hasActivityType($type, $activities),
            'label' => $type->label(),
        ], ProspectActivityType::pipeline());
    }

    /**
     * First incomplete lifecycle step, or null when only convert remains / all done.
     *
     * @return array{type: ProspectActivityType, done: bool, label: string, panel: string}|null
     */
    public function nextLifecycleStep(): ?array
    {
        foreach ($this->lifecycleSteps() as $step) {
            if ($step['done']) {
                continue;
            }

            $step['panel'] = self::panelForActivityType($step['type']);

            return $step;
        }

        return null;
    }

    public static function panelForActivityType(ProspectActivityType $type): string
    {
        return match ($type) {
            ProspectActivityType::Source => 'call',
            ProspectActivityType::AiSuggestion => 'ai',
            ProspectActivityType::Converted => 'convert',
            default => $type->value,
        };
    }

    /**
     * @return array{linkedin?: string, twitter?: string, facebook?: string, instagram?: string, website?: string}
     */
    public function socialLinks(): array
    {
        $socials = is_array($this->socials) ? $this->socials : [];

        return array_filter([
            'linkedin' => $socials['linkedin'] ?? null,
            'twitter' => $socials['twitter'] ?? null,
            'facebook' => $socials['facebook'] ?? null,
            'instagram' => $socials['instagram'] ?? null,
            'website' => $socials['website'] ?? null,
        ], fn ($value) => filled($value));
    }
}
