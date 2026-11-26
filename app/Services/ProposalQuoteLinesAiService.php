<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Proposal;
use RuntimeException;

class ProposalQuoteLinesAiService
{
    public function __construct(
        private PlatformLlmRouter $llm,
    ) {}

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    public function suggestLines(Proposal $proposal, string $brief): array
    {
        $proposal->loadMissing(['client', 'project', 'items', 'company']);
        $currency = strtoupper((string) $proposal->currency);

        $existing = $proposal->items->map(fn ($i) => sprintf(
            '- %s × %d @ %s',
            $i->description,
            $i->quantity,
            flowdesk_format_minor((int) $i->unit_amount, $currency)
        ))->implode("\n");

        return $this->generateLines(
            trim($brief),
            $currency,
            'Quote title: '.$proposal->name."\n"
            .'Client: '.($proposal->client?->name ?? '—')."\n"
            .'Project: '.($proposal->project?->title ?? '—')."\n"
            ."Currency: {$currency}\n"
            .'Valid until: '.($proposal->valid_until?->format('Y-m-d') ?? '—')."\n\n"
            ."Staff brief (what to quote):\n".trim($brief)."\n\n"
            .($existing !== '' ? "Existing lines (replace or extend as appropriate):\n{$existing}\n" : ''),
            $proposal->company,
        );
    }

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    public function suggestLinesDraft(
        string $brief,
        string $currency,
        ?string $quoteTitle = null,
        ?string $clientName = null,
        ?string $projectTitle = null,
        ?string $validUntil = null,
        ?Company $company = null,
    ): array {
        $currency = strtoupper($currency);
        $block = 'Quote: new draft'."\n"
            .'Quote title: '.($quoteTitle ?: '—')."\n"
            .'Client: '.($clientName ?? '—')."\n"
            .'Project: '.($projectTitle ?? '—')."\n"
            ."Currency: {$currency}\n";

        if ($validUntil !== null && $validUntil !== '') {
            $block .= 'Valid until: '.$validUntil."\n";
        }

        $block .= "\nStaff brief (what to quote):\n".trim($brief)."\n";

        return $this->generateLines(trim($brief), $currency, $block, $company);
    }

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    private function generateLines(string $brief, string $currency, string $userBlock, ?Company $company = null): array
    {
        if ($brief === '') {
            throw new RuntimeException(__('Describe what to quote (at least 10 characters).'));
        }

        $scale = flowdesk_currency_minor_scale($currency);
        $fd = flowdesk_currency_fraction_digits($currency);

        $system = <<<PROMPT
You are a pricing assistant for B2B quotes. Respond with ONLY valid JSON (no markdown).

{"items":[{"title":"...","description":"...","quantity":1,"unit_price_major":0}]}

Rules:
- 1 to 8 line items (ex. VAT).
- title: short commercial label of the line (max ~60 characters, e.g. the service or product name).
- description: optional details shown below the title in smaller text (scope, deliverables); empty string when nothing to add.
- quantity: positive integer.
- unit_price_major: major currency units for {$currency} (up to {$fd} decimals).
- Prefer deliverables / work packages from "Understanding / executive summary" and priced rows from "Financial study".
- When the brief lists named elements with amounts, turn each into its own line with that price.
- Use the project name in descriptions only when it clarifies scope — do not invent unrelated products.
- Realistic prices. Same language as the staff brief.
PROMPT;
        $system .= "\n\n".AiAssistantPrompts::outputLanguageInstruction();

        $result = $this->llm->completeFast($system, $userBlock, 768, $company, true);
        $decoded = $this->decodeJsonObject($result['suggestion']);
        $rows = $decoded['items'] ?? [];
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException(__('The AI did not return any line items.'));
        }

        $out = [];
        foreach (array_slice($rows, 0, 8) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = flowdesk_split_line_label($row['title'] ?? null, $row['description'] ?? null);
            if ($label['title'] === null && $label['description'] === '') {
                continue;
            }
            $qty = max(1, (int) ($row['quantity'] ?? 1));
            $major = (float) ($row['unit_price_major'] ?? $row['unit_price'] ?? 0);
            if ($major < 0) {
                $major = 0;
            }
            $minor = (int) round($major * $scale);
            $out[] = [
                'title' => $label['title'],
                'description' => mb_substr($label['description'], 0, 2000),
                'quantity' => $qty,
                'unit_amount' => $minor,
            ];
        }

        if ($out === []) {
            throw new RuntimeException(__('The AI did not return valid line items.'));
        }

        return $out;
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
            throw new RuntimeException(__('Could not parse the AI response.'));
        }

        return $decoded;
    }
}
