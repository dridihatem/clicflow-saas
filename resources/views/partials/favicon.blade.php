@php($fv = @filemtime(public_path('favicon-512.png')) ?: @filemtime(public_path('images/clicflow-icon.png')) ?: time())
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $fv }}" sizes="any">
<link rel="icon" href="{{ asset('favicon.svg') }}?v={{ $fv }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon-32.png') }}?v={{ $fv }}" type="image/png" sizes="32x32">
<link rel="icon" href="{{ asset('favicon-16.png') }}?v={{ $fv }}" type="image/png" sizes="16x16">
<link rel="icon" href="{{ asset('favicon-192.png') }}?v={{ $fv }}" type="image/png" sizes="192x192">
<link rel="icon" href="{{ asset('favicon-512.png') }}?v={{ $fv }}" type="image/png" sizes="512x512">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $fv }}" sizes="180x180">
<link rel="manifest" href="{{ asset('site.webmanifest') }}?v={{ $fv }}">
<meta name="theme-color" content="#EA580C">
<meta name="msapplication-TileColor" content="#0B1437">
<meta name="msapplication-TileImage" content="{{ asset('favicon-192.png') }}?v={{ $fv }}">
