@php
    use App\Enums\InvoiceRecurrenceInterval;

    $recurrenceInvoice = $invoice ?? null;
    $isRecurrenceChild = $recurrenceInvoice && filled($recurrenceInvoice->recurrence_parent_id);
    $recurrenceActive = (bool) old('recurrence_active', $recurrenceInvoice?->recurrence_active ?? false);
    $recurrenceInterval = old('recurrence_interval', $recurrenceInvoice?->recurrence_interval?->value ?? InvoiceRecurrenceInterval::Month->value);
    $recurrenceMonths = (int) old('recurrence_every_months', $recurrenceInvoice?->recurrence_every_months ?? 2);
    $recurrenceEnds = old('recurrence_ends_on', $recurrenceInvoice?->recurrence_ends_on?->format('Y-m-d'));
@endphp

<x-flow.form-card tone="violet" icon="fa-solid fa-arrows-rotate" :title="__('invoice_recurrence.title')" :hint="__('invoice_recurrence.help')">
    @if ($isRecurrenceChild)
        <div class="rounded-xl border border-violet-200/80 bg-white/70 px-4 py-3 text-sm text-violet-950 dark:border-violet-900/40 dark:bg-violet-950/30 dark:text-violet-100">
            <p class="font-medium">{{ __('invoice_recurrence.child_notice') }}</p>
            @if ($recurrenceInvoice->recurrenceParent)
                <p class="mt-1">
                    <a href="{{ route('invoices.show', $recurrenceInvoice->recurrenceParent) }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                        {{ $recurrenceInvoice->recurrenceParent->number ?? __('Invoice') }}
                    </a>
                </p>
            @endif
        </div>
    @else
        <div
            class="space-y-3"
            x-data="{
                active: {{ $recurrenceActive ? 'true' : 'false' }},
                interval: @js($recurrenceInterval),
            }"
        >
            <label class="inline-flex items-start gap-3 text-sm text-slate-800 dark:text-slate-200">
                <input type="hidden" name="recurrence_active" value="0" />
                <input
                    type="checkbox"
                    name="recurrence_active"
                    value="1"
                    class="mt-0.5 rounded border-slate-300 text-indigo-600 dark:border-slate-600"
                    x-model="active"
                    @checked($recurrenceActive)
                />
                <span>
                    <span class="font-medium">{{ __('invoice_recurrence.enable') }}</span>
                    <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ __('invoice_recurrence.auto_send_help') }}</span>
                </span>
            </label>
            <div class="grid gap-4 sm:grid-cols-3" x-show="active" x-cloak>
                <div>
                    <x-input-label for="recurrence_interval" :value="__('invoice_recurrence.interval')" />
                    <select
                        id="recurrence_interval"
                        name="recurrence_interval"
                        class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        x-model="interval"
                    >
                        @foreach (InvoiceRecurrenceInterval::cases() as $intervalCase)
                            <option value="{{ $intervalCase->value }}" @selected($recurrenceInterval === $intervalCase->value)>
                                {{ $intervalCase === InvoiceRecurrenceInterval::Custom ? __('invoice_recurrence.custom') : $intervalCase->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('recurrence_interval')" />
                </div>
                <div x-show="interval === 'custom'" x-cloak>
                    <x-input-label for="recurrence_every_months" :value="__('invoice_recurrence.every_months')" />
                    <x-text-input
                        id="recurrence_every_months"
                        name="recurrence_every_months"
                        type="number"
                        min="1"
                        max="36"
                        class="mt-1 block w-full"
                        :value="$recurrenceMonths"
                    />
                    <x-input-error class="mt-2" :messages="$errors->get('recurrence_every_months')" />
                </div>
                <div>
                    <x-input-label for="recurrence_ends_on" :value="__('invoice_recurrence.ends_on')" />
                    <x-text-input id="recurrence_ends_on" name="recurrence_ends_on" type="date" class="mt-1 block w-full" :value="$recurrenceEnds" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('invoice_recurrence.ends_on_help') }}</p>
                    <x-input-error class="mt-2" :messages="$errors->get('recurrence_ends_on')" />
                </div>
            </div>
            @if ($recurrenceInvoice?->recurrence_next_on && $recurrenceInvoice->recurrence_active)
                <p class="text-sm text-slate-600 dark:text-slate-300" x-show="active">
                    {{ __('invoice_recurrence.next_on') }}:
                    <span class="font-medium tabular-nums">{{ $recurrenceInvoice->recurrence_next_on->format('Y-m-d') }}</span>
                </p>
            @endif
        </div>
    @endif
</x-flow.form-card>
