@php
    $art = $art ?? 'welcome';
@endphp
<svg viewBox="0 0 320 240" class="h-full w-full" role="img" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="ptg-{{ $art }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#22d3ee" stop-opacity="0.95"/>
            <stop offset="100%" stop-color="#0891b2" stop-opacity="0.85"/>
        </linearGradient>
    </defs>

    @if ($art === 'welcome')
        <rect x="36" y="48" width="248" height="144" rx="18" fill="#0f172a"/>
        <rect x="52" y="66" width="120" height="12" rx="6" fill="url(#ptg-welcome)"/>
        <rect x="52" y="90" width="180" height="8" rx="4" fill="#334155"/>
        <rect x="52" y="108" width="150" height="8" rx="4" fill="#334155"/>
        <circle cx="236" cy="128" r="34" fill="url(#ptg-welcome)" opacity="0.9"/>
        <path d="M224 128h24M236 116v24" stroke="#0f172a" stroke-width="4" stroke-linecap="round"/>
        <rect x="52" y="140" width="70" height="28" rx="8" fill="#22d3ee"/>
        <rect x="132" y="140" width="70" height="28" rx="8" fill="#1e293b" stroke="#334155"/>
    @elseif ($art === 'clients')
        <circle cx="110" cy="100" r="36" fill="url(#ptg-clients)"/>
        <circle cx="170" cy="92" r="28" fill="#67e8f9" opacity="0.85"/>
        <circle cx="210" cy="118" r="24" fill="#a5f3fc" opacity="0.75"/>
        <rect x="70" y="150" width="180" height="14" rx="7" fill="#e2e8f0"/>
        <rect x="90" y="174" width="140" height="10" rx="5" fill="#cbd5e1"/>
        <path d="M96 100h28M110 86v28" stroke="#0f172a" stroke-width="3" stroke-linecap="round" opacity="0.35"/>
    @elseif ($art === 'billing')
        <rect x="58" y="52" width="120" height="150" rx="14" fill="#fff" stroke="#cbd5e1" stroke-width="2"/>
        <rect x="74" y="72" width="88" height="10" rx="5" fill="url(#ptg-billing)"/>
        <rect x="74" y="94" width="70" height="8" rx="4" fill="#e2e8f0"/>
        <rect x="74" y="112" width="80" height="8" rx="4" fill="#e2e8f0"/>
        <rect x="74" y="148" width="88" height="24" rx="8" fill="#0f172a"/>
        <rect x="150" y="70" width="112" height="120" rx="14" fill="#0f172a"/>
        <rect x="168" y="92" width="76" height="10" rx="5" fill="#22d3ee"/>
        <rect x="168" y="114" width="60" height="8" rx="4" fill="#334155"/>
        <rect x="168" y="150" width="76" height="22" rx="8" fill="#22d3ee"/>
    @elseif ($art === 'nova')
        {{-- Nova robot --}}
        <ellipse cx="160" cy="208" rx="56" ry="10" fill="#cbd5e1" opacity="0.55"/>
        {{-- antenna --}}
        <line x1="160" y1="38" x2="160" y2="58" stroke="#0891b2" stroke-width="4" stroke-linecap="round"/>
        <circle cx="160" cy="32" r="8" fill="url(#ptg-nova)"/>
        {{-- ears / side pods --}}
        <rect x="78" y="96" width="22" height="36" rx="8" fill="#0f172a"/>
        <rect x="220" y="96" width="22" height="36" rx="8" fill="#0f172a"/>
        <circle cx="89" cy="114" r="5" fill="#22d3ee"/>
        <circle cx="231" cy="114" r="5" fill="#22d3ee"/>
        {{-- head --}}
        <rect x="104" y="58" width="112" height="88" rx="26" fill="#0f172a"/>
        <rect x="116" y="72" width="88" height="52" rx="16" fill="url(#ptg-nova)"/>
        {{-- eyes --}}
        <circle cx="140" cy="96" r="9" fill="#0f172a"/>
        <circle cx="180" cy="96" r="9" fill="#0f172a"/>
        <circle cx="142" cy="94" r="3.5" fill="#ecfeff"/>
        <circle cx="182" cy="94" r="3.5" fill="#ecfeff"/>
        {{-- smile --}}
        <path d="M138 112c8 10 36 10 44 0" fill="none" stroke="#0f172a" stroke-width="3.5" stroke-linecap="round"/>
        {{-- neck --}}
        <rect x="148" y="142" width="24" height="14" rx="4" fill="#334155"/>
        {{-- body --}}
        <rect x="118" y="154" width="84" height="48" rx="16" fill="#0f172a"/>
        <rect x="132" y="166" width="56" height="22" rx="8" fill="url(#ptg-nova)" opacity="0.9"/>
        <circle cx="160" cy="177" r="5" fill="#0f172a" opacity="0.35"/>
        {{-- arms --}}
        <rect x="92" y="160" width="22" height="12" rx="6" fill="#334155"/>
        <rect x="206" y="160" width="22" height="12" rx="6" fill="#334155"/>
    @elseif ($art === 'settings')
        <rect x="48" y="56" width="224" height="128" rx="16" fill="#0f172a"/>
        <rect x="68" y="78" width="44" height="44" rx="12" fill="url(#ptg-settings)"/>
        <rect x="128" y="82" width="120" height="10" rx="5" fill="#e2e8f0" opacity="0.85"/>
        <rect x="128" y="102" width="90" height="8" rx="4" fill="#475569"/>
        <rect x="68" y="138" width="44" height="28" rx="10" fill="#334155"/>
        <rect x="128" y="142" width="100" height="10" rx="5" fill="#475569"/>
        <circle cx="90" cy="100" r="10" fill="#0f172a" opacity="0.35"/>
        <path d="M90 94v12M84 100h12" stroke="#ecfeff" stroke-width="2.5" stroke-linecap="round"/>
    @else
        {{-- ready --}}
        <circle cx="160" cy="110" r="52" fill="url(#ptg-ready)"/>
        <path d="M138 112l14 14 30-34" fill="none" stroke="#0f172a" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
        <rect x="88" y="178" width="144" height="16" rx="8" fill="#e2e8f0"/>
        <rect x="108" y="178" width="88" height="16" rx="8" fill="#22d3ee"/>
    @endif
</svg>
