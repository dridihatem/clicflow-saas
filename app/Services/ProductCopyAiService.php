<?php

namespace App\Services;

use App\Models\Company;
use App\Support\CatalogUnits;
use RuntimeException;

class ProductCopyAiService
{
    public function __construct(
        private PlatformLlmRouter $llm,
    ) {}

    /**
     * @return array{name: string, description: string, unit: string|null}
     */
    public function generate(
        string $brief,
        string $kind,
        ?string $existingName = null,
        ?string $existingDescription = null,
        ?Company $company = null,
    ): array {
        $brief = trim($brief);
        if (mb_strlen($brief) < 8) {
            throw new RuntimeException(__('catalog_ai_prompt_required'));
        }

        $kind = $kind === 'service' ? 'service' : 'product';
        $units = CatalogUnits::valuesForKind($kind);
        $unitList = implode(', ', $units);

        $system = <<<PROMPT
You write catalog copy for products and services sold on quotes and invoices.
Respond with ONLY valid JSON (no markdown):

{"name":"...","description":"...","unit":"..."}

Rules:
- name: short commercial title (max 80 characters), no quotes around the whole title.
- description: 2–4 sentences, sellable and specific. Plain text only (no HTML, no markdown).
- unit: exactly one of: {$unitList}
- Same language as the staff brief.
- Do not invent SKUs, prices, or legal claims.
PROMPT;
        $system .= "\n\n".AiAssistantPrompts::outputLanguageInstruction();

        $user = 'Catalog kind: '.($kind === 'service' ? 'billable service' : 'physical or digital product')."\n"
            .'Allowed units: '.$unitList."\n\n"
            ."Staff brief:\n{$brief}\n";

        $existingName = trim((string) $existingName);
        $existingDescription = trim((string) $existingDescription);
        if ($existingName !== '') {
            $user .= "\nCurrent title (improve or replace): {$existingName}\n";
        }
        if ($existingDescription !== '') {
            $user .= "Current description (improve or replace):\n{$existingDescription}\n";
        }

        $result = $this->llm->completeFast($system, $user, 768, $company, true);
        $decoded = $this->decodeJsonObject($result['suggestion'] ?? '');

        $name = trim((string) ($decoded['name'] ?? $decoded['title'] ?? ''));
        $description = trim((string) ($decoded['description'] ?? ''));
        $unit = trim((string) ($decoded['unit'] ?? ''));

        if ($name === '' && $description === '') {
            throw new RuntimeException(__('catalog_ai_empty'));
        }

        if ($name === '') {
            $name = mb_substr($brief, 0, 80);
        }

        if (! in_array($unit, $units, true)) {
            $unit = CatalogUnits::defaultForKind($kind);
        }

        return [
            'name' => mb_substr($name, 0, 255),
            'description' => mb_substr($description, 0, 8000),
            'unit' => $unit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $raw): array
    {
        $trim = trim($raw);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $trim, $m)) {
            $trim = trim($m[1]);
        }
        $decoded = json_decode($trim, true);
        if (! is_array($decoded)) {
            throw new RuntimeException(__('catalog_ai_failed'));
        }

        return $decoded;
    }
}
