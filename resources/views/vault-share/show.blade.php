@php
    $company = $share?->company;
    $logo = asset('images/logo-clicflow-light.png');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ $file ? $file->original_name : __('vault_public_unavailable_title') }} — ClicFlow</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-gradient-to-br from-orange-50 via-white to-sky-50 font-sans text-slate-900 antialiased">
    <div class="h-1.5 w-full bg-gradient-to-r from-[#30C0F0] via-[#EA580C] to-[#D81854]"></div>
    <div class="flex min-h-[calc(100vh-6px)] flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex justify-center">
                <span class="inline-flex items-center rounded-2xl bg-white px-5 py-3 shadow-lg shadow-orange-900/5 ring-1 ring-slate-200/80">
                    <img src="{{ $logo }}" alt="ClicFlow" class="h-9 w-auto">
                </span>
            </div>

            <div class="rounded-3xl border border-slate-200/80 bg-white/95 p-8 shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5">
                @if ($state === 'unavailable')
                    <div class="flex justify-center"><x-cf-icon name="link" variant="tile" tone="slate" size="md" /></div>
                    <h1 class="mt-4 text-center text-xl font-bold tracking-tight">{{ __('vault_public_unavailable_title') }}</h1>
                    <p class="mt-2 text-center text-sm text-slate-500">{{ __('vault_public_unavailable_help') }}</p>
                @else
                    <div class="flex justify-center">
                        <x-cf-icon :name="$state === 'password' ? 'lock' : 'vault'" variant="tile" tone="orange" size="md" />
                    </div>
                    <h1 class="mt-4 text-center text-xl font-bold tracking-tight">{{ $state === 'password' ? __('vault_public_protected_title') : __('vault_public_title') }}</h1>
                    @if ($company?->name)
                        <p class="mt-1 text-center text-sm text-slate-500">{{ __('vault_public_from', ['company' => $company->name]) }}</p>
                    @endif

                    <div class="mt-6 flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4">
                        <x-cf-icon :name="$file->isPdf() ? 'file-pdf' : ($file->isImage() ? 'image' : 'file-lines')" variant="tile" :tone="$file->isPdf() ? 'rose' : 'blue'" size="sm" />
                        <div class="min-w-0">
                            <p class="break-all text-sm font-semibold text-slate-900">{{ $file->original_name }}</p>
                            <p class="text-xs text-slate-500">{{ strtoupper((string) $file->extension) }} · {{ $file->humanSize() }}@if ($share->expires_at) · {{ __('vault_public_expires', ['date' => $share->expires_at->format('d/m/Y')]) }}@endif</p>
                        </div>
                    </div>

                    @if ($state === 'password')
                        <form method="POST" action="{{ route('vault-share.unlock', $share->token) }}" class="mt-6 space-y-4">
                            @csrf
                            <div>
                                <label for="password" class="mb-1 block text-sm font-medium text-slate-700">{{ __('vault_public_password_prompt') }}</label>
                                <input id="password" type="password" name="password" required autocomplete="off" autofocus @disabled(($lockedFor ?? 0) > 0)
                                       class="block w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                                @error('password')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                                @if (($lockedFor ?? 0) > 0 && ! $errors->has('password'))
                                    <p class="mt-2 text-sm text-rose-600">{{ __('vault_public_too_many', ['minutes' => (int) ceil($lockedFor / 60)]) }}</p>
                                @endif
                            </div>
                            <button type="submit" @disabled(($lockedFor ?? 0) > 0) class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-orange-600/20 hover:bg-orange-500 disabled:opacity-50">
                                <x-cf-icon name="lock" size="xs" />{{ __('vault_public_unlock') }}
                            </button>
                        </form>
                    @else
                        @if ($share->allow_preview && $file->isPreviewable())
                            <div class="mt-5 overflow-hidden rounded-2xl ring-1 ring-slate-200">
                                @if ($file->isImage())
                                    <img src="{{ route('vault-share.preview', $share->token) }}" alt="{{ $file->original_name }}" class="max-h-80 w-full bg-slate-50 object-contain">
                                @else
                                    <a href="{{ route('vault-share.preview', $share->token) }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 hover:text-orange-600"><x-cf-icon name="eye" size="xs" />{{ __('vault_public_preview') }}</a>
                                @endif
                            </div>
                        @endif
                        <a href="{{ route('vault-share.download', $share->token) }}" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-orange-600/20 hover:bg-orange-500">
                            <x-cf-icon name="download" size="xs" />{{ __('vault_public_download') }}
                        </a>
                        @if ($share->max_downloads)
                            <p class="mt-2 text-center text-xs text-slate-400">{{ __('vault_public_remaining', ['count' => max(0, $share->max_downloads - $share->download_count)]) }}</p>
                        @endif
                    @endif
                @endif
            </div>
            <p class="mt-6 text-center text-xs text-slate-400">{{ __('vault_public_footer') }}</p>
        </div>
    </div>
</body>
</html>
