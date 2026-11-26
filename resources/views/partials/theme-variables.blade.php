{{-- Tenant / platform theme: load after @vite. Uses !important so tokens beat compiled app.css + Tailwind. --}}
@php
    $primary = $flowdeskTheme['primary_color'] ?? '#EA580C';
    $primaryHover = $flowdeskTheme['primary_hover'] ?? '#C2410C';
    $secondary = $flowdeskTheme['secondary_color'] ?? '#475569';
    $fontStack = $flowdeskTheme['font_stack'] ?? "'Inter', ui-sans-serif, system-ui, sans-serif";
@endphp
<style id="flowdesk-theme-tokens">
    :root {
        --flow-primary: {{ $primary }} !important;
        --flow-primary-hover: {{ $primaryHover }} !important;
        --flow-secondary: {{ $secondary }} !important;
        {{-- Raw: inside <style>, Blade {{ }} escapes quotes to &#039; which CSS does not treat as quotes --}}
        --flow-font-sans: {!! $fontStack !!} !important;
        --flow-font-display: 'Space Grotesk', var(--flow-font-sans) !important;
        --flow-surface: #ffffff !important;
        --flow-surface-muted: #f8fafc !important;
        --flow-border: #e2e8f0 !important;
        --flow-text: #0f172a !important;
        --flow-font-family:{{ $fontStack }}  !important;
        --flow-text-muted: #64748b !important;
        --cf-sky: #30C0F0 !important;
        --cf-green: #24B478 !important;
        --cf-gold: #E4A824 !important;
        --cf-raspberry: #D81854 !important;
        --cf-purple: #480C48 !important;
        --cf-orange: #EA580C !important;
        --cf-accent-bar: linear-gradient(90deg,#30C0F0 0%,#24B478 20%,#E4A824 40%,#EA580C 60%,#D81854 80%,#480C48 100%) !important;
    }

    html.dark {
        --flow-surface: #0b1020 !important;
        --flow-surface-muted: #070b1a !important;
        --flow-border: rgba(124, 92, 252, 0.28) !important;
        --flow-text: #f8fafc !important;
        --flow-text-muted: #94a3b8 !important;
        --flow-font-family: var(--flow-font-family) !important;
    }

    /* Scope: whole authenticated app (html + body) follows saved font / colors / light-dark */
    html.flow-theme-app {
        font-family: var(--flow-font-sans), ui-sans-serif, system-ui, sans-serif !important;
    }

    body.flow-app-body {
        background-color: var(--flow-surface-muted) !important;
        color: var(--flow-text) !important;
        font-family: var(--flow-font-family) !important;
        min-height: 100vh;
    }

    html.dark body.flow-app-body {
        background-color: rgb(11, 16, 32) !important;
        background-image:
            radial-gradient(circle at 12% 6%, rgba(108, 92, 231, 0.16), transparent 42%),
            radial-gradient(circle at 88% 12%, rgba(0, 209, 255, 0.08), transparent 40%) !important;
        color: var(--flow-text) !important;
    }

    @if (filled($flowdeskTheme['custom_css'] ?? null))
        {!! $flowdeskTheme['custom_css'] !!}
    @endif
</style>
