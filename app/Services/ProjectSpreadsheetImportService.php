<?php

namespace App\Services;

use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskPriceMode;
use App\Enums\TaskScope;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProjectSpreadsheetImportService
{
    /**
     * @return list<array{key: string, required: bool, description: string}>
     */
    public function projectColumns(): array
    {
        return [
            ['key' => 'title', 'required' => false, 'description' => __('project_import_col_title')],
            ['key' => 'description', 'required' => false, 'description' => __('project_import_col_description')],
            ['key' => 'client_email', 'required' => false, 'description' => __('project_import_col_client_email')],
            ['key' => 'client_name', 'required' => false, 'description' => __('project_import_col_client_name')],
            ['key' => 'status', 'required' => false, 'description' => __('project_import_col_status')],
            ['key' => 'budget', 'required' => false, 'description' => __('project_import_col_budget')],
            ['key' => 'currency', 'required' => false, 'description' => __('project_import_col_currency')],
            ['key' => 'deadline', 'required' => false, 'description' => __('project_import_col_deadline')],
        ];
    }

    /**
     * @return list<array{key: string, required: bool, description: string}>
     */
    public function taskColumns(): array
    {
        return [
            ['key' => 'title', 'required' => true, 'description' => __('project_import_col_task_title')],
            ['key' => 'description', 'required' => false, 'description' => __('project_import_col_task_description')],
            ['key' => 'status', 'required' => false, 'description' => __('project_import_col_task_status')],
            ['key' => 'priority', 'required' => false, 'description' => __('project_import_col_task_priority')],
            ['key' => 'starts_on', 'required' => false, 'description' => __('project_import_col_task_starts')],
            ['key' => 'ends_on', 'required' => false, 'description' => __('project_import_col_task_ends')],
        ];
    }

    public function exampleCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        $write = static function (array $row) use ($handle): void {
            fputcsv($handle, $row);
        };

        $write(['# Website rebuild']);
        $write(['title', 'Website rebuild']);
        $write(['description', 'Full redesign of the marketing site']);
        $write(['client_email', 'billing@acme.example']);
        $write(['status', 'draft']);
        $write(['budget', '25000']);
        $write(['currency', 'USD']);
        $write(['deadline', '2026-12-31']);
        $write([]);
        $write(['title', 'description', 'status', 'priority', 'starts_on', 'ends_on']);
        $write(['Discovery', 'Kickoff workshops and research', 'todo', 'high', '2026-10-01', '2026-10-15']);
        $write(['Design', 'UI kit and page layouts', 'todo', 'medium', '2026-10-16', '2026-11-15']);
        $write(['Development', 'Front-end implementation', 'todo', 'medium', '', '']);
        $write([]);
        $write(['# Mobile app']);
        $write(['title', 'Mobile app MVP']);
        $write(['description', 'iOS and Android MVP for client portal']);
        $write(['client_name', 'Jane Doe']);
        $write(['status', 'pending']);
        $write(['budget', '48000']);
        $write(['currency', 'EUR']);
        $write(['deadline', '2027-03-01']);
        $write([]);
        $write(['title', 'description', 'status', 'priority', 'starts_on', 'ends_on']);
        $write(['Product brief', 'Scope and user stories', 'todo', 'urgent', '', '']);
        $write(['Prototype', 'Clickable prototype', 'todo', 'high', '', '']);

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @return array{projects: int, tasks: int, errors: list<string>}
     */
    public function importFile(Company $company, UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException(__('Invalid upload.'));
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $sheets = $ext === 'xlsx' && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)
            ? $this->parseXlsx($path)
            : $this->parseCsvSheets((string) file_get_contents($path));

        if ($sheets === []) {
            throw new RuntimeException(__('No sheets or sections found in file.'));
        }

        $projectsCreated = 0;
        $tasksCreated = 0;
        $errors = [];

        DB::transaction(function () use ($company, $sheets, &$projectsCreated, &$tasksCreated, &$errors): void {
            foreach ($sheets as $sheetName => $payload) {
                $details = $payload['project'] ?? [];
                $taskRows = $payload['tasks'] ?? [];
                $title = trim((string) ($details['title'] ?? ''));
                if ($title === '') {
                    $title = trim((string) $sheetName) !== '' ? trim((string) $sheetName) : __('Imported project');
                }

                $currency = flowdesk_normalize_currency_code(
                    $details['currency'] ?? $company->default_currency ?? flowdesk_default_currency()
                );
                $budgetMinor = null;
                if (isset($details['budget']) && trim((string) $details['budget']) !== '') {
                    $budgetMinor = flowdesk_decimal_to_minor((string) $details['budget'], $currency);
                }

                $status = ProjectStatus::Draft;
                if (! empty($details['status'])) {
                    $status = ProjectStatus::tryFrom(strtolower(trim((string) $details['status'])))
                        ?? ProjectStatus::Draft;
                }

                $client = $this->resolveClient($company, $details);

                $project = Project::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'client_id' => $client?->id,
                    'title' => mb_substr($title, 0, 255),
                    'description' => $this->nullableString($details['description'] ?? null, 50000),
                    'status' => $status,
                    'source' => ProjectSource::Internal,
                    'created_by' => null,
                    'final_price' => $budgetMinor,
                    'final_deadline' => $this->nullableDate($details['deadline'] ?? $details['final_deadline'] ?? null),
                ]);
                $projectsCreated++;

                $order = 0;
                foreach ($taskRows as $index => $taskRow) {
                    $taskTitle = trim((string) ($taskRow['title'] ?? ''));
                    if ($taskTitle === '') {
                        $errors[] = __('Sheet :sheet row :row: missing task title.', [
                            'sheet' => $title,
                            'row' => $index + 1,
                        ]);

                        continue;
                    }

                    $order++;
                    $taskStatus = TaskStatus::Todo;
                    if (! empty($taskRow['status'])) {
                        $taskStatus = TaskStatus::tryFrom(strtolower(trim((string) $taskRow['status'])))
                            ?? TaskStatus::Todo;
                    }
                    $priority = TaskPriority::Medium;
                    if (! empty($taskRow['priority'])) {
                        $priority = TaskPriority::tryFrom(strtolower(trim((string) $taskRow['priority'])))
                            ?? TaskPriority::Medium;
                    }

                    $project->tasks()->create([
                        'company_id' => $company->id,
                        'title' => mb_substr($taskTitle, 0, 255),
                        'description' => $this->nullableString($taskRow['description'] ?? null, 10000),
                        'status' => $taskStatus,
                        'priority' => $priority,
                        'scope' => TaskScope::Core,
                        'price_mode' => TaskPriceMode::Bundled,
                        'sort_order' => $order,
                        'billable' => true,
                        'starts_on' => $this->nullableDate($taskRow['starts_on'] ?? null),
                        'ends_on' => $this->nullableDate($taskRow['ends_on'] ?? null),
                    ]);
                    $tasksCreated++;
                }
            }
        });

        return [
            'projects' => $projectsCreated,
            'tasks' => $tasksCreated,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, array{project: array<string, string|null>, tasks: list<array<string, string|null>>}>
     */
    private function parseXlsx(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $out = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $name = (string) $sheet->getTitle();
            $rows = $sheet->toArray(null, true, true, false);
            $parsed = $this->parseSheetRows($name, is_array($rows) ? $rows : []);
            if ($parsed !== null) {
                $out[$name] = $parsed;
            }
        }

        return $out;
    }

    /**
     * CSV fallback: sections separated by a line starting with # SheetName
     *
     * @return array<string, array{project: array<string, string|null>, tasks: list<array<string, string|null>>}>
     */
    private function parseCsvSheets(string $raw): array
    {
        $raw = trim(str_replace("\r\n", "\n", $raw));
        if ($raw === '') {
            return [];
        }

        $sections = [];
        $current = __('Imported project');
        $buffer = [];

        foreach (explode("\n", $raw) as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '#')) {
                if ($buffer !== []) {
                    $parsed = $this->parseSheetRows($current, $buffer);
                    if ($parsed !== null) {
                        $sections[$current] = $parsed;
                    }
                }
                $current = trim(ltrim($trimmed, '#')) ?: __('Imported project');
                $buffer = [];

                continue;
            }
            $buffer[] = str_getcsv($line);
        }

        if ($buffer !== []) {
            $parsed = $this->parseSheetRows($current, $buffer);
            if ($parsed !== null) {
                $sections[$current] = $parsed;
            }
        }

        if ($sections === []) {
            $lines = array_values(array_filter(explode("\n", $raw), fn ($l) => trim($l) !== ''));
            if ($lines !== []) {
                $parsed = $this->parseSheetRows(__('Imported project'), array_map(fn ($l) => str_getcsv($l), $lines));
                if ($parsed !== null) {
                    return [__('Imported project') => $parsed];
                }
            }
        }

        return $sections;
    }

    /**
     * Each sheet/tab: optional project detail key/value rows, then a task table.
     *
     * @param  list<array<int, mixed>>  $rows
     * @return array{project: array<string, string|null>, tasks: list<array<string, string|null>>}|null
     */
    private function parseSheetRows(string $sheetName, array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }

        $project = [
            'title' => trim($sheetName) !== '' ? trim($sheetName) : __('Imported project'),
            'description' => null,
            'client_email' => null,
            'client_name' => null,
            'status' => null,
            'budget' => null,
            'currency' => null,
            'deadline' => null,
        ];
        $tasks = [];
        $mode = 'meta';
        $map = [];
        $foundMeta = false;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = array_map(fn ($c) => trim((string) $c), $row);
            if ($this->rowIsEmpty($cells)) {
                continue;
            }

            if ($mode === 'meta') {
                if ($this->looksLikeFieldValueHeader($cells)) {
                    continue;
                }

                $meta = $this->metaPair($cells);
                if ($meta !== null) {
                    $foundMeta = true;
                    $project[$meta[0]] = $meta[1];

                    continue;
                }

                if ($this->looksLikeTaskHeader($cells)) {
                    $mode = 'tasks';
                    $map = $this->taskColumnMap($cells);

                    continue;
                }

                // Legacy sheet: no project details, rows are already a task table (or data).
                $mode = 'tasks';
                $map = ['title' => 0, 'description' => 1];
                $tasks[] = $this->taskFromCells($cells, $map);

                continue;
            }

            if ($this->looksLikeTaskHeader($cells) && $tasks === []) {
                $map = $this->taskColumnMap($cells);

                continue;
            }

            $task = $this->taskFromCells($cells, $map);
            if (($task['title'] ?? '') !== '') {
                $tasks[] = $task;
            }
        }

        if (! $foundMeta && $tasks === []) {
            return null;
        }

        return [
            'project' => $project,
            'tasks' => $tasks,
        ];
    }

    /**
     * @param  list<string>  $cells
     * @return array{0: string, 1: string}|null
     */
    private function metaPair(array $cells): ?array
    {
        $nonEmpty = array_values(array_filter($cells, fn ($c) => $c !== ''));
        // Project details are always key/value pairs (exactly two populated cells).
        if (count($nonEmpty) !== 2) {
            return null;
        }

        $key = Str::of($nonEmpty[0])->lower()->replace([' ', '-'], '_')->toString();
        $aliases = [
            'title' => 'title',
            'project' => 'title',
            'project_title' => 'title',
            'name' => 'title',
            'description' => 'description',
            'details' => 'description',
            'brief' => 'description',
            'client_email' => 'client_email',
            'email' => 'client_email',
            'client_name' => 'client_name',
            'client' => 'client_name',
            'status' => 'status',
            'budget' => 'budget',
            'price' => 'budget',
            'final_price' => 'budget',
            'currency' => 'currency',
            'deadline' => 'deadline',
            'final_deadline' => 'deadline',
            'due_date' => 'deadline',
        ];

        if (! isset($aliases[$key])) {
            return null;
        }

        $value = $nonEmpty[1] ?? '';

        return [$aliases[$key], $value];
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, int>
     */
    private function taskColumnMap(array $cells): array
    {
        $map = [
            'title' => 0,
            'description' => 1,
            'status' => null,
            'priority' => null,
            'starts_on' => null,
            'ends_on' => null,
        ];

        foreach ($cells as $idx => $col) {
            $key = Str::of($col)->lower()->replace([' ', '-'], '_')->toString();
            if (in_array($key, ['title', 'task', 'task_title', 'name'], true)) {
                $map['title'] = $idx;
            }
            if (in_array($key, ['description', 'desc', 'details', 'notes'], true)) {
                $map['description'] = $idx;
            }
            if (in_array($key, ['status', 'column', 'state'], true)) {
                $map['status'] = $idx;
            }
            if (in_array($key, ['priority', 'prio'], true)) {
                $map['priority'] = $idx;
            }
            if (in_array($key, ['starts_on', 'start', 'start_date', 'starts'], true)) {
                $map['starts_on'] = $idx;
            }
            if (in_array($key, ['ends_on', 'end', 'end_date', 'deadline', 'due', 'due_date'], true)) {
                $map['ends_on'] = $idx;
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int|null>  $map
     * @return array<string, string|null>
     */
    private function taskFromCells(array $cells, array $map): array
    {
        $get = static function (string $key) use ($cells, $map): ?string {
            $idx = $map[$key] ?? null;
            if ($idx === null) {
                return null;
            }
            $value = $cells[$idx] ?? '';

            return $value !== '' ? $value : null;
        };

        return [
            'title' => $get('title') ?? '',
            'description' => $get('description'),
            'status' => $get('status'),
            'priority' => $get('priority'),
            'starts_on' => $get('starts_on'),
            'ends_on' => $get('ends_on'),
        ];
    }

    /**
     * @param  list<string>  $cells
     */
    private function looksLikeTaskHeader(array $cells): bool
    {
        $known = [
            'title', 'task', 'task_title', 'name',
            'description', 'desc', 'details', 'notes',
            'status', 'column', 'state',
            'priority', 'prio',
            'starts_on', 'start', 'start_date', 'starts',
            'ends_on', 'end', 'end_date', 'deadline', 'due', 'due_date',
        ];

        $matched = 0;
        foreach ($cells as $col) {
            if ($col === '') {
                continue;
            }
            $key = Str::of($col)->lower()->replace([' ', '-'], '_')->toString();
            if (in_array($key, $known, true)) {
                $matched++;
            }
        }

        return $matched >= 2;
    }

    /**
     * @param  list<string>  $cells
     */
    private function looksLikeFieldValueHeader(array $cells): bool
    {
        $a = strtolower($cells[0] ?? '');
        $b = strtolower($cells[1] ?? '');

        return in_array($a, ['field', 'key', 'property', 'attribute'], true)
            && in_array($b, ['value', 'detail', 'details'], true);
    }

    /**
     * @param  list<string>  $cells
     */
    private function rowIsEmpty(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $details
     */
    private function resolveClient(Company $company, array $details): ?Client
    {
        $email = strtolower(trim((string) ($details['client_email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Client::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email', $email)
                ->first();
        }

        $name = trim((string) ($details['client_name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return Client::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->first();
    }

    private function nullableString(mixed $value, int $max = 10000): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function nullableDate(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        $ts = strtotime($value);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
