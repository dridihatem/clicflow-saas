<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkspaceInsightsSummaryService
{
    public function __construct(
        private PlanLimitService $planLimits,
        private AiCreditUsageService $usage,
        private PlatformLlmRouter $llm,
    ) {}

    /**
     * @param  array{
     *   clients: array{total: int, with_projects: int, without_projects: int},
     *   financial: array{currency: string, invoices: int, open_invoices: int, open_amount: string, paid_amount: string, quotes: int},
     *   projects: array{total: int, in_progress: int, completed: int, pending: int}
     * }  $stats
     * @return array{
     *   available: bool,
     *   generated: bool,
     *   charged: int,
     *   clients: ?string,
     *   financial: ?string,
     *   projects: ?string,
     *   reason: ?string
     * }
     */
    public function summarize(Company $company, array $stats, bool $force = false): array
    {
        $empty = [
            'available' => false,
            'generated' => false,
            'charged' => 0,
            'clients' => null,
            'financial' => null,
            'projects' => null,
            'reason' => null,
        ];

        if (! $this->planLimits->isFeatureEnabled($company, 'ai_credits')) {
            return [...$empty, 'reason' => 'plan_disabled'];
        }

        $creditCost = $this->usage->creditsForTask(AiCreditUsageService::TASK_ASSISTANT, 'insights_summary');
        if (! $this->planLimits->allows($company, 'ai_credits', $creditCost)) {
            return [...$empty, 'reason' => 'ai_credits_limit'];
        }

        if (! $this->llm->isAvailable($company)) {
            return [...$empty, 'reason' => 'llm_unavailable'];
        }

        $fingerprint = substr(hash('sha256', json_encode($stats) ?: ''), 0, 16);
        $cacheKey = 'company_insights_ai:'.$company->id.':'.now()->toDateString().':'.$fingerprint;

        if (! $force) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['clients'], $cached['financial'], $cached['projects'])) {
                return [
                    'available' => true,
                    'generated' => true,
                    'charged' => 0,
                    'clients' => (string) $cached['clients'],
                    'financial' => (string) $cached['financial'],
                    'projects' => (string) $cached['projects'],
                    'reason' => null,
                ];
            }
        }

        try {
            $system = 'You are Nova, a concise business analyst for a company workspace. '
                .'Given workspace stats, write three short executive summaries (1–2 sentences each). '
                .'Be practical and specific to the numbers. Do not invent metrics not provided. '
                .'Return ONLY valid JSON with keys: clients, financial, projects. '
                .AiAssistantPrompts::outputLanguageInstruction();

            $user = "Workspace stats JSON:\n".json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $result = $this->llm->completeFast($system, $user, 500, $company, true);
            $raw = trim((string) ($result['suggestion'] ?? ''));
            $parsed = $this->parseJson($raw);

            $clients = trim((string) ($parsed['clients'] ?? ''));
            $financial = trim((string) ($parsed['financial'] ?? ''));
            $projects = trim((string) ($parsed['projects'] ?? ''));

            if ($clients === '' || $financial === '' || $projects === '') {
                return [...$empty, 'available' => true, 'reason' => 'empty_response'];
            }

            $charged = $this->usage->recordForTask($company, AiCreditUsageService::TASK_ASSISTANT, 'insights_summary');

            $payload = [
                'clients' => $clients,
                'financial' => $financial,
                'projects' => $projects,
            ];
            Cache::put($cacheKey, $payload, now()->endOfDay());

            return [
                'available' => true,
                'generated' => true,
                'charged' => $charged,
                'clients' => $clients,
                'financial' => $financial,
                'projects' => $projects,
                'reason' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Insights AI summary failed', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return [...$empty, 'available' => true, 'reason' => 'generation_failed'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}/', $raw, $m) === 1) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
