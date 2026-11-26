@php
    $bookStatuses = \App\Enums\ClientBookingStatus::cases();
    $importErrors = session('client_booking_import_errors', []);
@endphp
<div x-show="tab === 'bookings'" x-cloak class="space-y-6" x-data="{ showAdd: false, showImport: false, editing: null }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_bookings_heading') }}</h3>
            <p class="mt-1 text-xs text-slate-500">{{ __('client_bookings_intro') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-secondary-button type="button" class="!normal-case" x-on:click="showImport = !showImport; showAdd = false; editing = null">{{ __('client_prospect_import') }}</x-secondary-button>
            <x-primary-button type="button" class="!normal-case" x-on:click="showAdd = !showAdd; showImport = false; editing = null">{{ __('client_booking_add') }}</x-primary-button>
        </div>
    </div>

    @if ($importErrors)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <ul class="list-disc ps-5 text-xs">@foreach ($importErrors as $err)<li>{{ $err }}</li>@endforeach</ul>
        </div>
    @endif

    <div x-show="showImport" x-cloak class="flow-panel p-6">
        <p class="text-xs text-slate-500">{{ __('client_booking_import_help') }}</p>
        <form method="POST" action="{{ route('clients.bookings.import', $client) }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <input name="csv" type="file" accept=".csv,text/csv,text/plain" required class="text-sm file:me-3 file:rounded-lg file:border-0 file:bg-orange-600 file:px-3 file:py-2 file:text-white" />
            <x-primary-button type="submit" class="!normal-case">{{ __('client_prospect_import_submit') }}</x-primary-button>
        </form>
    </div>

    <div x-show="showAdd || editing" x-cloak class="flow-panel p-6">
        <form method="POST" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
              :action="editing ? (@js(url('/clients/'.$client->id.'/bookings')) + '/' + editing.id) : @js(route('clients.bookings.store', $client))">
            @csrf
            <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
            <div>
                <x-input-label for="cb_name" :value="__('client_booking_customer')" />
                <x-text-input id="cb_name" name="customer_name" type="text" class="mt-2 block w-full" required x-bind:value="editing ? editing.customer_name : ''" />
            </div>
            <div>
                <x-input-label for="cb_phone" :value="__('Phone')" />
                <x-text-input id="cb_phone" name="phone" type="text" class="mt-2 block w-full" x-bind:value="editing ? (editing.phone || '') : ''" />
            </div>
            <div>
                <x-input-label for="cb_email" :value="__('Email')" />
                <x-text-input id="cb_email" name="email" type="email" class="mt-2 block w-full" x-bind:value="editing ? (editing.email || '') : ''" />
            </div>
            <div>
                <x-input-label for="cb_service" :value="__('client_booking_service')" />
                <x-text-input id="cb_service" name="service" type="text" class="mt-2 block w-full" x-bind:value="editing ? (editing.service || '') : ''" />
            </div>
            <div>
                <x-input-label for="cb_start" :value="__('client_booking_when')" />
                <x-text-input id="cb_start" name="starts_at" type="datetime-local" class="mt-2 block w-full" x-bind:value="editing ? (editing.starts_at || '') : ''" />
            </div>
            <div>
                <x-input-label for="cb_status" :value="__('Status')" />
                <select id="cb_status" name="status" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    @foreach ($bookStatuses as $st)
                        <option value="{{ $st->value }}" x-bind:selected="editing && editing.status === @js($st->value)">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <x-input-label for="cb_notes" :value="__('client_booking_notes')" />
                <textarea id="cb_notes" name="notes" rows="2" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800" x-text="editing ? (editing.notes || '') : ''"></textarea>
            </div>
            <div class="flex gap-2">
                <x-primary-button type="submit" class="!normal-case">{{ __('Save') }}</x-primary-button>
                <x-secondary-button type="button" class="!normal-case" x-on:click="showAdd = false; editing = null">{{ __('Cancel') }}</x-secondary-button>
            </div>
        </form>
    </div>

    <div class="flow-panel overflow-x-auto p-0">
        <x-flow.table>
            <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80">
                <tr>
                    <th class="px-3 py-3 text-start">{{ __('client_booking_when') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_booking_customer') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('Phone') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_booking_service') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('client_booking_notes') }}</th>
                    <th class="px-3 py-3 text-start">{{ __('Status') }}</th>
                    <th class="px-3 py-3 text-end"><span class="sr-only">{{ __('Actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/80 text-sm">
                @forelse ($clientBookings as $booking)
                    @php
                        $edit = [
                            'id' => $booking->id,
                            'customer_name' => $booking->customer_name,
                            'phone' => $booking->phone,
                            'email' => $booking->email,
                            'service' => $booking->service,
                            'status' => $booking->status?->value,
                            'notes' => $booking->notes,
                            'starts_at' => $booking->starts_at?->format('Y-m-d\TH:i'),
                        ];
                    @endphp
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2.5 tabular-nums">{{ $booking->starts_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-3 py-2.5 font-medium">{{ $booking->customer_name }}</td>
                        <td class="px-3 py-2.5">
                            @if ($booking->telHref())
                                <a href="{{ $booking->telHref() }}" class="text-orange-600 hover:underline"><span class="flowdesk-ltr-num" dir="ltr">{{ $booking->phone }}</span></a>
                            @else — @endif
                        </td>
                        <td class="px-3 py-2.5">{{ $booking->service ?: '—' }}</td>
                        <td class="max-w-xs px-3 py-2.5 text-xs text-slate-600">{{ \Illuminate\Support\Str::limit($booking->notes, 80) ?: '—' }}</td>
                        <td class="px-3 py-2.5"><span class="inline-flex rounded-md px-2 py-1 text-[11px] font-semibold {{ $booking->status?->badgeClass() }}">{{ $booking->status?->label() }}</span></td>
                        <td class="px-3 py-2.5 text-end">
                            <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-orange-700 hover:bg-orange-50" x-on:click='editing = @json($edit); showAdd = false'>{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('clients.bookings.destroy', [$client, $booking]) }}" class="inline" onsubmit="return confirm(@js(__('client_booking_delete_confirm')))">
                                @csrf @method('DELETE')
                                <button type="submit" class="rounded-lg px-2 py-1 text-xs font-medium text-rose-700">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('client_bookings_empty') }}</td></tr>
                @endforelse
            </tbody>
        </x-flow.table>
    </div>
    @if (method_exists($clientBookings, 'hasPages') && $clientBookings->hasPages())
        <div>{{ $clientBookings->appends(['tab' => 'bookings'])->links() }}</div>
    @endif
</div>
