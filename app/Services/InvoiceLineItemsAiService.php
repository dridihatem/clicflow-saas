<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use RuntimeException;

class InvoiceLineItemsAiService
{
    public function __construct(
        private PlatformLlmRouter $llm,
    ) {}

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    public function suggestLines(Invoice $invoice, string $brief): array
    {
        $invoice->loadMissing(['client', 'project', 'items', 'company']);

        return $this->generateLines(
            trim($brief),
            strtoupper((string) $invoice->currency),
            $this->contextBlock(
                'Invoice reference: '.($invoice->number ?? '—'),
                strtoupper((string) $invoice->currency),
                $invoice->client?->name,
                $invoice->project?->title,
                $invoice->due_date?->format('Y-m-d'),
                $invoice->items->map(fn ($i) => sprintf(
                    '- %s × %d @ %s',
                    $i->description,
                    $i->quantity,
                    flowdesk_format_minor((int) $i->unit_amount, $invoice->currency)
                ))->implode("\n"),
            ),
            $invoice->company,
        );
    }

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    public function suggestLinesDraft(string $brief, string $currency, ?Client $client = null, ?string $projectTitle = null, ?Company $company = null): array
    {
        return $this->generateLines(
            trim($brief),
            strtoupper($currency),
            $this->contextBlock(
                'Invoice: new draft',
                strtoupper($currency),
                $client?->name,
                $projectTitle,
                null,
                '',
            ),
            $company,
        );
    }

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    private function generateLines(string $brief, string $currency, string $contextBlock, ?Company $company = null): array
    {
        if ($brief === '') {
            throw new RuntimeException(__('Describe what to invoice (at least 10 characters).'));
        }

        $scale = flowdesk_currency_minor_scale($currency);
        $fd = flowdesk_currency_fraction_digits($currency);

        $userBlock = $contextBlock."\n\n"
            ."Staff brief (what to invoice):\n{$brief}\n";

        $system = <<<PROMPT
You are a pricing assistant for B2B invoices. Respond with ONLY valid JSON (no markdown).

{"items":[{"title":"...","description":"...","quantity":1,"unit_price_major":0}]}

Rules:
- 1 to 8 line items (ex. VAT).
- title: short commercial label of the line (max ~60 characters, e.g. the service or product name).
- description: optional details shown below the title in smaller text (scope, deliverables); empty string when nothing to add.
- quantity: positive integer.
- unit_price_major: major currency units for {$currency} (up to {$fd} decimals).
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

    private function contextBlock(
        string $titleLine,
        string $currency,
        ?string $clientName,
        ?string $projectTitle,
        ?string $dueDate,
        string $existingLines,
    ): string {
        $block = $titleLine."\n"
            .'Client: '.($clientName ?? '—')."\n"
            .'Project: '.($projectTitle ?? '—')."\n"
            ."Currency: {$currency}\n";

        if ($dueDate !== null && $dueDate !== '') {
            $block .= 'Due date: '.$dueDate."\n";
        }

        if ($existingLines !== '') {
            $block .= "\nExisting lines (replace or extend as appropriate):\n{$existingLines}\n";
        }

        return $block;
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
