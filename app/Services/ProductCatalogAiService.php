<?php

namespace App\Services;

use App\Enums\ProductKind;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\CommerceSyncDispatcher;
use App\Support\CatalogUnits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductCatalogAiService
{
    public const MAX_ITEMS = 12;

    public const MAX_VARIANTS = 24;

    public function __construct(
        private PlatformLlmRouter $llm,
        private CommerceSyncDispatcher $sync,
    ) {}

    /**
     * @return list<Product>
     */
    public function generateAndStore(string $brief, Company $company): array
    {
        $items = $this->propose($brief, $company);
        if ($items === []) {
            throw new RuntimeException(__('catalog_ai_bulk_empty'));
        }

        return DB::transaction(function () use ($items, $company): array {
            $created = [];
            foreach ($items as $row) {
                $created[] = $this->storeItem($company, $row);
            }

            return $created;
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function propose(string $brief, Company $company): array
    {
        $brief = trim($brief);
        if (mb_strlen($brief) < 8) {
            throw new RuntimeException(__('catalog_ai_bulk_prompt_required'));
        }

        $productUnits = implode(', ', CatalogUnits::productValues());
        $serviceUnits = implode(', ', CatalogUnits::serviceValues());
        $currency = strtoupper((string) ($company->default_currency ?? flowdesk_default_currency()));

        $system = <<<PROMPT
You turn a staff catalog brief into products and services for quotes and invoices.
Decide kind automatically for each item:
- product: physical or digital goods (t-shirts, shoes, hardware, packaged goods).
- service: billable work (consulting, design, maintenance, hourly or mission work).

Respond with ONLY valid JSON (no markdown):
{"items":[{"kind":"product","name":"...","description":"...","unit":"pcs","sku":"...","price_major":"29.00","colors":["Black","White"],"sizes":["S","M","L"],"track_stock":true}]}

Rules:
- 1 to 12 items. Same language as the staff brief.
- name: short commercial title (max 80 characters).
- description: 1–3 sellable sentences. Plain text only.
- unit: product units are {$productUnits}. Service units are {$serviceUnits}.
- Shoes/footwear usually unit "pair". Apparel usually "pcs".
- If the brief mentions size and/or color, fill sizes and colors from the brief.
- If they ask for size/color without listing values, use sizes S, M, L and colors Black, White.
- Services: colors and sizes must be empty arrays, track_stock false.
- price_major: decimal string in {$currency}, no currency symbol. Guess a reasonable list price if none is given.
- sku: short uppercase code, letters/digits/dashes only.
- Do not invent legal claims.
PROMPT;
        $system .= "\n\n".AiAssistantPrompts::outputLanguageInstruction();

        $user = "Workspace currency: {$currency}\n\nStaff catalog brief:\n{$brief}\n";

        $result = $this->llm->completeFast($system, $user, 2048, $company, true);
        $decoded = $this->decodeJsonObject($result['suggestion'] ?? '');
        $rows = $decoded['items'] ?? $decoded['products'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        $items = [];
        foreach (array_slice($rows, 0, self::MAX_ITEMS) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = $this->normalizeItem($row);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        if ($items === []) {
            throw new RuntimeException(__('catalog_ai_bulk_empty'));
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function normalizeItem(array $row): ?array
    {
        $name = trim((string) ($row['name'] ?? $row['title'] ?? ''));
        if ($name === '') {
            return null;
        }

        $kind = strtolower(trim((string) ($row['kind'] ?? 'product')));
        if ($kind !== 'service') {
            $kind = 'product';
        }

        $unit = trim((string) ($row['unit'] ?? ''));
        $allowed = CatalogUnits::valuesForKind($kind);
        if (! in_array($unit, $allowed, true)) {
            $unit = CatalogUnits::defaultForKind($kind);
        }

        $colors = $kind === 'service' ? [] : $this->stringList($row['colors'] ?? []);
        $sizes = $kind === 'service' ? [] : $this->stringList($row['sizes'] ?? []);

        $sku = strtoupper((string) preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($row['sku'] ?? '')));
        if ($sku === '') {
            $sku = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii(mb_substr($name, 0, 24))));
            $sku = trim($sku, '-');
        }

        $price = (string) ($row['price_major'] ?? $row['price'] ?? '0');
        $price = preg_replace('/[^\d.,\-]/', '', $price) ?? '0';

        return [
            'kind' => $kind,
            'name' => mb_substr($name, 0, 255),
            'description' => mb_substr(trim((string) ($row['description'] ?? '')), 0, 8000),
            'unit' => $unit,
            'sku' => mb_substr($sku, 0, 64) ?: null,
            'price_major' => $price === '' ? '0' : $price,
            'colors' => $colors,
            'sizes' => $sizes,
            'track_stock' => $kind === 'product' && filter_var($row['track_stock'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function storeItem(Company $company, array $row): Product
    {
        $currency = strtoupper((string) ($company->default_currency ?? flowdesk_default_currency()));
        $product = Product::query()->create([
            'company_id' => $company->id,
            'name' => $row['name'],
            'kind' => $row['kind'] === 'service' ? ProductKind::Service : ProductKind::Product,
            'sku' => $row['sku'],
            'description' => $row['description'] !== '' ? $row['description'] : null,
            'base_price_minor' => flowdesk_decimal_to_minor((string) $row['price_major'], $currency),
            'currency' => $currency,
            'unit' => $row['unit'],
            'track_stock' => $row['kind'] === 'service' ? false : (bool) $row['track_stock'],
            'is_active' => true,
        ]);

        foreach ($this->variantMatrix($row['colors'], $row['sizes']) as $combo) {
            $variant = ProductVariant::query()->create([
                'company_id' => $company->id,
                'product_id' => $product->id,
                'sku' => $this->variantSku($row['sku'], $combo['color'], $combo['size']),
                'color' => $combo['color'],
                'size' => $combo['size'],
                'price_minor' => null,
                'stock_qty' => 0,
            ]);
            $this->sync->pushVariantIfNeeded($company, $variant);
        }

        return $product->load('variants');
    }

    /**
     * @param  list<string>  $colors
     * @param  list<string>  $sizes
     * @return list<array{color: ?string, size: ?string}>
     */
    private function variantMatrix(array $colors, array $sizes): array
    {
        $colors = array_slice($colors, 0, 8);
        $sizes = array_slice($sizes, 0, 12);

        if ($colors === [] && $sizes === []) {
            return [['color' => null, 'size' => null]];
        }
        if ($colors === []) {
            return array_map(fn (string $size): array => ['color' => null, 'size' => $size], $sizes);
        }
        if ($sizes === []) {
            return array_map(fn (string $color): array => ['color' => $color, 'size' => null], $colors);
        }

        $out = [];
        foreach ($colors as $color) {
            foreach ($sizes as $size) {
                $out[] = ['color' => $color, 'size' => $size];
                if (count($out) >= self::MAX_VARIANTS) {
                    return $out;
                }
            }
        }

        return $out;
    }

    private function variantSku(?string $base, ?string $color, ?string $size): ?string
    {
        $parts = array_filter([
            $base,
            $color ? strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $color)) : null,
            $size ? strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $size)) : null,
        ]);
        if ($parts === []) {
            return null;
        }

        return mb_substr(implode('-', $parts), 0, 64);
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,|;\/]+/', $value) ?: [];
        }
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $out[] = mb_substr($text, 0, 64);
            }
        }

        return array_values(array_unique($out));
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
