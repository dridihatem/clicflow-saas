<?php

namespace App\Services;

use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriceMode;
use App\Enums\TaskScope;
use App\Enums\TaskStatus;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectPdfImportService
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private PlatformLlmRouter $llm,
    ) {}

    /**
     * @return array{projects: int, tasks: int, errors: list<string>}
     */
    public function importFile(Company $company, UploadedFile $file): array
    {
        $mime = strtolower((string) $file->getMimeType());
        if ($mime !== 'application/pdf' && ! str_ends_with(strtolower($file->getClientOriginalExtension()), 'pdf')) {
            throw new RuntimeException(__('project_pdf_import_invalid_type'));
        }

        $bytes = $file->getSize();
        if (! is_int($bytes) || $bytes < 1 || $bytes > self::MAX_BYTES) {
            throw new RuntimeException(__('Document must be smaller than 10 MB.'));
        }

        $contents = $file->get();
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException(__('Could not read the uploaded document.'));
        }

        if (! $this->llm->isAvailable($company)) {
            throw new RuntimeException(__('project_pdf_import_ai_unavailable'));
        }

        $base64 = base64_encode($contents);
        $payload = $this->extractStructure($base64, $mime !== '' ? $mime : 'application/pdf', $company);

        $projectsCreated = 0;
        $tasksCreated = 0;
        $errors = [];

        DB::transaction(function () use ($company, $payload, &$projectsCreated, &$tasksCreated, &$errors): void {
            foreach ($payload as $block) {
                $title = trim((string) ($block['title'] ?? ''));
                if ($title === '') {
                    $title = __('Imported project');
                }

                $description = $this->nullableString($block['description'] ?? null);
                $project = Project::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'client_id' => null,
                    'title' => mb_substr($title, 0, 255),
                    'description' => $description,
                    'status' => ProjectStatus::Draft,
                    'source' => ProjectSource::Internal,
                    'created_by' => null,
                ]);
                $projectsCreated++;

                $tasks = is_array($block['tasks'] ?? null) ? $block['tasks'] : [];
                $order = 0;
                foreach ($tasks as $index => $taskRow) {
                    if (! is_array($taskRow)) {
                        continue;
                    }
                    $taskTitle = trim((string) ($taskRow['title'] ?? ''));
                    if ($taskTitle === '') {
                        $errors[] = __('Project :title task row :row: missing title.', [
                            'title' => $title,
                            'row' => $index + 1,
                        ]);

                        continue;
                    }
                    $order++;
                    $project->tasks()->create([
                        'company_id' => $company->id,
                        'title' => mb_substr($taskTitle, 0, 255),
                        'description' => $this->nullableString($taskRow['description'] ?? null),
                        'status' => TaskStatus::Todo,
                        'scope' => TaskScope::Core,
                        'price_mode' => TaskPriceMode::Bundled,
                        'sort_order' => $order,
                        'billable' => true,
                    ]);
                    $tasksCreated++;
                }
            }
        });

        if ($projectsCreated === 0) {
            throw new RuntimeException(__('project_pdf_import_empty'));
        }

        return [
            'projects' => $projectsCreated,
            'tasks' => $tasksCreated,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<array{title: string, description: string|null, tasks: list<array{title: string, description: string|null}>}>
     */
    private function extractStructure(string $base64, string $mime, Company $company): array
    {
        $system = <<<'PROMPT'
You extract project plans from PDF documents (SOW, briefs, proposals, task lists).

Respond with ONLY valid JSON (no markdown):
{"projects":[{"title":"...","description":"...","tasks":[{"title":"...","description":"..."}]}]}

Rules:
- 1 to 5 projects. Prefer one project when the document is a single scope of work.
- title: short project name from the document.
- description: optional short summary (plain text, not HTML).
- tasks: 1 to 40 work items / deliverables / milestones as tasks. Skip pricing-only lines, legal boilerplate, and signatures.
- Same language as the document.
PROMPT;
        $system .= "\n\n".AiAssistantPrompts::outputLanguageInstruction();

        $user = 'Extract project(s) and their tasks from this PDF so we can import them into a project manager.';

        $result = $this->llm->completeWithDocument($system, $user, $base64, $mime, 4096, $company);
        $decoded = $this->decodeJsonObject($result['suggestion']);
        $rows = $decoded['projects'] ?? null;
        if (! is_array($rows) || $rows === []) {
            // Allow a flatter shape {title, tasks}
            if (isset($decoded['title']) || isset($decoded['tasks'])) {
                $rows = [$decoded];
            } else {
                throw new RuntimeException(__('project_pdf_import_empty'));
            }
        }

        $out = [];
        foreach (array_slice($rows, 0, 5) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $tasks = [];
            foreach (array_slice(is_array($row['tasks'] ?? null) ? $row['tasks'] : [], 0, 40) as $task) {
                if (! is_array($task)) {
                    continue;
                }
                $taskTitle = trim((string) ($task['title'] ?? ''));
                if ($taskTitle === '') {
                    continue;
                }
                $tasks[] = [
                    'title' => $taskTitle,
                    'description' => $this->nullableString($task['description'] ?? null),
                ];
            }
            if ($title === '' && $tasks === []) {
                continue;
            }
            $out[] = [
                'title' => $title !== '' ? $title : __('Imported project'),
                'description' => $this->nullableString($row['description'] ?? null),
                'tasks' => $tasks,
            ];
        }

        if ($out === []) {
            throw new RuntimeException(__('project_pdf_import_empty'));
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

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? mb_substr($trimmed, 0, 10000) : null;
    }
}
