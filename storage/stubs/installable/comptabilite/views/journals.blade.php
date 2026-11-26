@php
    use Illuminate\Support\Facades\DB;

    $companyId = auth()->user()->company_id;
    $currency = strtoupper((string) (auth()->user()->company?->default_currency ?? 'USD'));

    $journals = DB::table('module_accounting_journals')->where('company_id', $companyId)->orderBy('code')->get();
    $accounts = DB::table('module_accounting_accounts')->where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get();
    $entries = DB::table('module_accounting_entries as e')
        ->join('module_accounting_journals as j', 'j.id', '=', 'e.journal_id')
        ->where('e.company_id', $companyId)
        ->orderByDesc('e.entry_date')
        ->orderByDesc('e.created_at')
        ->limit(50)
        ->get(['e.*', 'j.code as journal_code', 'j.name as journal_name']);
@endphp

<div class="space-y-6">
    @if (session('status'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
    @endif

    <div class="flow-panel p-6 sm:p-8">
        <h3 class="text-lg font-semibold">{{ module_trans($module, 'nav_journals') }}</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ module_trans($module, 'journals_help') }}</p>

        @if ($journals->isEmpty() || $accounts->isEmpty())
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ module_trans($module, 'seed_first') }}</p>
            <form method="post" action="{{ route('modules.actions', $module->slug) }}" class="mt-3">
                @csrf
                <input type="hidden" name="module_action" value="seed_chart">
                <input type="hidden" name="return_page" value="journals">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ module_trans($module, 'seed_chart') }}</button>
            </form>
        @else
            <form method="post" action="{{ route('modules.actions', $module->slug) }}" class="mt-6 space-y-4">
                @csrf
                <input type="hidden" name="module_action" value="store_entry">
                <input type="hidden" name="return_page" value="journals">
                <div class="grid gap-3 sm:grid-cols-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-500">{{ module_trans($module, 'journal') }}</label>
                        <select name="journal_id" required class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                            @foreach ($journals as $journal)
                                <option value="{{ $journal->id }}">{{ $journal->code }} — {{ $journal->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500">{{ __('Date') }}</label>
                        <input type="date" name="entry_date" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500">{{ __('Reference') }}</label>
                        <input name="reference" maxlength="64" class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500">{{ __('Currency') }}</label>
                        <input name="currency" value="{{ $currency }}" maxlength="3" class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                    </div>
                    <div class="sm:col-span-4">
                        <label class="text-xs font-semibold text-slate-500">{{ __('Memo') }}</label>
                        <input name="memo" maxlength="255" class="mt-1 w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ module_trans($module, 'entry_lines') }}</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="space-y-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/40">
                            <p class="text-sm font-semibold">{{ module_trans($module, 'debit_line') }}</p>
                            <select name="debit_account_id" required class="w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" min="0.01" name="debit_amount" required placeholder="{{ module_trans($module, 'amount') }}" class="w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                        </div>
                        <div class="space-y-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/40">
                            <p class="text-sm font-semibold">{{ module_trans($module, 'credit_line') }}</p>
                            <select name="credit_account_id" required class="w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                                @endforeach
                            </select>
                            <input type="number" step="0.01" min="0.01" name="credit_amount" required placeholder="{{ module_trans($module, 'amount') }}" class="w-full rounded-lg border text-sm dark:border-slate-600 dark:bg-slate-900">
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">{{ module_trans($module, 'balanced_hint') }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="post_now" value="1" class="rounded border-slate-300">
                    {{ module_trans($module, 'post_now') }}
                </label>

                <div>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ module_trans($module, 'save_entry') }}</button>
                </div>
            </form>
        @endif
    </div>

    <div class="flow-panel overflow-x-auto p-0">
        <table class="min-w-full text-sm">
            <thead class="border-b bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-3">{{ __('Date') }}</th>
                    <th class="px-4 py-3">{{ module_trans($module, 'journal') }}</th>
                    <th class="px-4 py-3">{{ __('Reference') }}</th>
                    <th class="px-4 py-3">{{ __('Memo') }}</th>
                    <th class="px-4 py-3">{{ __('Status') }}</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-slate-700">
                @forelse ($entries as $entry)
                    <tr>
                        <td class="px-4 py-3 tabular-nums">{{ $entry->entry_date }}</td>
                        <td class="px-4 py-3">{{ $entry->journal_code }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $entry->reference }}</td>
                        <td class="px-4 py-3">{{ $entry->memo }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $entry->status === 'posted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $entry->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            @if ($entry->status !== 'posted')
                                <form method="post" action="{{ route('modules.actions', $module->slug) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="module_action" value="post_entry">
                                    <input type="hidden" name="return_page" value="journals">
                                    <input type="hidden" name="entry_id" value="{{ $entry->id }}">
                                    <button class="text-xs font-semibold text-indigo-600">{{ module_trans($module, 'post_entry') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-slate-500">{{ module_trans($module, 'entries_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
