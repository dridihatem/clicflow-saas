<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ProjectStudy;
use RuntimeException;

class ProjectStudyAiService
{
    public function __construct(private PlatformLlmRouter $llm) {}

    /**
     * @return array{
     *     executive_summary: string,
     *     technical_study: string,
     *     financial_study: string,
     *     methodology: string,
     *     risks: string,
     *     conclusion: string,
     *     full_body: string,
     *     model: string
     * }
     */
    public function generate(Company $company, ProjectStudy $study): array
    {
        if (! $this->llm->isAvailable($company)) {
            throw new RuntimeException(__('AI is not configured for this workspace.'));
        }

        $study->loadMissing(['client:id,name', 'project:id,title']);

        $budget = null;
        if ($study->budget_minor !== null) {
            $budget = flowdesk_format_minor((int) $study->budget_minor, (string) $study->currency).' '.$study->currency;
        }

        $locale = app()->getLocale();
        $system = <<<SYS
You are a senior management consultant writing a formal project study (étude de projet).
Write in the user's language ({$locale}).
Produce a long, professional document with clearly separated sections.
Return ONLY valid JSON with these string keys:
executive_summary, technical_study, financial_study, methodology, risks, conclusion.
Each section must be substantial (several paragraphs). Use plain text (no markdown headings with #).
SYS;

        $user = "Project study title: {$study->title}\n"
            .'Client: '.($study->client?->name ?: 'n/a')."\n"
            .'Linked project: '.($study->project?->title ?: 'n/a')."\n"
            .'Budget: '.($budget ?: 'not specified')."\n"
            .'Existing notes / brief: '.trim((string) ($study->executive_summary ?? ''))."\n\n"
            .'Write a complete étude de projet covering context, technical approach, financial analysis (CAPEX/OPEX, ROI assumptions), methodology, risks, and conclusion.';

        $result = $this->llm->complete($system, $user, 8192, $company);
        $parsed = $this->parseJsonSections($result['suggestion']);

        $full = collect([
            'EXECUTIVE SUMMARY',
            $parsed['executive_summary'],
            '',
            'TECHNICAL STUDY',
            $parsed['technical_study'],
            '',
            'FINANCIAL STUDY',
            $parsed['financial_study'],
            '',
            'METHODOLOGY',
            $parsed['methodology'],
            '',
            'RISKS & MITIGATIONS',
            $parsed['risks'],
            '',
            'CONCLUSION',
            $parsed['conclusion'],
        ])->implode("\n");

        return [
            ...$parsed,
            'full_body' => $full,
            'model' => (string) ($result['model'] ?? ''),
        ];
    }

    /**
     * @return array{
     *     executive_summary: string,
     *     technical_study: string,
     *     financial_study: string,
     *     methodology: string,
     *     risks: string,
     *     conclusion: string
     * }
     */
    private function parseJsonSections(string $raw): array
    {
        $keys = ['executive_summary', 'technical_study', 'financial_study', 'methodology', 'risks', 'conclusion'];
        $json = $raw;
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $json = $m[0];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return [
                'executive_summary' => trim($raw),
                'technical_study' => '',
                'financial_study' => '',
                'methodology' => '',
                'risks' => '',
                'conclusion' => '',
            ];
        }

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = trim((string) ($decoded[$key] ?? ''));
        }

        return $out;
    }
}
