<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">{{ __('client_portal_panel_eyebrow') }}</p>
                <h2 class="mt-0.5 text-xl font-semibold text-slate-900 dark:text-white">{{ __('portal_nav_bookings') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('portal_bookings_intro') }}</p>
            </div>
            <p class="text-sm text-slate-500">
                <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $bookings->total() }}</span>
                {{ __('portal_bookings_listed') }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full max-w-12xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('portal.bookings.index') }}" class="flow-panel flex flex-wrap items-end gap-3 p-4">
                <div class="min-w-[12rem] flex-1">
                    <x-input-label for="q" :value="__('Search')" />
                    <x-text-input id="q" name="q" type="search" class="mt-1.5 block w-full" :value="$filters['q']" :placeholder="__('portal_bookings_search_placeholder')" />
                </div>
                <div class="w-44">
                    <x-input-label for="status" :value="__('Status')" />
                    <select id="status" name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">{{ __('portal_prospects_all_statuses') }}</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st->value }}" @selected($filters['status'] === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit" class="!normal-case">{{ __('Filter') }}</x-primary-button>
                @if ($filters['q'] !== '' || $filters['status'] !== '')
                    <a href="{{ route('portal.bookings.index') }}" class="text-sm font-medium text-orange-600 hover:underline dark:text-orange-400">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="flow-panel overflow-x-auto p-0">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-start text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-3 text-start">{{ __('client_booking_when') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('client_booking_customer') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('Phone') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('Email') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('client_booking_service') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('client_booking_notes') }}</th>
                            <th class="min-w-[9rem] px-0 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($bookings as $booking)
                            <tr class="align-top text-slate-800 dark:text-slate-100" x-data="{ open: false }">
                                <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600 dark:text-slate-300">{{ $booking->starts_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="px-3 py-2.5 font-medium">{{ $booking->customer_name }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    @if ($booking->telHref())
                                        <a href="{{ $booking->telHref() }}" class="inline-flex items-center gap-1.5 text-orange-600 hover:underline dark:text-orange-400">
                                            <i class="fa-solid fa-phone text-[10px]" aria-hidden="true"></i>
                                            <span class="flowdesk-ltr-num" dir="ltr">{{ $booking->phone }}</span>
                                        </a>
                                    @else — @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">{{ $booking->email ?: '—' }}</td>
                                <td class="px-3 py-2.5">{{ $booking->service ?: '—' }}</td>
                                <td class="px-3 py-2.5">
                                    <p class="max-w-md text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $booking->notes ?: '—' }}</p>
                                    <button type="button" class="mt-1 text-[11px] font-medium text-orange-600 hover:underline dark:text-orange-400" x-on:click="open = !open">{{ __('client_booking_add_comment') }}</button>
                                    <div x-show="open" x-cloak class="mt-2 space-y-2">
                                        <form method="POST" action="{{ route('portal.bookings.comment', $booking) }}" class="flex flex-col gap-2 sm:flex-row">
                                            @csrf
                                            <textarea name="notes" rows="2" required maxlength="5000" class="block w-full rounded-lg border-slate-300 text-xs shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                                            <x-primary-button type="submit" class="!normal-case shrink-0 self-end">{{ __('Save') }}</x-primary-button>
                                        </form>
                                        @if ($booking->relationLoaded('comments') && $booking->comments->isNotEmpty())
                                            <ul class="space-y-1 border-s-2 border-orange-200 ps-3 dark:border-orange-900">
                                                @foreach ($booking->comments as $entry)
                                                    <li class="text-[11px] text-slate-500">
                                                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ $entry->author?->name ?? __('Team') }}</span>
                                                        <span class="tabular-nums"> · {{ $entry->created_at?->format('d/m/Y H:i') }}</span>
                                                        <span class="mt-0.5 block text-slate-700 dark:text-slate-300">{{ $entry->body }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-0 py-0">
                                    <form method="POST" action="{{ route('portal.bookings.status', $booking) }}" class="h-full">
                                        @csrf
                                        <select name="status" class="h-full min-h-[3rem] w-full cursor-pointer border-0 px-2 py-3 text-center text-[11px] font-semibold leading-tight focus:ring-2 focus:ring-orange-500 {{ $booking->status?->badgeClass() }}" x-on:change="$el.form.submit()" aria-label="{{ __('Status') }}">
                                            @foreach ($statuses as $st)
                                                <option value="{{ $st->value }}" class="bg-white text-slate-900" @selected($booking->status === $st)>{{ $st->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center text-sm text-slate-500">{{ __('portal_bookings_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($bookings->hasPages())
                <div>{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
