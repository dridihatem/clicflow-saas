<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">{{ __('client_portal_panel_eyebrow') }}</p>
                <h2 class="mt-0.5 text-xl font-semibold text-slate-900 dark:text-white">{{ __('portal_nav_campaigns') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('portal_campaigns_intro') }}</p>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $campaigns->count() }}</span>
                {{ __('portal_campaigns_listed') }}
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full max-w-12xl space-y-5 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($campaigns as $campaign)
                    @php
                        $counts = $statusCounts->get($campaign->id, collect());
                        $byStatus = $counts->keyBy('status');
                    @endphp
                    <article class="flow-panel flex flex-col p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-semibold text-slate-900 dark:text-white">{{ $campaign->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $campaign->channel?->label() }}</p>
                            </div>
                            <span class="inline-flex shrink-0 rounded-md px-2 py-1 text-[11px] font-semibold {{ $campaign->status?->badgeClass() }}">{{ $campaign->status?->label() }}</span>
                        </div>
                        <p class="mt-3 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                            {{ $campaign->starts_on?->format('d/m/Y') ?? '—' }}
                            @if ($campaign->ends_on) → {{ $campaign->ends_on->format('d/m/Y') }} @endif
                        </p>
                        @if ($campaign->budget !== null)
                            <p class="mt-1 text-xs text-slate-500">{{ __('client_campaign_budget') }} : {{ number_format((float) $campaign->budget, 2, ',', ' ') }} {{ $campaign->currency }}</p>
                        @endif
                        @if ($campaign->description)
                            <p class="mt-2 line-clamp-3 text-xs text-slate-500">{{ $campaign->description }}</p>
                        @endif
                        <p class="mt-4 text-sm font-medium text-slate-900 dark:text-white">
                            <span class="tabular-nums">{{ $campaign->prospects_count }}</span>
                            {{ __('portal_campaign_prospects_label') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($statuses as $st)
                                @php $n = (int) ($byStatus->get($st->value)->aggregate ?? 0); @endphp
                                @if ($n > 0)
                                    <a href="{{ route('portal.prospects.index', ['campaign_id' => $campaign->id, 'status' => $st->value]) }}" class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $st->badgeClass() }}">
                                        {{ $st->label() }} <span class="tabular-nums">{{ $n }}</span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                        <a href="{{ route('portal.prospects.index', ['campaign_id' => $campaign->id]) }}" class="mt-4 text-xs font-medium text-orange-600 hover:underline dark:text-orange-400">{{ __('portal_campaigns_see_prospects') }}</a>
                    </article>
                @empty
                    <div class="flow-panel col-span-full px-4 py-16 text-center text-sm text-slate-500">{{ __('portal_campaigns_empty') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
