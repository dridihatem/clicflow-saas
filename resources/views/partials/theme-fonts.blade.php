@php
    $fontHref = $flowdeskTheme['font_url'] ?? 'https://fonts.bunny.net/css?family=figtree:400,500,600|plus-jakarta-sans:500,600,700&display=swap';
@endphp
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="{{ $fontHref }}" rel="stylesheet" />

@if (!empty($flowdeskTheme['use_system_dark']))
    <script>
        (function () {
            const m = window.matchMedia('(prefers-color-scheme: dark)');
            function apply() {
                document.documentElement.classList.toggle('dark', m.matches);
            }
            apply();
            m.addEventListener('change', apply);
        })();
    </script>
@endif
