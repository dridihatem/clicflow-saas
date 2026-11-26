<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">{{ __('client_portal_panel_eyebrow') }}</p>
                <h2 class="mt-0.5 text-xl font-semibold text-slate-900 dark:text-white">{{ __('client_prospects_heading') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('portal_prospects_intro') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $prospects->total() }}</span>
                    {{ __('portal_prospects_listed') }}
                </p>
                <button type="button" class="cf-btn-primary" @click="window.dispatchEvent(new CustomEvent('portal-prospect-add'))">
                    <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i> {{ __('portal_prospect_add') }}
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-8" x-data="{ showAdd: {{ $errors->any() ? 'true' : 'false' }} }" x-on:portal-prospect-add.window="showAdd = true">
        <div class="mx-auto w-full max-w-12xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div x-show="showAdd" x-cloak class="flow-panel cf-card p-6" x-transition>
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('portal_prospect_add') }}</h3>
                    <button type="button" class="text-sm text-slate-500" @click="showAdd = false">{{ __('Cancel') }}</button>
                </div>
                <form method="POST" action="{{ route('portal.prospects.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf
                    <div>
                        <x-input-label for="add_campaign" :value="__('client_prospect_campaign')" />
                        <select id="add_campaign" name="campaign" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                            <option value="">—</option>
                            @foreach (($campaignRecords ?? $campaigns) as $c)
                                <option value="{{ is_object($c) ? $c->name : $c }}">{{ is_object($c) ? $c->name : $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="add_name" :value="__('client_prospect_name')" />
                        <x-text-input id="add_name" name="name" type="text" class="mt-1.5 block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="add_phone" :value="__('Phone')" />
                        <x-text-input id="add_phone" name="phone" type="text" class="mt-1.5 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="add_email" :value="__('Email')" />
                        <x-text-input id="add_email" name="email" type="email" class="mt-1.5 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="add_status" :value="__('Status')" />
                        <select id="add_status" name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                            @foreach ($statuses as $st)
                                <option value="{{ $st->value }}">{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="add_rdv" :value="__('client_prospect_appointment')" />
                        <x-text-input id="add_rdv" name="appointment_at" type="datetime-local" class="mt-1.5 block w-full" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <x-input-label for="add_comment" :value="__('client_prospect_comments')" />
                        <textarea id="add_comment" name="comment" rows="2" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800"></textarea>
                    </div>
                    <div>
                        <x-primary-button type="submit" class="!normal-case">{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <form method="GET" action="{{ route('portal.prospects.index') }}" class="flow-panel flex flex-wrap items-end gap-3 p-4">
                <div class="min-w-[12rem] flex-1">
                    <x-input-label for="q" :value="__('Search')" />
                    <x-text-input id="q" name="q" type="search" class="mt-1.5 block w-full" :value="$filters['q']" :placeholder="__('portal_prospects_search_placeholder')" />
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
                <div class="min-w-[12rem] flex-1">
                    <x-input-label for="campaign" :value="__('client_prospect_campaign')" />
                    <select id="campaign" name="campaign" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <option value="">{{ __('portal_prospects_all_campaigns') }}</option>
                        @foreach ($campaigns as $campaign)
                            <option value="{{ $campaign }}" @selected($filters['campaign'] === $campaign)>{{ $campaign }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit" class="!normal-case">{{ __('Filter') }}</x-primary-button>
                @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['campaign'] !== '')
                    <a href="{{ route('portal.prospects.index') }}" class="text-sm font-medium text-orange-600 hover:underline dark:text-orange-400">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="flow-panel overflow-x-auto p-0">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-start text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-3 text-start">{{ __('client_prospect_date') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('client_prospect_campaign') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('client_prospect_name') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('Phone') }}</th>
                            <th class="px-3 py-3 text-start">{{ __('client_prospect_comments') }}</th>
                            <th class="min-w-[9rem] px-0 py-3 text-start">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($prospects as $prospect)
                            <tr
                                class="align-top text-slate-800 dark:text-slate-100"
                                x-data="{
                                    open: false,
                                    status: @js($prospect->status?->value),
                                    showRdv: @js($prospect->status === \App\Enums\ClientProspectStatus::RdvProgramme)
                                }"
                            >
                                <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600 dark:text-slate-300">
                                    {{ $prospect->captured_on?->format('d-m-y') ?? $prospect->created_at?->format('d-m-y') }}
                                </td>
                                <td class="px-3 py-2.5">{{ $prospect->campaignRelation?->name ?? $prospect->campaign ?: '—' }}</td>
                                <td class="px-3 py-2.5 font-medium">
                                    <a href="{{ route('portal.prospects.show', $prospect) }}" class="hover:text-orange-700 dark:hover:text-orange-300">{{ $prospect->name }}</a>
                                    @if ($prospect->isClientSourced())
                                        <span class="ms-1 inline-flex rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold text-orange-800">{{ __('portal_prospect_source_client') }}</span>
                                    @endif
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        <a href="{{ route('portal.prospects.show', $prospect) }}" class="text-[11px] font-medium text-slate-500 hover:text-orange-600 hover:underline">{{ __('portal_prospect_view') }}</a>
                                        <a href="{{ route('portal.assistant.index', ['prospect' => $prospect->id]) }}" class="inline-flex items-center gap-1 text-[11px] font-medium text-orange-600 hover:underline dark:text-orange-400">
                                            <i class="fa-solid fa-wand-magic-sparkles text-[10px]" aria-hidden="true"></i>
                                            {{ __('portal_ai_ask') }}
                                        </a>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    @if ($prospect->telHref())
                                        <a href="{{ $prospect->telHref() }}" class="inline-flex items-center gap-1.5 text-orange-600 hover:underline dark:text-orange-400">
                                            <i class="fa-solid fa-phone text-[10px]" aria-hidden="true"></i>
                                            <span class="flowdesk-ltr-num" dir="ltr">{{ $prospect->phone }}</span>
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    <p class="max-w-md text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $prospect->comment ?: '—' }}</p>
                                    <button type="button" class="mt-1 text-[11px] font-medium text-orange-600 hover:underline dark:text-orange-400" x-on:click="open = !open">
                                        {{ __('client_prospect_add_comment') }}
                                    </button>
                                    <div x-show="open" x-cloak class="mt-2 space-y-2">
                                        <form method="POST" action="{{ route('portal.prospects.comment', $prospect) }}" class="flex flex-col gap-2 sm:flex-row">
                                            @csrf
                                            <textarea name="comment" rows="2" required maxlength="5000" class="block w-full rounded-lg border-slate-300 text-xs shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="{{ __('client_prospect_comment_placeholder') }}"></textarea>
                                            <x-primary-button type="submit" class="!normal-case shrink-0 self-end">{{ __('Save') }}</x-primary-button>
                                        </form>
                                        @if ($prospect->relationLoaded('comments') && $prospect->comments->isNotEmpty())
                                            <ul class="space-y-1 border-s-2 border-orange-200 ps-3 dark:border-orange-900">
                                                @foreach ($prospect->comments as $entry)
                                                    <li class="text-[11px] text-slate-500 dark:text-slate-400">
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
                                    <form method="POST" action="{{ route('portal.prospects.status', $prospect) }}" class="h-full">
                                        @csrf
                                        <select
                                            name="status"
                                            class="h-full min-h-[3rem] w-full cursor-pointer border-0 px-2 py-3 text-center text-[11px] font-semibold leading-tight focus:ring-2 focus:ring-orange-500 {{ $prospect->status?->badgeClass() }}"
                                            x-model="status"
                                            x-on:change="showRdv = status === 'rdv_programme'; if (!showRdv) $el.form.submit()"
                                            aria-label="{{ __('Status') }}"
                                        >
                                            @foreach ($statuses as $st)
                                                <option value="{{ $st->value }}" class="bg-white text-slate-900" @selected($prospect->status === $st)>{{ $st->label() }}</option>
                                            @endforeach
                                        </select>
                                        <div x-show="showRdv && status === 'rdv_programme'" x-cloak class="bg-amber-50 px-2 py-2 dark:bg-amber-950/40">
                                            <label class="block text-[10px] font-semibold uppercase text-amber-900 dark:text-amber-200">{{ __('client_prospect_appointment') }}</label>
                                            <input type="datetime-local" name="appointment_at" value="{{ $prospect->appointment_at?->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded border-amber-300 text-xs dark:border-amber-700 dark:bg-slate-900" />
                                            <button type="submit" class="mt-1 w-full rounded bg-amber-500 px-2 py-1 text-[11px] font-semibold text-white hover:bg-amber-600">{{ __('Save') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-16 text-center text-sm text-slate-500">{{ __('portal_prospects_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($prospects->hasPages())
                <div>{{ $prospects->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
