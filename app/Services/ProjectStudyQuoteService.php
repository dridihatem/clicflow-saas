<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProjectStudy;

class ProjectStudyQuoteService
{
    public function __construct(
        private ProposalQuoteLinesAiService $linesAi,
        private PlatformLlmRouter $llm,
        private PlanLimitService $planLimits,
        private AiCreditUsageService $usage,
    ) {}

    /**
     * Quote title: linked project name, otherwise study title.
     */
    public function quoteName(ProjectStudy $study): string
    {
        $study->loadMissing('project:id,title');
        $fromProject = trim((string) ($study->project?->title ?? ''));

        return mb_substr($fromProject !== '' ? $fromProject : (string) $study->title, 0, 255);
    }

    /**
     * Project total in minor units: study budget, linked project price, then amounts in the financial study.
     */
    public function projectTotalMinor(ProjectStudy $study): int
    {
        $study->loadMissing('project');

        if ($study->budget_minor !== null && (int) $study->budget_minor > 0) {
            return (int) $study->budget_minor;
        }

        $agreed = $study->project?->clientAgreedPriceMinor();
        if ($agreed !== null && $agreed > 0) {
            return $agreed;
        }

        $currency = flowdesk_normalize_currency_code($study->currency ?? flowdesk_default_currency());

        return $this->totalFromFinancialText((string) ($study->financial_study ?? ''), $currency);
    }

    /**
     * Structured brief for AI / notes: understanding + financial elements + prices.
     */
    public function studyBriefForQuote(ProjectStudy $study): string
    {
        $study->loadMissing('project');
        $currency = strtoupper((string) ($study->currency ?? flowdesk_default_currency()));
        $parts = [];

        $projectTitle = trim((string) ($study->project?->title ?? ''));
        if ($projectTitle !== '') {
            $parts[] = 'Project name: '.$projectTitle;
        }
        $parts[] = 'Study title: '.$study->title;

        $total = $this->projectTotalMinor($study);
        if ($total > 0) {
            $parts[] = 'Project total: '.flowdesk_format_minor($total, $currency).' '.$currency;
        }

        $projectDescription = trim((string) ($study->project?->description ?? ''));
        if ($projectDescription !== '') {
            $parts[] = "Project description:\n".$projectDescription;
        }

        $sections = [
            'Understanding / executive summary' => $study->executive_summary,
            'Technical study' => $study->technical_study,
            'Financial study (elements & prices)' => $study->financial_study,
            'Methodology' => $study->methodology,
            'Risks' => $study->risks,
            'Conclusion' => $study->conclusion,
        ];

        foreach ($sections as $label => $body) {
            if (! is_string($body) || trim($body) === '') {
                continue;
            }
            $parts[] = $label.":\n".trim($body);
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int, product_id?: string|null, unit?: string|null}>
     */
    public function buildLineItems(ProjectStudy $study, Company $company, bool $useAi = true): array
    {
        $currency = flowdesk_normalize_currency_code($study->currency ?? $company->default_currency ?? flowdesk_default_currency());
        $total = $this->projectTotalMinor($study);
        $items = [];

        if ($useAi) {
            $brief = $this->studyBriefForQuote($study);
            if (mb_strlen($brief) >= 10) {
                $aiAvailable = $this->llm->isAvailable($company) && $this->planLimits->isFeatureEnabled($company, 'ai_credits');
                if ($aiAvailable) {
                    $creditCost = $this->usage->creditsForTask('quote_line_items');
                    $this->planLimits->assertAllows($company, 'ai_credits', $creditCost);
                    $items = $this->linesAi->suggestLinesDraft(
                        $brief,
                        $currency,
                        $this->quoteName($study),
                        $study->client?->name,
                        $study->project?->title,
                        null,
                        $company,
                    );
                    $this->usage->recordForTask($company, 'quote_line_items');
                    $items = $this->ensureProjectTotal($items, $total);
                }
            }
        }

        if ($items === []) {
            $items = $this->linesFromStudy($study, $currency, $total);
        }

        return $this->attachCatalogMatches($items, $company);
    }

    /**
     * Line items from the study: commercial financial rows when present,
     * otherwise project / study description elements with the project total.
     *
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    public function linesFromStudy(ProjectStudy $study, string $currency, ?int $total = null): array
    {
        $total ??= $this->projectTotalMinor($study);
        $priced = $this->pricedLinesFromFinancialStudy($study, $currency);
        $elements = $this->elementsFromProjectDescription($study);

        if ($this->pricedLinesLookCommercial($priced)) {
            return $this->ensureProjectTotal($priced, $total);
        }

        if ($elements !== []) {
            $items = array_map(static fn (string $description): array => [
                'description' => $description,
                'quantity' => 1,
                'unit_amount' => 0,
            ], $elements);

            return $this->ensureProjectTotal($items, $total);
        }

        return [[
            'description' => mb_substr($this->quoteName($study), 0, 2000),
            'quantity' => 1,
            'unit_amount' => max(0, $total),
        ]];
    }

    /**
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    private function pricedLinesFromFinancialStudy(ProjectStudy $study, string $currency): array
    {
        $text = trim((string) ($study->financial_study ?? ''));
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\R+/', $text) ?: [];
        $out = [];

        foreach ($lines as $raw) {
            $line = trim($raw);
            $line = preg_replace('/^[\-\*\x{2022}\d]+[\.\)\-\:\s]+/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '' || mb_strlen($line) < 3) {
                continue;
            }

            $qty = 1;
            if (preg_match('/^(?:x|×|\*)?\s*(\d+)\s*[x×\*]\s*(.+)$/iu', $line, $m)) {
                $qty = max(1, (int) $m[1]);
                $line = trim($m[2]);
            }

            [$description, $minor] = $this->splitDescriptionAndAmount($line, $currency);
            if ($description === '' || mb_strlen($description) > 220) {
                continue;
            }

            $out[] = [
                'description' => mb_substr($description, 0, 2000),
                'quantity' => $qty,
                'unit_amount' => max(0, $minor),
            ];

            if (count($out) >= 12) {
                break;
            }
        }

        return $out;
    }

    /**
     * Scope lines from the linked project description, then understanding / technical sections.
     *
     * @return list<string>
     */
    private function elementsFromProjectDescription(ProjectStudy $study): array
    {
        $study->loadMissing('project');

        $seen = [];
        $out = [];
        foreach ([
            (string) ($study->project?->description ?? ''),
            (string) ($study->executive_summary ?? ''),
            (string) ($study->technical_study ?? ''),
        ] as $text) {
            foreach ($this->splitScopeLines($text) as $line) {
                $key = mb_strtolower($line);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $line;
                if (count($out) >= 8) {
                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function splitScopeLines(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/\R+/', $text) ?: [] as $raw) {
            $line = trim((string) $raw);
            $line = preg_replace('/^[\-\*\x{2022}\d]+[\.\)\-\:\s]+/u', '', $line) ?? $line;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (mb_strlen($line) >= 8 && mb_strlen($line) <= 200) {
                $out[] = mb_substr($line, 0, 2000);

                continue;
            }

            if (mb_strlen($line) > 200 && preg_match('/^[^.!?\n]{12,220}[.!?]/u', $line, $m)) {
                $out[] = mb_substr(trim($m[0]), 0, 2000);
            }
        }

        return $out;
    }

    /**
     * @param  list<array{description: string, quantity: int, unit_amount: int}>  $items
     * @return bool
     */
    private function pricedLinesLookCommercial(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        $priced = 0;
        foreach ($items as $item) {
            if (mb_strlen((string) $item['description']) > 180) {
                return false;
            }
            if ((int) $item['unit_amount'] > 0) {
                $priced++;
            }
        }

        return $priced > 0;
    }

    /**
     * @param  list<array{description: string, quantity: int, unit_amount: int}>  $items
     * @return list<array{description: string, quantity: int, unit_amount: int}>
     */
    private function ensureProjectTotal(array $items, int $total): array
    {
        if ($items === []) {
            return $items;
        }

        $sum = 0;
        foreach ($items as $item) {
            $sum += ((int) $item['quantity']) * ((int) $item['unit_amount']);
        }

        if ($sum > 0 || $total <= 0) {
            return $items;
        }

        $items[0]['unit_amount'] = $total;

        return $items;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function splitDescriptionAndAmount(string $line, string $currency): array
    {
        if (preg_match('/^(.*?)(?:[:\-–—]\s*|\s+)((?:\d{1,3}(?:[\s\x{00A0}\x{202F}.]\d{3})+|\d+)(?:[,.]\d+)?)\s*([A-Za-z]{3})?\s*$/u', $line, $m)) {
            $minor = $this->parseLocalizedAmount($m[2], $currency);
            $desc = trim($m[1]);
            if ($desc !== '' && $minor !== null) {
                return [$desc, $minor];
            }
        }

        if (preg_match('/((?:\d{1,3}(?:[\s\x{00A0}\x{202F}.]\d{3})+|\d+)(?:[,.]\d{2,3}))\s*([A-Za-z]{3})?/u', $line, $m)) {
            $minor = $this->parseLocalizedAmount($m[1], $currency) ?? 0;
            $desc = trim(str_replace($m[0], ' ', $line));
            $desc = trim($desc, " \t\n\r\0\x0B,;:.–—-");

            return [$desc !== '' ? $desc : $line, $minor];
        }

        return [$line, 0];
    }

    private function totalFromFinancialText(string $text, string $currency): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        if (preg_match_all('/(?:(?:montant|total|forfait|budget|co[uû]t|prix)\D{0,40})?((?:\d{1,3}(?:[\s\x{00A0}\x{202F}.]\d{3})+|\d+)(?:[,.]\d{2,3})|\d{4,})(?:\s*([A-Za-z]{3}))?/iu', $text, $matches, PREG_SET_ORDER)) {
            $keyword = 0;
            $largest = 0;
            foreach ($matches as $match) {
                $minor = $this->parseLocalizedAmount($match[1], $currency);
                if ($minor === null || $minor <= 0) {
                    continue;
                }
                $largest = max($largest, $minor);
                $prefix = mb_strtolower(mb_substr($text, max(0, mb_strpos($text, $match[0]) - 40), 40));
                if (preg_match('/montant|total|forfait|budget|co[uû]t|prix/u', $prefix)) {
                    $keyword = max($keyword, $minor);
                }
            }

            return $keyword > 0 ? $keyword : $largest;
        }

        return 0;
    }

    private function parseLocalizedAmount(string $raw, string $currency): ?int
    {
        $raw = trim(str_replace(["\u{00A0}", "\u{202F}"], ' ', $raw));
        $raw = preg_replace('/\s+/', '', $raw) ?? $raw;
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $scale = flowdesk_currency_minor_scale($currency);

        return (int) round(((float) $raw) * $scale);
    }

    /**
     * @param  list<array{description: string, quantity: int, unit_amount: int, product_id?: string|null, unit?: string|null}>  $items
     * @return list<array{description: string, quantity: int, unit_amount: int, product_id: string|null, unit: string|null}>
     */
    private function attachCatalogMatches(array $items, Company $company): array
    {
        $products = Product::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'base_price_minor']);

        if ($products->isEmpty()) {
            return array_map(static fn (array $item): array => [
                'title' => $item['title'] ?? null,
                'description' => $item['description'],
                'quantity' => (int) $item['quantity'],
                'unit_amount' => (int) $item['unit_amount'],
                'product_id' => $item['product_id'] ?? null,
                'unit' => $item['unit'] ?? null,
            ], $items);
        }

        return array_map(function (array $item) use ($products): array {
            $desc = mb_strtolower(trim((string) (($item['title'] ?? null) ?: $item['description'])));
            $match = $products->first(function (Product $p) use ($desc): bool {
                $name = mb_strtolower(trim((string) $p->name));

                return $name !== '' && ($desc === $name || str_contains($desc, $name) || str_contains($name, $desc));
            });

            $productId = $item['product_id'] ?? null;
            $unit = $item['unit'] ?? null;
            $unitAmount = (int) $item['unit_amount'];

            if ($match instanceof Product) {
                $productId = (string) $match->id;
                $unit = $unit ?: $match->unit;
                if ($unitAmount <= 0 && (int) $match->base_price_minor > 0) {
                    $unitAmount = (int) $match->base_price_minor;
                }
            }

            return [
                'title' => $item['title'] ?? null,
                'description' => $item['description'],
                'quantity' => (int) $item['quantity'],
                'unit_amount' => $unitAmount,
                'product_id' => $productId,
                'unit' => $unit,
            ];
        }, $items);
    }
}
