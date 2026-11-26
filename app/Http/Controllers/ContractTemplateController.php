<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Services\ContractMergeService;
use App\Services\ContractTemplateBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContractTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()->hasAnyRole(['company_admin', 'team_member']), 403);
            abort_if(! $request->user()->can('workspace.manage_clients'), 403);

            return $next($request);
        });
    }

    public function index(Request $request, ContractTemplateBootstrapService $bootstrap): View
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $bootstrap->ensureDefaults($company);

        $templates = ContractTemplate::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('contract-templates.index', compact('templates'));
    }

    public function create(ContractMergeService $merge): View
    {
        abort_if(! auth()->user()?->company, 403);

        return view('contract-templates.create', [
            'mergeHints' => $merge->editorHints(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $this->validated($request);

        $template = ContractTemplate::query()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'type_key' => $data['type_key'],
            'body_html' => $data['body_html'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('contract-templates.edit', $template)
            ->with('status', __('contract_template_saved'));
    }

    public function edit(Request $request, ContractTemplate $contractTemplate, ContractMergeService $merge): View
    {
        $this->authorizeTemplate($request, $contractTemplate);

        return view('contract-templates.edit', [
            'template' => $contractTemplate,
            'mergeHints' => $merge->editorHints(),
        ]);
    }

    public function update(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->authorizeTemplate($request, $contractTemplate);

        $data = $this->validated($request);

        $contractTemplate->update([
            'name' => $data['name'],
            'type_key' => $data['type_key'],
            'body_html' => $data['body_html'],
            'is_active' => $data['is_active'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return redirect()
            ->route('contract-templates.edit', $contractTemplate)
            ->with('status', __('contract_template_saved'));
    }

    public function destroy(Request $request, ContractTemplate $contractTemplate): RedirectResponse
    {
        $this->authorizeTemplate($request, $contractTemplate);
        $contractTemplate->delete();

        return redirect()
            ->route('contract-templates.index')
            ->with('status', __('contract_template_deleted'));
    }

    /**
     * @return array{name: string, type_key: string, body_html: string, is_active?: bool, sort_order?: int}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type_key' => ['required', 'string', 'max:40', Rule::in(['service', 'nda', 'retainer', 'consulting', 'custom'])],
            'body_html' => ['required', 'string', 'max:2000000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function authorizeTemplate(Request $request, ContractTemplate $template): void
    {
        abort_if((string) $template->company_id !== (string) $request->user()->company_id, 404);
    }
}
