<?php

namespace App\Http\Controllers;

use App\Services\DataImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()->hasAnyRole(['company_admin', 'team_member']), 403);
            abort_if(! $request->user()->can('workspace.manage_invoices'), 403);

            return $next($request);
        });
    }

    public function index(DataImportService $import): View
    {
        $types = collect(DataImportService::TYPES)->map(fn (string $type) => [
            'key' => $type,
            'label' => $import->typeLabel($type),
            'help' => __('data_import_type_'.$type),
            'columns' => $import->columnsFor($type),
        ])->all();

        return view('imports.index', compact('types'));
    }

    public function previewForm(string $type, DataImportService $import): View
    {
        abort_unless(in_array($type, DataImportService::TYPES, true), 404);

        return view('imports.upload', [
            'type' => $type,
            'typeLabel' => $import->typeLabel($type),
            'columns' => $import->columnsFor($type),
            'exampleCsv' => $import->exampleCsv($type),
        ]);
    }

    public function example(string $type, DataImportService $import): StreamedResponse
    {
        abort_unless(in_array($type, DataImportService::TYPES, true), 404);

        $filename = 'example-'.$type.'.csv';
        $csv = $import->exampleCsv($type);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(Request $request, string $type, DataImportService $import): View|RedirectResponse
    {
        abort_unless(in_array($type, DataImportService::TYPES, true), 404);

        $raw = $this->rawImportPayload($request);
        if ($raw === '') {
            return back()->withErrors(['import_input' => __('prospect_import_empty')])->withInput();
        }

        $rows = $import->parseDelimited($raw);
        if ($rows === []) {
            return back()->withErrors(['import_input' => __('prospect_import_empty')])->withInput();
        }

        return $this->renderPreview($import, $type, $rows, $raw, $request->file('csv_file')?->getClientOriginalName());
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<string, string>|null  $mapping  manual mapping (original header => field key or '')
     * @param  list<string>  $mappingErrors
     */
    private function renderPreview(DataImportService $import, string $type, array $rows, string $raw, ?string $filename, ?array $mapping = null, array $mappingErrors = []): View
    {
        $headerKeys = array_map('strval', array_keys($rows[0]));
        $classification = $import->classifyHeaders($type, $headerKeys);

        $samples = [];
        foreach ($headerKeys as $header) {
            $samples[$header] = collect($rows)->pluck($header)->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->take(3)->values()->all();
        }

        $selected = $mapping ?? ($classification['mapping'] ?? []);
        $parsedPreview = $type === 'products' && request()->user()?->company
            ? $import->previewProducts(request()->user()->company, $import->applyMapping($type, array_slice($rows, 0, 25), $selected))
            : [];

        return view('imports.preview', [
            'parsedPreview' => $parsedPreview,
            'type' => $type,
            'typeLabel' => $import->typeLabel($type),
            'columns' => $import->columnsFor($type),
            'classification' => $classification,
            'rows' => $rows,
            'preview' => array_slice($rows, 0, 25),
            'raw' => $raw,
            'filename' => $filename,
            'headerKeys' => $headerKeys,
            'samples' => $samples,
            'manualMapping' => true,
            'fieldLabels' => $import->fieldLabelsFor($type),
            'mappingRules' => $import->mappingRulesFor($type),
            'confirmLabel' => __('data_import_confirm_'.$type, ['count' => count($rows)]),
            'selectedMapping' => $mapping ?? ($classification['mapping'] ?? []),
            'mappingErrors' => $mappingErrors,
        ]);
    }

    public function store(Request $request, string $type, DataImportService $import): RedirectResponse|View
    {
        abort_unless(in_array($type, DataImportService::TYPES, true), 404);

        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'raw' => ['required', 'string', 'max:2000000'],
            'filename' => ['nullable', 'string', 'max:255'],
            'mapping' => ['nullable', 'array', 'max:200'],
            'mapping.*' => ['nullable', 'string', 'max:64'],
        ]);

        $rows = $import->parseDelimited($data['raw']);
        if ($rows === []) {
            return redirect()->route('import.index')->withErrors(['import_input' => __('prospect_import_empty')]);
        }

        // Manual column mapping: mapping[i] = field key for the i-th header, '' = ignore.
        if (is_array($data['mapping'] ?? null)) {
            $headerKeys = array_map('strval', array_keys($rows[0]));
            $mapping = [];
            foreach ($headerKeys as $i => $header) {
                $mapping[$header] = (string) ($data['mapping'][$i] ?? '');
            }
            $mappingErrors = $import->validateMapping($type, $headerKeys, $mapping);
            if ($mappingErrors !== []) {
                return $this->renderPreview($import, $type, $rows, $data['raw'], $data['filename'] ?? null, $mapping, $mappingErrors);
            }
            $rows = $import->applyMapping($type, $rows, $mapping);
        }

        // Manual CSV imports may create the client of an invoice/quote that does not exist yet.
        $import->createMissingClients(in_array($type, ['invoices', 'proposals'], true));

        $result = match ($type) {
            'clients' => $import->importClients($company, $rows),
            'invoices' => $import->importInvoices($company, $rows),
            'proposals' => $import->importProposals($company, $rows),
            'products' => $import->importProducts($company, $rows),
            'prospects' => $import->importProspects($company, $request->user(), $rows, $data['filename'] ?? null),
        };

        return redirect()
            ->route('import.index')
            ->with('status', __('data_import_done', [
                'type' => $import->typeLabel($type),
                'created' => $result['created'],
                'updated' => $result['updated'] ?? 0,
                'errors' => count($result['errors']),
            ]));
    }

    private function rawImportPayload(Request $request): string
    {
        $request->validate([
            'import_input' => ['nullable', 'string', 'max:2000000'],
            'csv_file' => ['nullable', 'file', 'mimes:csv,txt,tsv', 'max:5120'],
        ]);

        if ($request->hasFile('csv_file')) {
            return (string) file_get_contents($request->file('csv_file')->getRealPath());
        }

        return trim((string) $request->input('import_input', ''));
    }
}
