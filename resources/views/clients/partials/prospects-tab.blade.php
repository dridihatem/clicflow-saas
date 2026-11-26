@php
    $statuses = \App\Enums\ClientProspectStatus::cases();
    $importErrors = session('client_prospect_import_errors', []);
@endphp
<div
    x-show="tab === 'prospects'"
    x-cloak
    class="space-y-6"
    x-data="{
        showAdd: false,
        showImport: false,
        editing: null,
        startEdit(p) { this.editing = p; this.showAdd = false; }
    }"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_prospects_heading') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('client_prospects_intro') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-secondary-button type="button" class="!normal-case" x-on:click="showImport = !showImport; showAdd = false; editing = null">
                <i class="fa-solid fa-file-csv me-1.5 text-xs" aria-hidden="true"></i>
                {{ __('client_prospect_import') }}
            </x-secondary-button>
            <x-primary-button type="button" class="!normal-case" x-on:click="showAdd = !showAdd; showImport = false; editing = null">
                <i class="fa-solid fa-plus me-1.5 text-xs" aria-hidden="true"></i>
                {{ __('client_prospect_add') }}
            </x-primary-button>
        </div>
    </div>

    @if ($importErrors)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-semibold">{{ __('client_prospect_import_errors_heading') }}</p>
            <ul class="mt-2 list-disc space-y-0.5 ps-5 text-xs">
                @foreach ($importErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-show="showImport" x-cloak class="flow-panel p-6">
        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_prospect_import') }}</h4>
        <p class="mt-1 text-xs text-slate-500">{{ __('client_prospect_import_help') }}</p>
        <form method="POST" action="{{ route('clients.prospects.import', $client) }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-[16rem] flex-1">
                <x-input-label for="prospect_csv" :value="__('client_prospect_import_csv')" />
                <input id="prospect_csv" name="csv" type="file" accept=".csv,text/csv,text/plain" required class="mt-2 block w-full text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-orange-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-orange-700 dark:text-slate-200" />
                <x-input-error :messages="$errors->get('csv')" class="mt-2" />
            </div>
            <x-primary-button type="submit" class="!normal-case">{{ __('client_prospect_import_submit') }}</x-primary-button>
        </form>
    </div>

    <div x-show="showAdd || editing" x-cloak class="flow-panel p-6">
        <h4 class="text-sm font-semibold text-slate-900 dark:text-white" x-text="editing ? @js(__('client_prospect_edit')) : @js(__('client_prospect_add'))"></h4>
        <form
            method="POST"
            class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            :action="editing ? @js(url('/clients/'.$client->id.'/prospects')). '/' + editing.id : @js(route('clients.prospects.store', $client))"
        >
            @csrf
            <template x-if="editing">
                <input type="hidden" name="_method" value="PUT">
            </template>
            <div>
                <x-input-label for="cp_name" :value="__('client_prospect_name')" />
                <x-text-input id="cp_name" name="name" type="text" class="mt-2 block w-full" required x-bind:value="editing ? editing.name : ''" />
            </div>
            <div>
                <x-input-label for="cp_phone" :value="__('Phone')" />
                <x-text-input id="cp_phone" name="phone" type="text" class="mt-2 block w-full" x-bind:value="editing ? (editing.phone || '') : ''" />
            </div>
            <div>
                <x-input-label for="cp_email" :value="__('Email')" />
                <x-text-input id="cp_email" name="email" type="email" class="mt-2 block w-full" x-bind:value="editing ? (editing.email || '') : ''" />
            </div>
            <div>
                <x-input-label for="cp_campaign" :value="__('client_prospect_campaign')" />
                <x-text-input id="cp_campaign" name="campaign" type="text" class="mt-2 block w-full" x-bind:value="editing ? (editing.campaign || '') : ''" />
            </div>
            <div>
                <x-input-label for="cp_status" :value="__('Status')" />
                <select id="cp_status" name="status" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}" x-bind:selected="editing && editing.status === @js($st->value)">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="cp_captured" :value="__('client_prospect_date')" />
                <x-text-input id="cp_captured" name="captured_on" type="date" class="mt-2 block w-full" x-bind:value="editing ? (editing.captured_on || '') : @js(now()->toDateString())" />
            </div>
            <div>
                <x-input-label for="cp_rdv" :value="__('client_prospect_appointment')" />
                <x-text-input id="cp_rdv" name="appointment_at" type="datetime-local" class="mt-2 block w-full" x-bind:value="editing ? (editing.appointment_at || '') : ''" />
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label for="cp_comment" :value="__('client_prospect_comment')" />
                <textarea id="cp_comment" name="comment" rows="2" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" x-text="editing ? (editing.comment || '') : ''"></textarea>
            </div>
            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                <x-primary-button type="submit" class="!normal-case" x-text="editing ? @js(__('Save changes')) : @js(__('client_prospect_add'))"></x-primary-button>
                <x-secondary-button type="button" class="!normal-case" x-on:click="showAdd = false; editing = null">{{ __('Cancel') }}</x-secondary-button>
            </div>
        </form>
    </div>

    <div class="flow-panel overflow-x-auto p-0">
        <x-flow.table>
            <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                    <th class="px-3 py-3 text-start">{{ __('client_prospect_date') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_prospect_campaign') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_prospect_name') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('Phone') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_prospect_comment') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('Status') }}</th>
                    <th class="px-3 py-3 text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/80 text-sm text-slate-800 dark:divide-slate-700/80 dark:text-slate-100">
                @forelse ($clientProspects as $prospect)
                    @php
                        $editPayload = [
                            'id' => $prospect->id,
                            'name' => $prospect->name,
                            'phone' => $prospect->phone,
                            'email' => $prospect->email,
                            'campaign' => $prospect->campaign,
                            'status' => $prospect->status?->value,
                            'comment' => $prospect->comment,
                            'captured_on' => $prospect->captured_on?->format('Y-m-d'),
                            'appointment_at' => $prospect->appointment_at?->format('Y-m-d\TH:i'),
                        ];
                    @endphp
                    <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                        <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600 dark:text-slate-300">{{ $prospect->captured_on?->format('d-m-y') ?? $prospect->created_at?->format('d-m-y') }}</td>
                        <td class="px-3 py-2.5">{{ $prospect->campaign ?: '—' }}</td>
                        <td class="px-3 py-2.5 font-medium">{{ $prospect->name }}
                            @if ($prospect->isClientSourced())
                                <span class="ms-1 inline-flex rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-semibold text-orange-800">{{ __('portal_prospect_source_client') }}</span>
                            @endif
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
                        <td class="max-w-xs px-3 py-2.5 text-xs text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($prospect->comment, 80) ?: '—' }}</td>
                        <td class="px-3 py-2.5">
                            <span class="inline-flex w-full min-w-[7rem] items-center justify-center rounded-md px-2 py-1 text-center text-[11px] font-semibold leading-tight {{ $prospect->status?->badgeClass() }}">{{ $prospect->status?->label() }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-end">
                            <div class="inline-flex items-center gap-1">
                                <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-orange-700 hover:bg-orange-50 dark:text-orange-300 dark:hover:bg-orange-950/40" x-on:click='startEdit(@json($editPayload))'>{{ __('Edit') }}</button>
                                <form method="POST" action="{{ route('clients.prospects.destroy', [$client, $prospect]) }}" onsubmit="return confirm(@js(__('client_prospect_delete_confirm')))">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('client_prospects_empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-flow.table>
    </div>

    @if (method_exists($clientProspects, 'hasPages') && $clientProspects->hasPages())
        <div class="mt-4">{{ $clientProspects->appends(['tab' => 'prospects'])->links() }}</div>
    @endif
</div>
