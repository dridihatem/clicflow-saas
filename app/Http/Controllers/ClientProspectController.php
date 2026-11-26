<?php

namespace App\Http\Controllers;

use App\Enums\ClientProspectStatus;
use App\Models\Client;
use App\Models\ClientProspect;
use App\Services\ClientProspectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientProspectController extends Controller
{
    public function __construct(
        private ClientProspectService $prospects,
    ) {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()?->hasAnyRole(['company_admin', 'team_member']), 403);

            return $next($request);
        });
    }

    private function assertWorkspaceClient(Request $request, Client $client): void
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);
        abort_if((string) $client->company_id !== (string) $company->id, 404);
    }

    private function assertProspect(Client $client, ClientProspect $prospect): void
    {
        abort_if((string) $prospect->company_id !== (string) $client->company_id, 404);
        abort_if((string) $prospect->client_id !== (string) $client->id, 404);
    }

    private function backToTab(Client $client, ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route('clients.show', ['client' => $client, 'tab' => 'prospects']);
        if ($status) {
            $redirect->with('status', $status);
        }

        return $redirect;
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);

        $data = $request->validate($this->rules());
        $this->prospects->create($client, $data, $request->user());

        return $this->backToTab($client, __('client_prospect_created'));
    }

    public function update(Request $request, Client $client, ClientProspect $prospect): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);
        $this->assertProspect($client, $prospect);

        $data = $request->validate($this->rules(false));
        $this->prospects->update($prospect, $data, $request->user());

        return $this->backToTab($client, __('client_prospect_updated'));
    }

    public function destroy(Request $request, Client $client, ClientProspect $prospect): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);
        $this->assertProspect($client, $prospect);
        $this->prospects->delete($prospect);

        return $this->backToTab($client, __('client_prospect_deleted'));
    }

    public function import(Request $request, Client $client): RedirectResponse
    {
        $this->assertWorkspaceClient($request, $client);

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt,text/csv,text/plain', 'max:4096'],
        ]);

        $result = $this->prospects->importCsv($client, $request->file('csv'), $request->user());
        $message = __('client_prospect_import_done', ['count' => $result['created']]);
        if ($result['errors'] !== []) {
            $message .= ' '.__('client_prospect_import_with_errors', ['count' => count($result['errors'])]);
        }

        return $this->backToTab($client, $message)
            ->with('client_prospect_import_errors', $result['errors']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating = true): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:255'],
            'campaign' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::enum(ClientProspectStatus::class)],
            'comment' => ['nullable', 'string', 'max:5000'],
            'captured_on' => ['nullable', 'date'],
            'appointment_at' => ['nullable', 'date'],
        ];
    }
}
