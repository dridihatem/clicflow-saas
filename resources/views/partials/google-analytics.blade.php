@php
    $gaId = trim((string) config('flowdesk.ga_measurement_id', ''));
@endphp
@if ($gaId !== '')
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @js($gaId));
    </script>
@endif
