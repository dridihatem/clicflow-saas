@php
    $created = is_array($createdShare ?? null) ? $createdShare : null;
    $createdLink = $created ? $file->shareLinks->firstWhere('id', $created['id']) : null;
    $sendId = (string) request()->query('send', '');
    $stateStyles = [
        'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'expired' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'exhausted' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'revoked' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];
    $closeUrl = route($rp.'index', request()->except('links', 'send'));
@endphp
<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-8 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="vault-links-title">
    <div class="relative w-full max-w-3xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-700">
            <div class="flex min-w-0 items-center gap-3">
                <x-cf-icon name="link" variant="tile" tone="orange" size="sm" />
                <div class="min-w-0">
                    <h3 id="vault-links-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ __('vault_links_title') }}</h3>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $file->original_name }} · {{ $file->humanSize() }}</p>
                </div>
            </div>
            <a href="{{ $closeUrl }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" aria-label="{{ __('Close') }}"><x-cf-icon name="xmark" size="xs" /></a>
        </div>

        <div class="space-y-5 px-6 py-5">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">@foreach ($errors->all() as $err)<p>{{ $err }}</p>@endforeach</div>
            @endif

            @if ($createdLink)
                <div class="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/20" x-data="{ copied: false }">
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">{{ __('vault_link_created_title') }}</p>
                    <div class="flex gap-2">
                        <input type="text" readonly value="{{ $createdLink->publicUrl() }}" class="flow-input block w-full font-mono text-xs" onclick="this.select()">
                        <button type="button" @click="navigator.clipboard.writeText(@js($createdLink->publicUrl())); copied = true; setTimeout(() => copied = false, 1500)" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-500"><x-cf-icon name="copy" size="xs" /><span x-text="copied ? @js(__('vault_copied')) : @js(__('vault_copy'))">{{ __('vault_copy') }}</span></button>
                    </div>
                    @if (! empty($created['password']))
                        <p class="text-sm text-slate-700 dark:text-slate-200">{{ __('vault_link_password') }} : <code class="rounded bg-white px-2 py-0.5 font-mono font-semibold text-slate-900 ring-1 ring-slate-200">{{ $created['password'] }}</code></p>
                        <p class="text-[11px] text-amber-700">{{ __('vault_link_password_once') }}</p>
                    @endif
                    @include('vault.partials.share-send-form', ['link' => $createdLink, 'prefillPassword' => $created['password'] ?? null])
                </div>
            @endif

            <form method="POST" action="{{ route('vault.links.store', $file->id) }}" class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"
                  x-data="{ expiry: @js(old('expiry', '7')), pw: @js(old('password', '')), gen() { const c = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; let s = ''; const a = new Uint32Array(12); crypto.getRandomValues(a); a.forEach(v => s += c[v % c.length]); this.pw = s; } }">
                @csrf
                <p class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('vault_link_new') }}</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="link_password" :value="__('vault_link_password_optional')" class="!text-xs" />
                        <div class="mt-1 flex gap-2">
                            <input id="link_password" name="password" type="text" x-model="pw" autocomplete="off" minlength="4" maxlength="100" class="flow-input block w-full" placeholder="{{ __('vault_link_password_placeholder') }}">
                            <button type="button" @click="gen()" class="shrink-0 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 hover:border-orange-300 hover:text-orange-600">{{ __('vault_link_generate') }}</button>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="link_expiry" :value="__('vault_link_expiry')" class="!text-xs" />
                        <select id="link_expiry" name="expiry" x-model="expiry" class="flow-input mt-1 block w-full">
                            @foreach (['1' => 'vault_link_expiry_1', '7' => 'vault_link_expiry_7', '30' => 'vault_link_expiry_30', '90' => 'vault_link_expiry_90', 'never' => 'vault_link_expiry_never', 'custom' => 'vault_link_expiry_custom'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('expiry', '7') === $v)>{{ __($l) }}</option>
                            @endforeach
                        </select>
                        <input type="date" name="expires_on" x-show="expiry === 'custom'" @if (old('expiry') !== 'custom') style="display:none" @endif value="{{ old('expires_on') }}" min="{{ now()->addDay()->format('Y-m-d') }}" class="flow-input mt-2 block w-full">
                    </div>
                    <div>
                        <x-input-label for="link_max" :value="__('vault_link_max_downloads')" class="!text-xs" />
                        <x-text-input id="link_max" name="max_downloads" type="number" min="1" max="10000" :value="old('max_downloads')" class="mt-1 block w-full" placeholder="{{ __('vault_link_unlimited') }}" />
                    </div>
                    <div>
                        <x-input-label for="link_label" :value="__('vault_link_label')" class="!text-xs" />
                        <x-text-input id="link_label" name="label" type="text" maxlength="120" :value="old('label')" class="mt-1 block w-full" placeholder="{{ __('vault_link_label_placeholder') }}" />
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="hidden" name="allow_preview" value="0">
                        <input type="checkbox" name="allow_preview" value="1" @checked(old('allow_preview', '1')) class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                        {{ __('vault_link_allow_preview') }}
                    </label>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-500"><x-cf-icon name="link" size="xs" />{{ __('vault_link_create') }}</button>
                </div>
            </form>

            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('vault_links_existing') }} ({{ $file->shareLinks->count() }})</p>
                @forelse ($file->shareLinks as $link)
                    @php $state = $link->state(); @endphp
                    <div class="mb-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700" x-data="{ copied: false }">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ $link->label ?: __('vault_link_default_label') }}
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $stateStyles[$state] }}">{{ __('vault_link_state_'.$state) }}</span>
                                    @if ($link->hasPassword())<span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"><x-cf-icon name="lock" size="xs" />{{ __('vault_link_protected') }}</span>@endif
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    {{ $link->expires_at ? __('vault_link_expires_at', ['date' => $link->expires_at->format('d/m/Y H:i')]) : __('vault_link_no_expiry') }}
                                    · {{ __('vault_link_downloads', ['count' => $link->download_count]) }}@if ($link->max_downloads) / {{ $link->max_downloads }}@endif
                                    · {{ __('vault_link_views', ['count' => $link->view_count]) }}
                                    · {{ $link->creator?->name }} · {{ $link->created_at?->format('d/m/Y') }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                @if ($state === 'active')
                                    <button type="button" @click="navigator.clipboard.writeText(@js($link->publicUrl())); copied = true; setTimeout(() => copied = false, 1500)" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-orange-300 hover:text-orange-600"><x-cf-icon name="copy" size="xs" /><span x-text="copied ? @js(__('vault_copied')) : @js(__('vault_copy'))">{{ __('vault_copy') }}</span></button>
                                    <a href="{{ route($rp.'index', ['links' => $file->id, 'send' => $link->id]) }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-orange-300 hover:text-orange-600"><x-cf-icon name="paper-plane" size="xs" />{{ __('vault_send_short') }}</a>
                                    <form method="POST" action="{{ route('vault.links.destroy', [$file->id, $link->id]) }}" onsubmit="return confirm(@js(__('vault_link_revoke_confirm')))">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">{{ __('vault_link_revoke') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if ($sendId === (string) $link->id && $state === 'active' && ! ($createdLink && $createdLink->id === $link->id))
                            <div class="mt-3">@include('vault.partials.share-send-form', ['link' => $link, 'prefillPassword' => null])</div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">{{ __('vault_links_none') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
