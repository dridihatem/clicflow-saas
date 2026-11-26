<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use App\Models\CommerceIntegration;
use App\Models\CommerceSyncLog;
use App\Models\Company;
use App\Models\OdooSyncMap;
use App\Services\Integrations\ThirdPartyCatalog;
use App\Services\Odoo\OdooConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ThirdPartyIntegrationsController extends Controller
{
    use AuthorizesWorkspaceManagers;

    public function __invoke(Request $request, ThirdPartyCatalog $catalog, OdooConfigService $odoo): View
    {
        $this->authorizeWorkspaceManagers($request);
        $company = $request->user()->company;
        abort_if(! $company instanceof Company, 403);

        $cards = $catalog->cards($company);
        $validKeys = collect($cards)->pluck('key')->all();
        $configureKey = strtolower((string) $request->query('configure', ''));
        if (! in_array($configureKey, $validKeys, true)) {
            $configureKey = '';
        }

        $providers = ['perfex', 'shopify', 'woocommerce', 'quickbooks', 'trello', 'notion'];
        $integrations = CommerceIntegration::query()
            ->whereIn('provider', $providers)
            ->get()
            ->keyBy('provider');

        $logs = CommerceSyncLog::query()
            ->whereIn('provider', $providers)
            ->latest()
            ->get()
            ->groupBy('provider')
            ->map(fn (Collection $group) => $group->take(20));

        $odooMapCounts = OdooSyncMap::query()
            ->where('company_id', $company->id)
            ->selectRaw('entity_type, COUNT(*) as aggregate')
            ->groupBy('entity_type')
            ->pluck('aggregate', 'entity_type');

        return view('settings.integrations', [
            'cards' => $cards,
            'configureKey' => $configureKey,
            'sheetTitles' => collect($cards)->mapWithKeys(fn (array $card) => [$card['key'] => $card['title']]),
            'integrations' => $integrations,
            'logs' => $logs,
            'odooForm' => $odoo->toFormArray($company),
            'odooMapCounts' => $odooMapCounts,
        ]);
    }
}
