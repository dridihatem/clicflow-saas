@php
    use Illuminate\Support\Facades\DB;

    $companyId = auth()->user()->company_id;
    $accounts = DB::table('module_accounting_accounts')
        ->where('company_id', $companyId)
        ->orderBy('code')
        ->get();
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <div class="flow-panel p-6 sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold">{{ module_trans($module, 'nav_accounts') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ module_trans($module, 'accounts_help') }}</p>
            </div>
            <form method="post" action="{{ route('modules.actions', $module->slug) }}">
                @csrf
                <input type="hidden" name="module_action" value="seed_chart">
                <input type="hidden" name="return_page" value="accounts">
                <button type="submit" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-800 hover:bg-indigo-100">
                    {{ module_trans($module, 'seed_chart') }}
                </button>
            </form>
        </div>

        <form method="post" action="{{ route('modules.actions', $module->slug) }}" class="mt-6 grid gap-3 sm:grid-cols-4">
            @csrf
            <input type="hidden" name="module_action" value="store_account">
            <input type="hidden" name="return_page" value="accounts">
            <div>
                <label class="text-xs font-semibold text-slate-500">{{ module_trans($module, 'account_code') }}</label>
                <input name="code" required maxlength="32" class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900" placeholder="1000">
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-semibold text-slate-500">{{ __('Name') }}</label>
                <input name="name" required maxlength="255" class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-500">{{ __('Type') }}</label>
                <select name="type" class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                    @foreach (['asset','liability','equity','revenue','expense'] as $type)
                        <option value="{{ $type }}">{{ module_trans($module, 'type_'.$type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-4">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ module_trans($module, 'add_account') }}</button>
            </div>
        </form>
    </div>

    <div class="flow-panel overflow-x-auto p-0">
        <table class="min-w-full text-sm">
            <thead class="border-b bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-3">{{ module_trans($module, 'account_code') }}</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3">{{ __('Type') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-slate-700">
                @forelse ($accounts as $account)
                    <tr>
                        <td class="px-4 py-3 font-mono font-semibold">{{ $account->code }}</td>
                        <td class="px-4 py-3">{{ $account->name }}</td>
                        <td class="px-4 py-3">{{ module_trans($module, 'type_'.$account->type) }}</td>
                        <td class="px-4 py-3">{{ $account->is_active ? __('Active') : __('Inactive') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ module_trans($module, 'accounts_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
