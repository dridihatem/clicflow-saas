@php
    $uid = 'cfhero-'.uniqid();
@endphp
<svg
    class="cf-hero-flow"
    viewBox="0 0 1440 900"
    preserveAspectRatio="xMidYMid slice"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    focusable="false"
>
    <defs>
        <linearGradient id="{{ $uid }}-rail" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#c2410c" stop-opacity="0"/>
            <stop offset="35%" stop-color="#ea580c" stop-opacity="0.8"/>
            <stop offset="70%" stop-color="#fbbf24" stop-opacity="0.7"/>
            <stop offset="100%" stop-color="#fbbf24" stop-opacity="0"/>
        </linearGradient>
        <linearGradient id="{{ $uid }}-rail2" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0%" stop-color="#f97316" stop-opacity="0"/>
            <stop offset="40%" stop-color="#fb923c" stop-opacity="0.5"/>
            <stop offset="100%" stop-color="#c2410c" stop-opacity="0"/>
        </linearGradient>
        <radialGradient id="{{ $uid }}-orb" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#fdba74"/>
            <stop offset="55%" stop-color="#ea580c"/>
            <stop offset="100%" stop-color="#c2410c"/>
        </radialGradient>
        <filter id="{{ $uid }}-soft" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur stdDeviation="8" result="b"/>
            <feMerge>
                <feMergeNode in="b"/>
                <feMergeNode in="SourceGraphic"/>
            </feMerge>
        </filter>
    </defs>

    <path id="{{ $uid }}-a" d="M-40,220 C220,180 420,320 720,300 C980,282 1180,160 1480,210" fill="none" stroke="url(#{{ $uid }}-rail)" stroke-width="2.2"/>
    <path id="{{ $uid }}-b" d="M-40,480 C260,520 480,400 720,430 C1000,468 1220,560 1480,500" fill="none" stroke="url(#{{ $uid }}-rail2)" stroke-width="1.8"/>
    <path id="{{ $uid }}-c" d="M-40,680 C300,620 520,740 760,700 C1040,652 1240,760 1480,720" fill="none" stroke="url(#{{ $uid }}-rail)" stroke-width="1.5" opacity="0.7"/>

    <g class="cf-hero-flow__nodes" fill="#1a120c" stroke="#fb923c" stroke-opacity="0.5">
        <circle cx="180" cy="236" r="10"/>
        <circle cx="420" cy="318" r="8"/>
        <circle cx="980" cy="278" r="9"/>
        <circle cx="260" cy="508" r="8"/>
        <circle cx="640" cy="422" r="11"/>
        <circle cx="1120" cy="520" r="8"/>
        <circle cx="360" cy="652" r="7"/>
        <circle cx="900" cy="678" r="9"/>
    </g>

    <g filter="url(#{{ $uid }}-soft)">
        <circle cx="720" cy="390" r="54" fill="url(#{{ $uid }}-orb)" opacity="0.95">
            <animate attributeName="r" values="50;58;50" dur="4s" repeatCount="indefinite"/>
        </circle>
        <text
            x="720"
            y="398"
            text-anchor="middle"
            fill="#120c08"
            font-family="Space Grotesk, Outfit, sans-serif"
            font-size="28"
            font-weight="700"
            letter-spacing="-0.04em"
        >CF</text>
    </g>

    <circle r="4" fill="#fdba74"><animateMotion dur="7s" repeatCount="indefinite"><mpath href="#{{ $uid }}-a"/></animateMotion></circle>
    <circle r="3" fill="#fbbf24"><animateMotion dur="9s" begin="1.2s" repeatCount="indefinite"><mpath href="#{{ $uid }}-a"/></animateMotion></circle>
    <circle r="3.5" fill="#fb923c"><animateMotion dur="8s" begin="0.6s" repeatCount="indefinite"><mpath href="#{{ $uid }}-b"/></animateMotion></circle>
    <circle r="3" fill="#ea580c"><animateMotion dur="10s" begin="2s" repeatCount="indefinite"><mpath href="#{{ $uid }}-b"/></animateMotion></circle>
    <circle r="3" fill="#f97316"><animateMotion dur="11s" begin="0.4s" repeatCount="indefinite"><mpath href="#{{ $uid }}-c"/></animateMotion></circle>
    <circle r="2.5" fill="#fdba74"><animateMotion dur="9.5s" begin="3s" repeatCount="indefinite"><mpath href="#{{ $uid }}-c"/></animateMotion></circle>
</svg>
