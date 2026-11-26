@php
    $peekId = $peekId ?? 'auth';
    $novaBrand = config('flowdesk.ai_assistant_brand_name', 'Nova');
@endphp
{{-- Nova robot — 3D mascot image (public/robot.png) --}}
<div class="auth-peek auth-peek--nova-auth is-idle">
    <p class="auth-peek__tooltip" role="note">
        <span class="auth-peek__tooltip-badge">{{ __('AI') }}</span>
        <span class="auth-peek__tooltip-text">
            {!! str_replace(
                e($novaBrand),
                '<strong class="auth-peek__brand">' . e($novaBrand) . '</strong>',
                e(__('auth_nova_agent_tooltip', ['brand' => $novaBrand]))
            ) !!}
        </span>
    </p>

    <div class="auth-peek__svg-wrap" aria-hidden="true">
        <img
            class="auth-peek__robot-img"
            src="{{ asset('robot.png') }}"
            alt=""
            width="440"
            height="680"
            decoding="async"
        />
    </div>
</div>
