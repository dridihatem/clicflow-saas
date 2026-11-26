<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('hr_my_advances') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl w-full space-y-6 sm:px-6 lg:px-8">
            <x-flow.page-header :title="__('hr_my_advances')" :description="__('hr_my_advances_intro')" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="flow-panel p-6">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('hr_request_advance') }}</h3>
                <form method="POST" action="{{ route('hr.me.advances.store') }}" class="mt-4 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="amount" :value="__('Amount').' ('.$currency.')'" />
                        <x-text-input id="amount" name="amount" class="flowdesk-amount mt-1 block w-full" required :value="old('amount')" />
                    </div>
                    <div>
                        <x-input-label for="reason" :value="__('Reason')" />
                        <textarea id="reason" name="reason" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ old('reason') }}</textarea>
                    </div>
                    <x-primary-button type="submit">{{ __('Submit') }}</x-primary-button>
                </form>
            </div>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Date') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Amount') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                        @forelse ($advances as $advance)
                            <tr>
                                <td class="px-4 py-3 text-sm">{{ $advance->requested_on?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm tabular-nums">{{ flowdesk_format_minor((int) $advance->amount_minor, $advance->currency) }} {{ $advance->currency }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $advance->status->badgeClass() }}">{{ $advance->status->label() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('hr_no_advances') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
                <div class="p-4">{{ $advances->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
