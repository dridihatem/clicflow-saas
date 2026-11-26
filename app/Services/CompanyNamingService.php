<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Str;

class CompanyNamingService
{
    public function uniqueSubdomain(string $companyName): string
    {
        return $this->uniqueSegment($companyName, 'subdomain');
    }

    public function uniqueSlug(string $companyName): string
    {
        return $this->uniqueSegment($companyName, 'slug');
    }

    /**
     * A segment that is free as BOTH subdomain and slug, including soft-deleted
     * companies (the DB unique indexes still cover trashed rows).
     */
    private function uniqueSegment(string $companyName, string $column): string
    {
        $base = Str::slug($companyName);
        $base = trim(Str::limit($base, 48, ''), '-');

        if ($base === '') {
            $base = 'company';
        }

        $candidate = $base;
        $i = 0;

        while ($this->segmentTaken($candidate)) {
            $i++;
            $suffix = $i <= 50 ? '-'.$i : '-'.Str::lower(Str::random(6));
            $candidate = rtrim(Str::limit($base, 48 - strlen($suffix), ''), '-').$suffix;
        }

        return $candidate;
    }

    private function segmentTaken(string $candidate): bool
    {
        return Company::withTrashed()
            ->where(fn ($q) => $q->where('subdomain', $candidate)->orWhere('slug', $candidate))
            ->exists();
    }

    /**
     * Unique URL segment for /partner/{slug} (public provider signup).
     */
    public function uniqueProviderRecruitmentSlug(string $seedName): string
    {
        $base = Str::slug($seedName);
        $base = trim(Str::limit($base, 48, ''), '-');

        if ($base === '') {
            $base = 'providers';
        }

        $candidate = $base;
        $i = 0;

        while (Company::withTrashed()->where('provider_recruitment_slug', $candidate)->exists()) {
            $i++;
            $suffix = '-'.$i;
            $candidate = Str::limit($base, 48 - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }
}
