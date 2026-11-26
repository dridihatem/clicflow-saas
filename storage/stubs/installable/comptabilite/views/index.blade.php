@php
    use Illuminate\Support\Facades\DB;

    $companyId = auth()->user()->company_id;
    $accounts = DB::table('module_accounting_accounts')->where('company_id', $companyId)->count();
    $entries = DB::table('module_accounting_entries')->where('company_id', $companyId)->count();
    $posted = DB::table('module_accounting_entries')->where('company_id', $companyId)->where('status', 'posted')->count();
@endphp

<div class="space-y-6">
    <div class="flow-panel p-6 sm:p-8">
        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $module->localizedName() }}</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $module->localizedDescription() }}</p>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ module_trans($module, 'hub_help') }}</p>
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ route('modules.show', ['slug' => $module->slug, 'page' => 'accounts']) }}" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white">{{ module_trans($module, 'nav_accounts') }}</a>
            <a href="{{ route('modules.show', ['slug' => $module->slug, 'page' => 'journals']) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold dark:border-slate-600">{{ module_trans($module, 'nav_journals') }}</a>
            <a href="{{ route('modules.show', ['slug' => $module->slug, 'page' => 'reports']) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold dark:border-slate-600">{{ module_trans($module, 'nav_reports') }}</a>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ module_trans($module, 'stat_accounts') }}</p>
            <p class="mt-2 text-2xl font-bold tabular-nums">{{ $accounts }}</p>
        </div>
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ module_trans($module, 'stat_entries') }}</p>
            <p class="mt-2 text-2xl font-bold tabular-nums">{{ $entries }}</p>
        </div>
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ module_trans($module, 'stat_posted') }}</p>
            <p class="mt-2 text-2xl font-bold tabular-nums">{{ $posted }}</p>
        </div>
    </div>
</div>
