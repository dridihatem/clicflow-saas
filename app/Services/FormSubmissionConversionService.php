<?php

namespace App\Services;

use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriceMode;
use App\Enums\TaskScope;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\FormSubmission;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FormSubmissionConversionService
{
    private const MAX_AUTO_TASKS = 12;

    public function __construct(
        private PlatformLlmRouter $llm,
        private PlanLimitService $planLimits,
        private AiCreditUsageService $usage,
    ) {}

    /**
     * Flatten submission payload into readable lines for tables / descriptions.
     *
     * @param  array<string, mixed>  $data
     */
    public function dataAsText(array $data): string
    {
        if ($data === []) {
            return '—';
        }

        $lines = [];
        foreach ($data as $key => $value) {
            $label = is_string($key) ? trim(str_replace(['_', '-'], ' ', $key)) : (string) $key;
            $label = $label !== '' ? Str::headline($label) : __('Field');
            $lines[] = $label.': '.$this->valueAsText($value);
        }

        return implode("\n", $lines);
    }

    /**
     * Compact one-line summary for table cells (skips name/email/phone shown separately).
     *
     * @param  array<string, mixed>  $data
     */
    public function dataAsSummary(array $data, int $maxLen = 220): string
    {
        $skip = [
            'name', 'full_name', 'fullnameName', 'client_name', 'contact_name', 'nom', 'prenom', 'prénom',
            'first_name', 'last_name', 'firstname', 'lastname',
            'email', 'e-mail', 'mail', 'courriel', 'contact_email', 'client_email',
            'phone', 'telephone', 'tel', 'mobile', 'cellphone', 'cell', 'whatsapp', 'téléphone',
            'contact_phone', 'client_phone',
        ];
        $skipLookup = array_fill_keys(array_map('mb_strtolower', $skip), true);

        $parts = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && isset($skipLookup[mb_strtolower(trim($key))])) {
                continue;
            }
            $label = is_string($key) ? Str::headline(str_replace(['_', '-'], ' ', $key)) : (string) $key;
            $parts[] = $label.': '.$this->valueAsText($value);
        }

        $text = implode(' · ', $parts);

        return $text === '' ? '—' : Str::limit($text, $maxLen, '…');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, email: ?string, phone: ?string}
     */
    public function extractContact(array $data): array
    {
        $name = $this->firstString($data, [
            'name', 'Name', 'full_name', 'fullnameName', 'fullname name', 'client_name', 'client name',
            'contact_name', 'contact name', 'nom', 'Nom', 'prenom', 'prénom', 'first_name', 'last_name',
        ]);

        if ($name === null) {
            $first = $this->firstString($data, ['first_name', 'firstname', 'prénom', 'prenom']);
            $last = $this->firstString($data, ['last_name', 'lastname', 'nom de famille', 'family_name']);
            if ($first || $last) {
                $name = trim(($first ?? '').' '.($last ?? ''));
            }
        }

        $email = $this->firstString($data, [
            'email', 'Email', 'e-mail', 'mail', 'courriel', 'contact_email', 'client_email',
        ]);

        $phone = $this->firstString($data, [
            'phone', 'Phone', 'telephone', 'Telephone', 'tel', 'Tel', 'mobile', 'Mobile',
            'cellphone', 'cell', 'whatsapp', 'téléphone', 'Telephone', 'contact_phone', 'client_phone',
        ]);

        if ($name === null || $name === '') {
            $name = __('Lead from form');
        }

        return [
            'name' => Str::limit($name, 255, ''),
            'email' => $email !== null ? Str::limit($email, 255, '') : null,
            'phone' => $phone !== null ? Str::limit($phone, 64, '') : null,
        ];
    }

    /**
     * Create (or reuse) a client and project from a form submission.
     * When AI credits allow, generate description, tasks, final price and negotiated price.
     *
     * @return array{project: Project, ai_used: bool, tasks_created: int}
     */
    public function convertToProject(User $user, FormSubmission $submission, bool $withAi = true): array
    {
        $company = $user->company;
        if ($company === null) {
            throw new RuntimeException(__('Unauthorized.'));
        }

        $data = is_array($submission->data) ? $submission->data : [];
        $contact = $this->extractContact($data);
        $client = $this->findOrCreateClient($company, $contact['name'], $contact['phone'], $contact['email']);

        $textDump = $this->dataAsText($data);
        $title = $contact['name'] !== '' ? $contact['name'] : __('Project from submission');

        $project = Project::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'provider_id' => null,
            'created_by' => $user->id,
            'title' => $title,
            'status' => ProjectStatus::Draft,
            'description' => '<p>'.e(__('Form submission')).'</p><pre>'.e($textDump).'</pre>',
            'source' => ProjectSource::FormWebsite,
            'form_submission_id' => $submission->id,
        ]);

        $aiUsed = false;
        $tasksCreated = 0;

        if ($withAi) {
            $creditCost = $this->usage->creditsForTask(AiCreditUsageService::TASK_PROJECT_WORKFLOW);
            if ($this->planLimits->isFeatureEnabled($company, 'ai_credits')
                && $this->planLimits->allows($company, 'ai_credits', $creditCost)) {
                try {
                    $tasksCreated = $this->enrichWithAi($project, $data, $contact, $company);
                    $this->usage->recordForTask($company, AiCreditUsageService::TASK_PROJECT_WORKFLOW);
                    $aiUsed = true;
                } catch (RuntimeException) {
                    // Keep the draft project with the submission dump if AI fails.
                }
            }
        }

        return [
            'project' => $project->fresh(['client', 'tasks']),
            'ai_used' => $aiUsed,
            'tasks_created' => $tasksCreated,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{name: string, email: ?string, phone: ?string}  $contact
     */
    private function enrichWithAi(Project $project, array $data, array $contact, Company $company): int
    {
        $currency = flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency());
        $submissionText = $this->dataAsText($data);

        $userBlock = 'Convert this website form submission into a delivery-ready project.'."\n"
            .'Workspace currency (major units for prices): '.$currency."\n"
            .'Suggested client name: '.$contact['name']."\n"
            .'Email: '.($contact['email'] ?? '—')."\n"
            .'Phone: '.($contact['phone'] ?? '—')."\n\n"
            ."Submission fields:\n".$submissionText;

        $system = <<<'PROMPT'
You are an expert sales + delivery manager. Respond with ONLY valid JSON (no markdown, no code fences).

Use this exact structure:
{
  "title": "Short project title",
  "description_html": "<p>...</p>",
  "tasks": [{"title":"...","description":"..."}],
  "final_price": 1200.00,
  "negotiated_price": 1000.00
}

Rules:
- title: concise, client-facing project name (max ~80 chars).
- description_html: HTML fragment only (<p>, <ul>, <li>, <strong>). Summarize the request, goals, scope, and next steps from the submission.
- tasks: 4–12 actionable verb-led tasks; optional one-line description each.
- final_price: suggested list / package price in major currency units (number), or null if impossible to infer.
- negotiated_price: realistic opening negotiated / discounted target in major units (number), usually <= final_price, or null.
- Use the output language rules below; prefer the language of the submission text when clear.

PROMPT;
        $system .= "\n\n".AiAssistantPrompts::workflowJsonOutputLanguageRules();

        $result = $this->llm->complete($system, $userBlock, 8192, $company);
        $decoded = $this->decodeJsonObject($result['suggestion']);

        $title = isset($decoded['title']) ? trim((string) $decoded['title']) : '';
        $htmlRaw = $decoded['description_html'] ?? $decoded['description'] ?? null;
        if (! is_string($htmlRaw) || trim($htmlRaw) === '') {
            throw new RuntimeException(__('The AI response did not include a description.'));
        }
        $html = $this->normalizeDescriptionHtml($htmlRaw);

        $items = $decoded['tasks'] ?? [];
        if (! is_array($items)) {
            $items = [];
        }
        $items = array_slice($items, 0, self::MAX_AUTO_TASKS);

        $finalMinor = $this->parseOptionalMajorToMinor($decoded['final_price'] ?? null, $currency);
        $negMinor = $this->parseOptionalMajorToMinor($decoded['negotiated_price'] ?? null, $currency);
        if ($finalMinor === null && $negMinor !== null) {
            $finalMinor = $negMinor;
        }
        if ($negMinor === null && $finalMinor !== null) {
            $negMinor = $finalMinor;
        }

        $created = 0;

        DB::transaction(function () use ($project, $title, $html, $items, $finalMinor, $negMinor, &$created): void {
            $project->forceFill([
                'title' => $title !== '' ? Str::limit($title, 255, '') : $project->title,
                'description' => $html,
                'final_price' => $finalMinor,
                'negotiated_price' => $negMinor,
            ])->save();

            $maxOrder = (int) $project->tasks()->where('status', TaskStatus::Todo)->max('sort_order');
            foreach ($items as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $t = isset($row['title']) ? trim((string) $row['title']) : '';
                if ($t === '') {
                    continue;
                }
                $desc = isset($row['description']) ? trim((string) $row['description']) : '';
                $maxOrder++;
                ProjectTask::query()->create([
                    'company_id' => $project->company_id,
                    'project_id' => $project->id,
                    'title' => Str::limit($t, 255, ''),
                    'description' => $desc === '' ? null : Str::limit($desc, 5000, ''),
                    'status' => TaskStatus::Todo,
                    'sort_order' => $maxOrder,
                    'scope' => TaskScope::Core,
                    'price_mode' => TaskPriceMode::Bundled,
                    'billable' => false,
                    'amount_cents' => null,
                    'currency' => null,
                ]);
                $created++;
            }
        });

        return $created;
    }

    private function findOrCreateClient(Company $company, string $name, ?string $phone, ?string $email): Client
    {
        $base = Client::query()->withoutGlobalScopes()->where('company_id', $company->id);

        if ($email !== null && $email !== '') {
            $byEmail = (clone $base)->where('email', $email)->first();
            if ($byEmail) {
                $updates = [];
                if (($byEmail->name === null || $byEmail->name === '') && $name !== '') {
                    $updates['name'] = $name;
                }
                if (($byEmail->phone === null || $byEmail->phone === '') && $phone) {
                    $updates['phone'] = $phone;
                }
                if ($updates !== []) {
                    $byEmail->forceFill($updates)->save();
                }

                return $byEmail;
            }
        }

        $digits = $this->normalizePhoneDigits($phone);
        if ($digits !== '') {
            foreach ((clone $base)->whereNotNull('phone')->get() as $c) {
                if ($this->normalizePhoneDigits($c->phone) === $digits) {
                    $updates = [];
                    if (($c->name === null || $c->name === '') && $name !== '') {
                        $updates['name'] = $name;
                    }
                    if (($c->email === null || $c->email === '') && $email) {
                        $updates['email'] = $email;
                    }
                    if ($updates !== []) {
                        $c->forceFill($updates)->save();
                    }

                    return $c;
                }
            }
        }

        if ($name !== '' && $name !== __('Lead from form')) {
            $byName = (clone $base)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            if ($byName) {
                $updates = [];
                if (($byName->phone === null || $byName->phone === '') && $phone) {
                    $updates['phone'] = $phone;
                }
                if (($byName->email === null || $byName->email === '') && $email) {
                    $updates['email'] = $email;
                }
                if ($updates !== []) {
                    $byName->forceFill($updates)->save();
                }

                return $byName;
            }
        }

        return Client::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $name !== '' ? $name : __('New client'),
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function firstString(array $data, array $keys): ?string
    {
        $normalized = [];
        foreach ($data as $k => $v) {
            if (! is_string($k)) {
                continue;
            }
            $normalized[mb_strtolower(trim($k))] = $v;
        }

        foreach ($keys as $key) {
            $look = mb_strtolower(trim($key));
            if (! array_key_exists($look, $normalized)) {
                continue;
            }
            $value = $normalized[$look];
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_numeric($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function valueAsText(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }
        if (is_scalar($value)) {
            return trim((string) $value);
        }
        if (is_array($value)) {
            $flat = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $flat[] = (string) $item;
                }
            }

            return $flat === [] ? json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—' : implode(', ', $flat);
        }

        return '—';
    }

    private function normalizePhoneDigits(?string $phone): string
    {
        if ($phone === null || $phone === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function parseOptionalMajorToMinor(mixed $value, string $currency): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace([',', ' '], ['', ''], $value);
        }
        if (! is_numeric($value) || (float) $value < 0) {
            return null;
        }

        $scale = max(0, flowdesk_currency_minor_scale($currency));

        return (int) round(((float) $value) * (10 ** $scale));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $raw): array
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
            $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException(__('Empty AI response.'));
        }

        return $decoded;
    }

    private function normalizeDescriptionHtml(string $html): string
    {
        $html = trim($html);
        $html = strip_tags($html, '<p><ul><ol><li><strong><em><br><b><i>');
        if ($html === '') {
            return '<p></p>';
        }
        if (! str_contains($html, '<')) {
            return '<p>'.e($html).'</p>';
        }

        return $html;
    }
}
