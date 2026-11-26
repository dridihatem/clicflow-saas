@php
    $prefillPassword = $prefillPassword ?? null;
    $suggest = collect($clients ?? [])->pluck('email')->filter();
    if ($file->client?->email) { $suggest = $suggest->prepend($file->client->email); }
    $suggest = $suggest->unique()->values();
    $listId = 'vault-send-emails-'.$link->id;
@endphp
<form method="POST" action="{{ route('vault.links.email', [$file->id, $link->id]) }}" class="space-y-3 rounded-xl border border-orange-200 bg-orange-50/40 p-4 dark:border-orange-900/50 dark:bg-orange-950/10">
    @csrf
    <div class="flex items-center gap-2">
        <x-cf-icon name="paper-plane" variant="tile" tone="orange" size="sm" />
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('vault_send_title') }}</p>
    </div>
    <div>
        <x-input-label :for="'send_to_'.$link->id" :value="__('vault_send_recipients')" class="!text-xs" />
        <x-text-input :id="'send_to_'.$link->id" name="recipients" type="text" :list="$listId" :value="old('recipients', $file->client?->email)" class="mt-1 block w-full" placeholder="client@exemple.com, autre@exemple.com" required />
        <datalist id="{{ $listId }}">@foreach ($suggest as $em)<option value="{{ $em }}">@endforeach</datalist>
        <p class="mt-1 text-[11px] text-slate-500">{{ __('vault_send_recipients_help') }}</p>
    </div>
    <div>
        <x-input-label :for="'send_msg_'.$link->id" :value="__('vault_send_message')" class="!text-xs" />
        <textarea id="send_msg_{{ $link->id }}" name="message" rows="2" maxlength="2000" class="flow-input mt-1 block w-full" placeholder="{{ __('vault_send_message_placeholder') }}">{{ old('message') }}</textarea>
    </div>
    @if ($link->hasPassword())
        <div x-data="{ inc: true }" class="space-y-2 rounded-lg bg-white/80 p-3 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                <input type="hidden" name="include_password" value="0">
                <input type="checkbox" name="include_password" value="1" x-model="inc" checked class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                {{ __('vault_send_include_password') }}
            </label>
            <div x-show="inc">
                @if ($prefillPassword)
                    <input type="hidden" name="link_password" value="{{ $prefillPassword }}">
                    <p class="text-xs text-slate-500">{{ __('vault_send_password_ready') }}</p>
                @else
                    <x-input-label :for="'send_pw_'.$link->id" :value="__('vault_send_retype_password')" class="!text-xs" />
                    <x-text-input :id="'send_pw_'.$link->id" name="link_password" type="password" autocomplete="new-password" class="mt-1 block w-full" />
                @endif
            </div>
            <p class="flex items-start gap-1.5 text-[11px] text-amber-700 dark:text-amber-300"><x-cf-icon name="info" size="xs" />{{ __('vault_send_security_hint') }}</p>
        </div>
    @endif
    <div class="flex justify-end">
        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-500">
            <x-cf-icon name="paper-plane" size="xs" />
            {{ __('vault_send_submit') }}
        </button>
    </div>
</form>
