@php
    $isFr = ! str_starts_with((string) app()->getLocale(), 'en') && ! str_starts_with((string) app()->getLocale(), 'ar');
    $t = fn (array $m) => $isFr ? ($m['fr'] ?? $m['en']) : ($m['en'] ?? $m['fr']);
    $homeUrl = url('/');
    try { $homeUrl = auth()->check() && \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : url('/'); } catch (\Throwable $e) {}
    $logo = file_exists(public_path('images/logo-clicflow-light.png')) ? asset('images/logo-clicflow-light.png') : null;
@endphp
<!DOCTYPE html>
<html lang="{{ $isFr ? 'fr' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $code }} · {{ $t($title) }} · {{ config('app.name', 'ClicFlow') }}</title>
    @include('partials.favicon')
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:flex;flex-direction:column;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#0f172a;background:#fff7ed}
        .bar{height:6px;background:linear-gradient(90deg,#EA580C,#F97316,#FBBF24)}
        header{padding:20px 24px}
        header img{height:34px;width:auto}
        header .name{font-weight:800;font-size:22px;color:#EA580C;text-decoration:none}
        main{flex:1;display:flex;align-items:center;justify-content:center;padding:24px}
        .card{width:100%;max-width:560px;background:#fff;border:1px solid #fed7aa;border-radius:24px;padding:40px 32px;text-align:center;box-shadow:0 30px 60px -30px rgba(154,52,18,.35)}
        .code{font-size:72px;line-height:1;font-weight:800;letter-spacing:-.04em;background:linear-gradient(135deg,#EA580C,#9a3412);-webkit-background-clip:text;background-clip:text;color:transparent;margin:0}
        h1{margin:16px 0 8px;font-size:24px;color:#EA580C}
        p{margin:0 auto;max-width:420px;color:#475569;line-height:1.6;font-size:15px}
        .actions{margin-top:28px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:8px;border-radius:12px;padding:12px 20px;font-weight:700;font-size:14px;text-decoration:none;border:1px solid transparent;cursor:pointer}
        .btn-primary{background:#EA580C;color:#fff;box-shadow:0 10px 20px -10px rgba(234,88,12,.6)}
        .btn-primary:hover{background:#C2410C}
        .btn-ghost{background:#fff;color:#0f172a;border-color:#e2e8f0}
        .btn-ghost:hover{border-color:#EA580C;color:#C2410C}
        footer{padding:16px;text-align:center;font-size:12px;color:#94a3b8}
    </style>
</head>
<body>
    <div class="bar"></div>
    <header>
        <a href="{{ url('/') }}" class="name">
            @if ($logo)<img src="{{ $logo }}" alt="{{ config('app.name', 'ClicFlow') }}">@else{{ config('app.name', 'ClicFlow') }}@endif
        </a>
    </header>
    <main>
        <div class="card">
            <p class="code">{{ $code }}</p>
            <h1>{{ $t($title) }}</h1>
            <p>{{ $t($message) }}</p>
            <div class="actions">
                @if (! empty($reload))
                    <a href="{{ url()->current() }}" class="btn btn-primary">{{ $isFr ? 'Réessayer' : 'Try again' }}</a>
                    <a href="{{ $homeUrl }}" class="btn btn-ghost">{{ $isFr ? "Retour à l'accueil" : 'Back to home' }}</a>
                @else
                    <a href="{{ $homeUrl }}" class="btn btn-primary">{{ $isFr ? "Retour à l'accueil" : 'Back to home' }}</a>
                    <a href="javascript:history.back()" class="btn btn-ghost">{{ $isFr ? 'Page précédente' : 'Go back' }}</a>
                @endif
            </div>
        </div>
    </main>
    <footer>© {{ date('Y') }} {{ config('app.name', 'ClicFlow') }}</footer>
</body>
</html>
