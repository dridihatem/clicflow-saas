@props([
    'text' => 'ClicFlow',
    'animate' => true,
    'size' => 'md', // sm | md | lg
    'mark' => true,
])
@php
    $chars = preg_split('//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $sizeClass = match ($size) {
        'sm' => 'mkt-logo--sm',
        'lg' => 'mkt-logo--lg',
        default => 'mkt-logo--md',
    };
    $gradId = 'fqMarkGrad-'.uniqid();
@endphp
<span
    {{ $attributes->class([
        'mkt-logo',
        $sizeClass,
        'mkt-logo--split' => $animate,
    ]) }}
    data-mkt-logo="{{ $animate ? '1' : '0' }}"
    aria-label="{{ $text }}"
>
    @if ($mark)
        <span class="mkt-logo__mark" aria-hidden="true">
            <span class="mkt-logo__orbit"></span>
            <span class="mkt-logo__orbit mkt-logo__orbit--2"></span>
            <svg class="mkt-logo__svg" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="{{ $gradId }}" x1="4" y1="4" x2="36" y2="36" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#22d3ee"/>
                        <stop offset="0.55" stop-color="#818cf8"/>
                        <stop offset="1" stop-color="#a78bfa"/>
                    </linearGradient>
                </defs>
                <rect x="2" y="2" width="36" height="36" rx="11" fill="url(#{{ $gradId }})" opacity="0.22"/>
                <path d="M12 12.5h16.5a2.2 2.2 0 0 1 0 4.4H17.2v3.1h9.4a2.2 2.2 0 0 1 0 4.4h-9.4V28a2.2 2.2 0 1 1-4.4 0V14.7c0-1.2 1-2.2 2.2-2.2Z" fill="url(#{{ $gradId }})"/>
                <circle cx="28.5" cy="28" r="3.1" fill="#22d3ee"/>
                <circle cx="28.5" cy="28" r="1.2" fill="#0b1020"/>
            </svg>
        </span>
    @endif
    <span class="mkt-logo__word">
        @foreach ($chars as $i => $char)
            <span
                class="mkt-logo__char"
                style="--i: {{ $i }}"
                @if ($char === ' ')aria-hidden="true"@endif
            >{!! $char === ' ' ? '&nbsp;' : e($char) !!}</span>
        @endforeach
    </span>
</span>
