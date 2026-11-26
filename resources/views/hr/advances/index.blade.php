<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('hr_salary_advances') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full space-y-6 sm:px-6 lg:px-8">
            <x-flow.page-header :title="__('hr_salary_advances')" :description="__('hr_advances_intro')" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="flow-panel p-6 lg:col-span-1">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('hr_new_advance') }}</h3>
                    <form method="POST" action="{{ route('hr.advances.store') }}" class="mt-4 space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="advance_employee" :value="__('Employee')" />
                            <select id="advance_employee" name="employee_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected(old('employee_id') === $employee->id)>{{ $employee->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="advance_amount" :value="__('Amount').' ('.$currency.')'" />
                            <x-text-input id="advance_amount" name="amount" class="flowdesk-amount mt-1 block w-full" required :value="old('amount')" />
                        </div>
                        <div>
                            <x-input-label for="advance_date" :value="__('Date')" />
                            <x-text-input id="advance_date" name="requested_on" type="date" class="mt-1 block w-full" required :value="old('requested_on', now()->toDateString())" />
                        </div>
                        <div>
                            <x-input-label for="advance_reason" :value="__('Reason')" />
                            <textarea id="advance_reason" name="reason" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ old('reason') }}</textarea>
                        </div>
                        <x-primary-button type="submit">{{ __('Create') }}</x-primary-button>
                    </form>
                </div>

                <div class="flow-panel overflow-hidden p-0 lg:col-span-2">
                    <x-flow.table>
                        <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('Employee') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Amount') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Date') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                            @forelse ($advances as $advance)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $advance->employee?->full_name }}</td>
                                    <td class="px-4 py-3 text-sm tabular-nums">{{ flowdesk_format_minor((int) $advance->amount_minor, $advance->currency) }} {{ $advance->currency }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $advance->requested_on?->format('Y-m-d') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $advance->status->badgeClass() }}">{{ $advance->status->label() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        @if ($advance->status === \App\Enums\HrSalaryAdvanceStatus::Pending)
                                            <div class="inline-flex gap-1">
                                                <form method="POST" action="{{ route('hr.advances.approve', $advance) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-emerald-600 shadow-sm hover:border-emerald-200 dark:border-slate-600 dark:bg-slate-800" title="{{ __('Approve') }}" aria-label="{{ __('Approve') }}">
                                                        <i class="fa-solid fa-check text-sm" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('hr.advances.reject', $advance) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-rose-600 shadow-sm hover:border-rose-200 dark:border-slate-600 dark:bg-slate-800" title="{{ __('Reject') }}" aria-label="{{ __('Reject') }}">
                                                        <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('hr_no_advances') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-flow.table>
                    <div class="p-4">{{ $advances->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
