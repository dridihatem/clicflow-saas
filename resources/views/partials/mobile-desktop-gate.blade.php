{{-- Blocks the logged-in workspace on phones/small tablets until a proper mobile UI ships. --}}
@php
    // Portal clients keep mobile access (the gate text promises it); only the staff workspace is blocked.
    $gateUser = auth()->user();
    $isPortalClient = $gateUser && method_exists($gateUser, 'hasRole') && $gateUser->hasRole('client');
    $blockMobile = (bool) config('flowdesk.block_mobile_workspace', true) && ! $isPortalClient;
    $brand = (string) config('flowdesk.brand_name', config('app.name'));
@endphp
@if ($blockMobile)
<style>
    #fd-mobile-desktop-gate {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        align-items: stretch;
        justify-content: stretch;
        padding: 1.25rem;
        background:
            radial-gradient(120% 80% at 10% 0%, color-mix(in srgb, var(--flow-primary, #0f766e) 22%, transparent), transparent 55%),
            linear-gradient(165deg, #0b1220 0%, #132033 48%, #0f172a 100%);
        color: #f8fafc;
        font-family: var(--flow-font-sans, ui-sans-serif, system-ui, sans-serif);
    }
    #fd-mobile-desktop-gate .fd-mobile-gate-inner {
        margin: auto;
        width: min(100%, 26rem);
        text-align: center;
    }
    #fd-mobile-desktop-gate .fd-mobile-gate-brand {
        font-size: clamp(1.75rem, 8vw, 2.35rem);
        font-weight: 700;
        letter-spacing: -0.03em;
        line-height: 1.1;
        color: #fff;
    }
    #fd-mobile-desktop-gate .fd-mobile-gate-title {
        margin-top: 1.25rem;
        font-size: 1.125rem;
        font-weight: 600;
        line-height: 1.35;
        color: #e2e8f0;
    }
    #fd-mobile-desktop-gate .fd-mobile-gate-body {
        margin-top: 0.75rem;
        font-size: 0.95rem;
        line-height: 1.55;
        color: #94a3b8;
    }
    #fd-mobile-desktop-gate .fd-mobile-gate-note {
        margin-top: 1.25rem;
        font-size: 0.875rem;
        line-height: 1.5;
        color: #cbd5e1;
    }
    #fd-mobile-desktop-gate .fd-mobile-gate-actions {
        margin-top: 1.75rem;
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    #fd-mobile-desktop-gate a.fd-mobile-gate-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        border-radius: 0.75rem;
        padding: 0.8rem 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        transition: opacity 0.15s ease;
    }
    #fd-mobile-desktop-gate a.fd-mobile-gate-btn:active {
        opacity: 0.85;
    }
    #fd-mobile-desktop-gate a.fd-mobile-gate-btn-primary {
        background: var(--flow-primary, #0f766e);
        color: #fff;
    }
    #fd-mobile-desktop-gate button.fd-mobile-gate-btn-ghost {
        width: 100%;
        cursor: pointer;
        border: 1px solid rgba(148, 163, 184, 0.35);
        background: rgba(15, 23, 42, 0.35);
        color: #e2e8f0;
        border-radius: 0.75rem;
        padding: 0.8rem 1rem;
        font-size: 0.875rem;
        font-weight: 600;
        font-family: inherit;
    }
    #fd-mobile-desktop-gate .fd-mobile-gate-icon {
        margin: 0 auto;
        display: flex;
        height: 3.5rem;
        width: 3.5rem;
        align-items: center;
        justify-content: center;
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.08);
        color: var(--flow-primary, #5eead4);
        font-size: 1.35rem;
    }

    /* Hide workspace immediately on narrow viewports (no JS flash). */
    @media (max-width: 1023px) {
        body > *:not(#fd-mobile-desktop-gate):not(style):not(script) {
            display: none !important;
        }
        #fd-mobile-desktop-gate {
            display: flex !important;
        }
        body {
            overflow: hidden !important;
        }
    }

    html.fd-mobile-workspace-blocked body > *:not(#fd-mobile-desktop-gate):not(style):not(script) {
        display: none !important;
    }
    html.fd-mobile-workspace-blocked #fd-mobile-desktop-gate {
        display: flex !important;
    }
    html.fd-mobile-workspace-blocked body {
        overflow: hidden !important;
    }
</style>

<div id="fd-mobile-desktop-gate" role="dialog" aria-modal="true" aria-labelledby="fd-mobile-gate-title">
    <div class="fd-mobile-gate-inner">
        <div class="fd-mobile-gate-icon" aria-hidden="true">
            <i class="fa-solid fa-desktop"></i>
        </div>
        <p class="fd-mobile-gate-brand">{{ $brand }}</p>
        <h1 id="fd-mobile-gate-title" class="fd-mobile-gate-title">{{ __('mobile_gate_title') }}</h1>
        <p class="fd-mobile-gate-body">{{ __('mobile_gate_body', ['brand' => $brand]) }}</p>
        <p class="fd-mobile-gate-note">{{ __('mobile_gate_note') }}</p>
        <div class="fd-mobile-gate-actions">
            <a href="{{ url('/') }}" class="fd-mobile-gate-btn fd-mobile-gate-btn-primary">
                {{ __('mobile_gate_back_home') }}
            </a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="fd-mobile-gate-btn-ghost">
                        {{ __('Log Out') }}
                    </button>
                </form>
            @endauth
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var html = document.documentElement;
    var mq = null;
    try {
        mq = window.matchMedia('(max-width: 1023px)');
    } catch (e) {}

    var apply = function () {
        var narrow = mq ? mq.matches : (window.innerWidth || 0) <= 1023;
        var coarse = false;
        try {
            coarse = window.matchMedia('(pointer: coarse)').matches;
        } catch (e) {}
        var touchUa = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
        // Extra class for large phones / tablets that report wide CSS width but are still mobile.
        if (!narrow && coarse && touchUa) {
            html.classList.add('fd-mobile-workspace-blocked');
        } else if (!narrow) {
            html.classList.remove('fd-mobile-workspace-blocked');
        }
    };

    apply();
    if (mq && mq.addEventListener) {
        mq.addEventListener('change', apply);
    } else if (mq && mq.addListener) {
        mq.addListener(apply);
    }
    window.addEventListener('orientationchange', apply);
    window.addEventListener('resize', apply);
})();
</script>
@endif
