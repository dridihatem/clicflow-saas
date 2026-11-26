<?php

namespace App\Http\Controllers;

use App\Enums\ClientCampaignChannel;
use App\Enums\ClientCampaignStatus;
use App\Models\Client;
use App\Models\ClientCampaign;
use App\Services\ClientCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientCampaignController extends Controller
{
    public function __construct(private ClientCampaignService $campaigns)
    {
        $this->middleware(function ($request, $next) {
            abort_if(! $request->user()?->hasAnyRole(['company_admin', 'team_member']), 403);

            return $next($request);
        });
    }

    private function assertClient(Request $request, Client $client): void
    {
        abort_if(! $request->user()->company, 403);
        abort_if((string) $client->company_id !== (string) $request->user()->company->id, 404);
    }

    private function assertCampaign(Client $client, ClientCampaign $campaign): void
    {
        abort_if((string) $campaign->client_id !== (string) $client->id, 404);
        abort_if((string) $campaign->company_id !== (string) $client->company_id, 404);
    }

    private function back(Client $client, ?string $status = null): RedirectResponse
    {
        $r = redirect()->route('clients.show', ['client' => $client, 'tab' => 'campaigns']);

        return $status ? $r->with('status', $status) : $r;
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $this->assertClient($request, $client);
        $data = $request->validate($this->rules());
        $this->campaigns->create($client, $data, $request->user());

        return $this->back($client, __('client_campaign_created'));
    }

    public function update(Request $request, Client $client, ClientCampaign $campaign): RedirectResponse
    {
        $this->assertClient($request, $client);
        $this->assertCampaign($client, $campaign);
        $this->campaigns->update($campaign, $request->validate($this->rules(false)));

        return $this->back($client, __('client_campaign_updated'));
    }

    public function destroy(Request $request, Client $client, ClientCampaign $campaign): RedirectResponse
    {
        $this->assertClient($request, $client);
        $this->assertCampaign($client, $campaign);
        $this->campaigns->delete($campaign);

        return $this->back($client, __('client_campaign_deleted'));
    }

    /** @return array<string, mixed> */
    private function rules(bool $creating = true): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'channel' => ['nullable', 'string', Rule::enum(ClientCampaignChannel::class)],
            'status' => ['nullable', 'string', Rule::enum(ClientCampaignStatus::class)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:8'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
