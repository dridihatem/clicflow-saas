<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\InstalledModule;
use App\Services\ModuleCalendarSyncService;
use App\Services\ModuleSettingsService;
use App\Services\NotionSyncModuleService;
use App\Services\ModuleTranslationService;
use App\Services\PlanLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModuleActionController extends Controller
{
    public function handle(
        Request $request,
        string $slug,
        ModuleSettingsService $moduleSettings,
        ModuleCalendarSyncService $calendarSync,
        PlanLimitService $planLimits,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user && $user->hasAnyRole(['company_admin', 'team_member']), 403);

        $company = $user->company;
        abort_if(! $company instanceof Company, 403);

        abort_unless($planLimits->isFeatureEnabled($company, 'modules'), 403, __('plan_feature_not_included'));

        $module = InstalledModule::query()
            ->where('company_id', $company->id)
            ->where('slug', $slug)
            ->where('is_enabled', true)
            ->firstOrFail();

        $action = (string) $request->input('module_action', '');
        $page = trim((string) $request->input('return_page', ''), '/');

        $redirect = $page === ''
            ? redirect()->route('modules.show', $slug)
            : redirect()->route('modules.show', ['slug' => $slug, 'page' => $page]);

        return match ($action) {
            'save_integrations' => $this->saveIntegrations($request, $module, $moduleSettings, $redirect, $user),
            'store_viewing', 'update_viewing', 'delete_viewing' => $this->handleViewingAction(
                $request,
                $module,
                $company,
                $user,
                $action,
                $moduleSettings,
                $calendarSync,
                $redirect,
            ),
            'seed_chart', 'store_account', 'store_entry', 'post_entry' => $this->handleComptabiliteAction(
                $request,
                $module,
                $company,
                $action,
                $redirect,
            ),
            default => $redirect->with('error', __('modules_action_unknown')),
        };
    }

    private function saveIntegrations(
        Request $request,
        InstalledModule $module,
        ModuleSettingsService $moduleSettings,
        RedirectResponse $redirect,
        $user,
    ): RedirectResponse {
        abort_unless($user->hasRole('company_admin'), 403);

        $toggles = $request->input('integrations', []);
        if (! is_array($toggles)) {
            $toggles = [];
        }

        $moduleSettings->saveIntegrations($module, $toggles);

        return $redirect->with('status', __('module_settings_saved'));
    }

    private function handleViewingAction(
        Request $request,
        InstalledModule $module,
        Company $company,
        $user,
        string $action,
        ModuleSettingsService $moduleSettings,
        ModuleCalendarSyncService $calendarSync,
        RedirectResponse $redirect,
    ): RedirectResponse {
        if (! $this->moduleHasViewings($module)) {
            return $redirect->with('error', __('modules_action_unknown'));
        }

        if ($action === 'delete_viewing') {
            return $this->deleteViewing($request, $company, $calendarSync, $redirect);
        }

        $validated = $request->validate([
            'property_title' => ['required', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:120'],
            'scheduled_at' => ['nullable', 'date'],
            'client_id' => ['nullable', 'string', 'max:26'],
            'viewing_id' => ['nullable', 'string', 'max:26'],
        ]);

        $clientId = $validated['client_id'] ?? null;
        if ($clientId !== null && $clientId !== '') {
            abort_unless(
                DB::table('clients')->where('company_id', $company->id)->where('id', $clientId)->exists(),
                422,
            );
        } else {
            $clientId = null;
        }

        $clientName = $clientId
            ? DB::table('clients')->where('id', $clientId)->value('name')
            : null;

        $calendarEnabled = $moduleSettings->isIntegrationEnabled($module, $company, 'calendar');

        if ($action === 'update_viewing') {
            $viewingId = (string) ($validated['viewing_id'] ?? '');
            abort_if($viewingId === '', 422);

            $exists = DB::table('module_property_viewings')
                ->where('company_id', $company->id)
                ->where('id', $viewingId)
                ->exists();
            abort_unless($exists, 404);

            DB::table('module_property_viewings')
                ->where('company_id', $company->id)
                ->where('id', $viewingId)
                ->update([
                    'property_title' => $validated['property_title'],
                    'zone' => ($validated['zone'] ?? null) ?: null,
                    'scheduled_at' => $validated['scheduled_at'] ?? null,
                    'client_id' => $clientId,
                    'updated_at' => now(),
                ]);

            if ($calendarEnabled) {
                $calendarSync->syncPropertyViewing(
                    $company,
                    $user,
                    $viewingId,
                    $validated['property_title'],
                    $validated['zone'] ?? null,
                    $validated['scheduled_at'] ?? null,
                    is_string($clientName) ? $clientName : null,
                    $module->slug,
                );
            } else {
                $calendarSync->detachViewingCalendarEvent($company, $viewingId);
            }

            return $redirect->with('status', __('module_viewing_updated'));
        }

        $viewingId = (string) Str::ulid();

        DB::table('module_property_viewings')->insert([
            'id' => $viewingId,
            'company_id' => $company->id,
            'client_id' => $clientId,
            'property_title' => $validated['property_title'],
            'zone' => ($validated['zone'] ?? null) ?: null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($calendarEnabled && filled($validated['scheduled_at'] ?? null)) {
            $calendarSync->syncPropertyViewing(
                $company,
                $user,
                $viewingId,
                $validated['property_title'],
                $validated['zone'] ?? null,
                $validated['scheduled_at'] ?? null,
                is_string($clientName) ? $clientName : null,
                $module->slug,
            );
        }

        return $redirect->with('status', __('module_viewing_saved'));
    }

    private function deleteViewing(
        Request $request,
        Company $company,
        ModuleCalendarSyncService $calendarSync,
        RedirectResponse $redirect,
    ): RedirectResponse {
        $viewingId = (string) $request->input('viewing_id', '');
        abort_if($viewingId === '', 422);

        $exists = DB::table('module_property_viewings')
            ->where('company_id', $company->id)
            ->where('id', $viewingId)
            ->exists();
        abort_unless($exists, 404);

        $calendarSync->detachViewingCalendarEvent($company, $viewingId);

        DB::table('module_property_viewings')
            ->where('company_id', $company->id)
            ->where('id', $viewingId)
            ->delete();

        return $redirect->with('status', __('module_viewing_deleted'));
    }

    private function moduleHasViewings(InstalledModule $module): bool
    {
        if ($module->slug === 'qatar-real-estate' || $module->slug === 'qatar-property-viewings') {
            return true;
        }

        $pages = collect($module->manifest['pages'] ?? [])->pluck('slug');

        return $pages->contains('viewings');
    }

    private function handleComptabiliteAction(
        Request $request,
        InstalledModule $module,
        Company $company,
        string $action,
        RedirectResponse $redirect,
    ): RedirectResponse {
        if ($module->slug !== 'comptabilite') {
            return $redirect->with('error', __('modules_action_unknown'));
        }

        app(ModuleTranslationService::class)->register($module);

        return match ($action) {
            'seed_chart' => $this->seedComptabiliteChart($company, $module, $redirect),
            'store_account' => $this->storeComptabiliteAccount($request, $company, $module, $redirect),
            'store_entry' => $this->storeComptabiliteEntry($request, $company, $module, $redirect),
            'post_entry' => $this->postComptabiliteEntry($request, $company, $module, $redirect),
            'save_notion_settings' => $this->saveNotionSettings($request, $module, $user, $redirect),
            'test_notion_connection' => $this->testNotionConnection($module, $user, $redirect),
            default => $redirect->with('error', __('modules_action_unknown')),
        };
    }

    private function saveNotionSettings(
        Request $request,
        InstalledModule $module,
        $user,
        RedirectResponse $redirect,
    ): RedirectResponse {
        abort_unless($module->slug === 'notion-sync', 404);
        abort_unless($user->hasRole('company_admin'), 403);

        $data = $request->validate([
            'notion_api_token' => ['nullable', 'string', 'max:500'],
            'notion_database_id' => ['nullable', 'string', 'max:64'],
            'sync_enabled' => ['nullable', 'boolean'],
        ]);

        app(NotionSyncModuleService::class)->saveSettings($module, [
            'notion_api_token' => $data['notion_api_token'] ?? '',
            'notion_database_id' => $data['notion_database_id'] ?? '',
            'sync_enabled' => $request->boolean('sync_enabled'),
        ]);

        return $redirect->with('status', __('module_settings_saved'));
    }

    private function testNotionConnection(
        InstalledModule $module,
        $user,
        RedirectResponse $redirect,
    ): RedirectResponse {
        abort_unless($module->slug === 'notion-sync', 404);
        abort_unless($user->hasRole('company_admin'), 403);

        $result = app(NotionSyncModuleService::class)->testConnection($module);

        return $redirect->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    private function seedComptabiliteChart(Company $company, InstalledModule $module, RedirectResponse $redirect): RedirectResponse
    {
        $accounts = [
            ['1000', 'asset'],
            ['1100', 'asset'],
            ['1200', 'asset'],
            ['1300', 'asset'],
            ['1500', 'asset'],
            ['2000', 'liability'],
            ['2100', 'liability'],
            ['2200', 'liability'],
            ['3000', 'equity'],
            ['3100', 'equity'],
            ['4000', 'revenue'],
            ['4100', 'revenue'],
            ['5000', 'expense'],
            ['6000', 'expense'],
            ['6100', 'expense'],
            ['6200', 'expense'],
            ['6300', 'expense'],
            ['6400', 'expense'],
            ['6900', 'expense'],
        ];

        foreach ($accounts as [$code, $type]) {
            $exists = DB::table('module_accounting_accounts')
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('module_accounting_accounts')->insert([
                'id' => (string) Str::ulid(),
                'company_id' => $company->id,
                'code' => $code,
                'name' => module_trans($module, 'account_'.$code),
                'type' => $type,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $journals = [
            ['GEN', 'general'],
            ['SAL', 'sales'],
            ['PUR', 'purchases'],
            ['BAN', 'bank'],
        ];
        foreach ($journals as [$code, $kind]) {
            $exists = DB::table('module_accounting_journals')
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('module_accounting_journals')->insert([
                'id' => (string) Str::ulid(),
                'company_id' => $company->id,
                'code' => $code,
                'name' => module_trans($module, 'journal_'.$code),
                'kind' => $kind,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $redirect->with('status', module_trans($module, 'chart_seeded'));
    }

    private function storeComptabiliteAccount(
        Request $request,
        Company $company,
        InstalledModule $module,
        RedirectResponse $redirect,
    ): RedirectResponse {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
        ]);

        $exists = DB::table('module_accounting_accounts')
            ->where('company_id', $company->id)
            ->where('code', $data['code'])
            ->exists();
        if ($exists) {
            return $redirect->with('error', module_trans($module, 'account_code').' '.$data['code']);
        }

        DB::table('module_accounting_accounts')->insert([
            'id' => (string) Str::ulid(),
            'company_id' => $company->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $redirect->with('status', module_trans($module, 'account_saved'));
    }

    private function storeComptabiliteEntry(
        Request $request,
        Company $company,
        InstalledModule $module,
        RedirectResponse $redirect,
    ): RedirectResponse {
        $data = $request->validate([
            'journal_id' => ['required', 'string', 'max:26'],
            'entry_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:64'],
            'memo' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'debit_account_id' => ['required', 'string', 'max:26'],
            'credit_account_id' => ['required', 'string', 'max:26'],
            'debit_amount' => ['required', 'numeric', 'min:0.01'],
            'credit_amount' => ['required', 'numeric', 'min:0.01'],
            'post_now' => ['nullable', 'boolean'],
        ]);

        $currency = flowdesk_normalize_currency_code($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency());
        $debitMinor = (int) flowdesk_decimal_to_minor((string) $data['debit_amount'], $currency);
        $creditMinor = (int) flowdesk_decimal_to_minor((string) $data['credit_amount'], $currency);

        if ($debitMinor !== $creditMinor || $debitMinor <= 0) {
            return $redirect->with('error', module_trans($module, 'entry_unbalanced'));
        }

        abort_unless(
            DB::table('module_accounting_journals')->where('company_id', $company->id)->where('id', $data['journal_id'])->exists(),
            422,
        );
        abort_unless(
            DB::table('module_accounting_accounts')->where('company_id', $company->id)->where('id', $data['debit_account_id'])->exists(),
            422,
        );
        abort_unless(
            DB::table('module_accounting_accounts')->where('company_id', $company->id)->where('id', $data['credit_account_id'])->exists(),
            422,
        );

        $entryId = (string) Str::ulid();
        $status = $request->boolean('post_now') ? 'posted' : 'draft';

        DB::transaction(function () use ($company, $data, $currency, $debitMinor, $creditMinor, $entryId, $status): void {
            DB::table('module_accounting_entries')->insert([
                'id' => $entryId,
                'company_id' => $company->id,
                'journal_id' => $data['journal_id'],
                'entry_date' => $data['entry_date'],
                'reference' => $data['reference'] ?? null,
                'memo' => $data['memo'] ?? null,
                'status' => $status,
                'currency' => $currency,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('module_accounting_entry_lines')->insert([
                [
                    'id' => (string) Str::ulid(),
                    'company_id' => $company->id,
                    'entry_id' => $entryId,
                    'account_id' => $data['debit_account_id'],
                    'label' => $data['memo'] ?? null,
                    'debit_minor' => $debitMinor,
                    'credit_minor' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => (string) Str::ulid(),
                    'company_id' => $company->id,
                    'entry_id' => $entryId,
                    'account_id' => $data['credit_account_id'],
                    'label' => $data['memo'] ?? null,
                    'debit_minor' => 0,
                    'credit_minor' => $creditMinor,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        });

        return $redirect->with('status', module_trans($module, $status === 'posted' ? 'entry_posted' : 'entry_saved'));
    }

    private function postComptabiliteEntry(
        Request $request,
        Company $company,
        InstalledModule $module,
        RedirectResponse $redirect,
    ): RedirectResponse {
        $entryId = (string) $request->input('entry_id', '');
        abort_if($entryId === '', 422);

        $updated = DB::table('module_accounting_entries')
            ->where('company_id', $company->id)
            ->where('id', $entryId)
            ->where('status', '!=', 'posted')
            ->update([
                'status' => 'posted',
                'updated_at' => now(),
            ]);

        abort_unless($updated > 0, 404);

        return $redirect->with('status', module_trans($module, 'entry_posted'));
    }

}
