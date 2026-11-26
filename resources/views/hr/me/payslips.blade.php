<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('hr_my_payslips') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full space-y-6 sm:px-6 lg:px-8">
            <x-flow.page-header :title="__('hr_my_payslips')" :description="__('hr_my_payslips_intro')" />

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Period') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Net') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                        @forelse ($payslips as $payslip)
                            <tr>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-medium">{{ $payslip->payrollRun?->title }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $payslip->payrollRun?->period_start?->format('Y-m-d') }} → {{ $payslip->payrollRun?->period_end?->format('Y-m-d') }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-end text-sm tabular-nums font-medium">
                                    {{ flowdesk_format_minor((int) $payslip->net_minor, $payslip->currency) }} {{ $payslip->currency }}
                                </td>
                                <td class="px-4 py-3 text-end">
                                    <a
                                        href="{{ route('hr.me.payslips.pdf', $payslip) }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                        title="{{ __('hr_download_payslip') }}"
                                        aria-label="{{ __('hr_download_payslip') }}"
                                    >
                                        <i class="fa-solid fa-file-pdf text-sm" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('hr_no_payslips_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
                <div class="p-4">{{ $payslips->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
