@php
    use Illuminate\Support\Facades\DB;

    $companyId = auth()->user()->company_id;
    $currency = strtoupper((string) (auth()->user()->company?->default_currency ?? 'USD'));

    $lines = DB::table('module_accounting_entry_lines as l')
        ->join('module_accounting_entries as e', 'e.id', '=', 'l.entry_id')
        ->join('module_accounting_accounts as a', 'a.id', '=', 'l.account_id')
        ->where('l.company_id', $companyId)
        ->where('e.status', 'posted')
        ->get(['a.id as account_id', 'a.code', 'a.name', 'a.type', 'l.debit_minor', 'l.credit_minor']);

    $grouped = [];
    foreach ($lines as $line) {
        $key = (string) $line->account_id;
        if (! isset($grouped[$key])) {
            $grouped[$key] = (object) [
                'code' => $line->code,
                'name' => $line->name,
                'type' => $line->type,
                'debit_minor' => 0,
                'credit_minor' => 0,
            ];
        }
        $grouped[$key]->debit_minor += (int) $line->debit_minor;
        $grouped[$key]->credit_minor += (int) $line->credit_minor;
    }
    $rows = collect($grouped)->sortBy('code')->values();

    $totalDebit = (int) $rows->sum('debit_minor');
    $totalCredit = (int) $rows->sum('credit_minor');

    $pnl = [
        'revenue' => 0,
        'expense' => 0,
    ];
    $bs = [
        'asset' => 0,
        'liability' => 0,
        'equity' => 0,
    ];
    foreach ($rows as $row) {
        $net = (int) $row->debit_minor - (int) $row->credit_minor;
        if ($row->type === 'revenue') {
            $pnl['revenue'] += ((int) $row->credit_minor - (int) $row->debit_minor);
        } elseif ($row->type === 'expense') {
            $pnl['expense'] += ((int) $row->debit_minor - (int) $row->credit_minor);
        } elseif ($row->type === 'asset') {
            $bs['asset'] += $net;
        } elseif ($row->type === 'liability') {
            $bs['liability'] += ((int) $row->credit_minor - (int) $row->debit_minor);
        } elseif ($row->type === 'equity') {
            $bs['equity'] += ((int) $row->credit_minor - (int) $row->debit_minor);
        }
    }
    $netIncome = $pnl['revenue'] - $pnl['expense'];
@endphp

<div class="space-y-6">
    <div class="flow-panel p-6 sm:p-8">
        <h3 class="text-lg font-semibold">{{ module_trans($module, 'nav_reports') }}</h3>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ module_trans($module, 'reports_help') }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ module_trans($module, 'pnl_revenue') }}</p>
            <p class="mt-2 text-xl font-bold tabular-nums">{{ flowdesk_format_minor($pnl['revenue'], $currency) }} {{ $currency }}</p>
        </div>
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ module_trans($module, 'pnl_expense') }}</p>
            <p class="mt-2 text-xl font-bold tabular-nums">{{ flowdesk_format_minor($pnl['expense'], $currency) }} {{ $currency }}</p>
        </div>
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ module_trans($module, 'pnl_net') }}</p>
            <p class="mt-2 text-xl font-bold tabular-nums {{ $netIncome >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ flowdesk_format_minor($netIncome, $currency) }} {{ $currency }}</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ module_trans($module, 'type_asset') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums">{{ flowdesk_format_minor($bs['asset'], $currency) }} {{ $currency }}</p>
        </div>
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ module_trans($module, 'type_liability') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums">{{ flowdesk_format_minor($bs['liability'], $currency) }} {{ $currency }}</p>
        </div>
        <div class="flow-panel p-5">
            <p class="text-xs font-semibold uppercase text-slate-500">{{ module_trans($module, 'type_equity') }}</p>
            <p class="mt-2 text-lg font-bold tabular-nums">{{ flowdesk_format_minor($bs['equity'] + $netIncome, $currency) }} {{ $currency }}</p>
        </div>
    </div>

    <div class="flow-panel overflow-x-auto p-0">
        <div class="border-b px-4 py-3 text-sm font-semibold">{{ module_trans($module, 'trial_balance') }}</div>
        <table class="min-w-full text-sm">
            <thead class="border-b bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-3">{{ module_trans($module, 'account_code') }}</th>
                    <th class="px-4 py-3">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-end">{{ module_trans($module, 'debit') }}</th>
                    <th class="px-4 py-3 text-end">{{ module_trans($module, 'credit') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y dark:divide-slate-700">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $row->code }}</td>
                        <td class="px-4 py-3">{{ $row->name }}</td>
                        <td class="px-4 py-3 text-end tabular-nums">{{ flowdesk_format_minor((int) $row->debit_minor, $currency) }}</td>
                        <td class="px-4 py-3 text-end tabular-nums">{{ flowdesk_format_minor((int) $row->credit_minor, $currency) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">{{ module_trans($module, 'reports_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="border-t font-semibold">
                    <tr>
                        <td class="px-4 py-3" colspan="2">{{ __('Total') }}</td>
                        <td class="px-4 py-3 text-end tabular-nums">{{ flowdesk_format_minor($totalDebit, $currency) }}</td>
                        <td class="px-4 py-3 text-end tabular-nums">{{ flowdesk_format_minor($totalCredit, $currency) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
