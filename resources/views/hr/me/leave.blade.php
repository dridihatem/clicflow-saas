<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('hr_my_leave') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl w-full space-y-6 sm:px-6 lg:px-8">
            <x-flow.page-header :title="__('hr_my_leave')" :description="__('hr_my_leave_intro')" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="flow-panel p-6">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('hr_request_leave') }}</h3>
                <form method="POST" action="{{ route('hr.me.leave.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="leave_type_id" :value="__('hr_leave_type')" />
                        <select id="leave_type_id" name="leave_type_id" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                            @foreach ($leaveTypes as $type)
                                <option value="{{ $type->id }}" @selected(old('leave_type_id') === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="starts_on" :value="__('From')" />
                        <x-text-input id="starts_on" name="starts_on" type="date" class="mt-1 block w-full" required :value="old('starts_on')" />
                    </div>
                    <div>
                        <x-input-label for="ends_on" :value="__('To')" />
                        <x-text-input id="ends_on" name="ends_on" type="date" class="mt-1 block w-full" required :value="old('ends_on')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="reason" :value="__('Reason')" />
                        <textarea id="reason" name="reason" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ old('reason') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-primary-button type="submit">{{ __('Submit') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('hr_leave_type') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('From') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('To') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                        @forelse ($requests as $req)
                            <tr>
                                <td class="px-4 py-3 text-sm">{{ $req->leaveType?->name }}</td>
                                <td class="px-4 py-3 text-sm">{{ $req->starts_on?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm">{{ $req->ends_on?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $req->status->badgeClass() }}">{{ $req->status->label() }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('hr_no_leave_requests') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
                <div class="p-4">{{ $requests->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
