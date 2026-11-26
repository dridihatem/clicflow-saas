@php
    $channels = \App\Enums\ClientCampaignChannel::cases();
    $campStatuses = \App\Enums\ClientCampaignStatus::cases();
@endphp
<div x-show="tab === 'campaigns'" x-cloak class="space-y-6" x-data="{ showAdd: false, editing: null }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_campaigns_heading') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('client_campaigns_intro') }}</p>
        </div>
        <x-primary-button type="button" class="!normal-case" x-on:click="showAdd = !showAdd; editing = null">
            <i class="fa-solid fa-plus me-1.5 text-xs" aria-hidden="true"></i>{{ __('client_campaign_add') }}
        </x-primary-button>
    </div>

    <div x-show="showAdd || editing" x-cloak class="flow-panel p-6">
        <h4 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="editing ? @js(__('client_campaign_edit')) : @js(__('client_campaign_add'))"></h4>
        <form method="POST" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
              :action="editing ? (@js(url('/clients/'.$client->id.'/campaigns')) + '/' + editing.id) : @js(route('clients.campaigns.store', $client))">
            @csrf
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
            <div class="sm:col-span-2">
                <x-input-label for="cc_name" :value="__('client_campaign_name')" />
                <x-text-input id="cc_name" name="name" type="text" class="mt-2 block w-full" required x-bind:value="editing ? editing.name : ''" />
            </div>
            <div>
                <x-input-label for="cc_channel" :value="__('client_campaign_channel')" />
                <select id="cc_channel" name="channel" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    @foreach ($channels as $ch)
                        <option value="{{ $ch->value }}" x-bind:selected="editing && editing.channel === @js($ch->value)">{{ $ch->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="cc_status" :value="__('Status')" />
                <select id="cc_status" name="status" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    @foreach ($campStatuses as $st)
                        <option value="{{ $st->value }}" x-bind:selected="editing && editing.status === @js($st->value)">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="cc_start" :value="__('client_campaign_starts')" />
                <x-text-input id="cc_start" name="starts_on" type="date" class="mt-2 block w-full" x-bind:value="editing ? (editing.starts_on || '') : ''" />
            </div>
            <div>
                <x-input-label for="cc_end" :value="__('client_campaign_ends')" />
                <x-text-input id="cc_end" name="ends_on" type="date" class="mt-2 block w-full" x-bind:value="editing ? (editing.ends_on || '') : ''" />
            </div>
            <div>
                <x-input-label for="cc_budget" :value="__('client_campaign_budget')" />
                <x-text-input id="cc_budget" name="budget" type="number" step="0.01" min="0" class="mt-2 block w-full" x-bind:value="editing ? (editing.budget || '') : ''" />
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label for="cc_desc" :value="__('client_campaign_description')" />
                <textarea id="cc_desc" name="description" rows="2" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" x-text="editing ? (editing.description || '') : ''"></textarea>
            </div>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-3">
                <x-primary-button type="submit" class="!normal-case">{{ __('Save') }}</x-primary-button>
                <x-secondary-button type="button" class="!normal-case" x-on:click="showAdd = false; editing = null">{{ __('Cancel') }}</x-secondary-button>
            </div>
        </form>
    </div>

    <div class="flow-panel overflow-x-auto p-0">
        <x-flow.table>
            <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-3 text-start">{{ __('client_campaign_name') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_campaign_channel') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_campaign_dates') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('Status') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_campaign_budget') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('portal_nav_prospects') }}</th>
                    <th class="px-3 py-3 text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/80 text-sm">
                @forelse ($clientCampaigns as $campaign)
                    @php
                        $edit = [
                            'id' => $campaign->id,
                            'name' => $campaign->name,
                            'channel' => $campaign->channel?->value,
                            'status' => $campaign->status?->value,
                            'starts_on' => $campaign->starts_on?->format('Y-m-d'),
                            'ends_on' => $campaign->ends_on?->format('Y-m-d'),
                            'budget' => $campaign->budget,
                            'description' => $campaign->description,
                        ];
                    @endphp
                    <tr>
                        <td class="px-3 py-2.5 font-medium">{{ $campaign->name }}</td>
                        <td class="px-3 py-2.5">{{ $campaign->channel?->label() }}</td>
                        <td class="px-3 py-2.5 tabular-nums text-slate-600">
                            {{ $campaign->starts_on?->format('d/m/Y') ?? '—' }}
                            @if ($campaign->ends_on) → {{ $campaign->ends_on->format('d/m/Y') }} @endif
                        </td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex rounded-md px-2 py-1 text-[11px] font-semibold {{ $campaign->status?->badgeClass() }}">{{ $campaign->status?->label() }}</span>
                        </td>
                        <td class="px-3 py-2.5 tabular-nums">{{ $campaign->budget !== null ? number_format((float) $campaign->budget, 2, ',', ' ') : '—' }}</td>
                        <td class="px-3 py-2.5 tabular-nums">{{ $campaign->prospects_count ?? $campaign->prospects()->count() }}</td>
                        <td class="px-3 py-2.5 text-end">
                            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-orange-700 hover:bg-orange-50 dark:text-orange-300" x-on:click='editing = @json($edit); showAdd = false'>{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('clients.campaigns.destroy', [$client, $campaign]) }}" class="inline" onsubmit="return confirm(@js(__('client_campaign_delete_confirm')))">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('client_campaigns_empty') }}</td></tr>
                @endforelse
            </tbody>
        </x-flow.table>
    </div>
</div>
