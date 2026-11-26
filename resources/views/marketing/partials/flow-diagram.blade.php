{{-- Animated ClicFlow AI flow diagram — light mode --}}
@php
    $uid = 'fqflow-'.uniqid();
@endphp
<div class="mkt-flow-visual mkt-glass relative overflow-hidden rounded-[20px] p-3 sm:p-5">
    <div class="pointer-events-none absolute -start-10 top-0 h-40 w-40 rounded-full bg-[rgba(124,92,252,0.12)] blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -end-8 bottom-0 h-36 w-36 rounded-full bg-[rgba(38,232,212,0.14)] blur-3xl" aria-hidden="true"></div>
    <svg class="relative z-[1] h-auto w-full" viewBox="0 0 600 400" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ __('Welcome hero panel title') }}">
        <defs>
            <linearGradient id="{{ $uid }}-rail" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0%" stop-color="#EA580C" stop-opacity="0.75"/>
                <stop offset="100%" stop-color="#EA580C" stop-opacity="0.8"/>
            </linearGradient>
            <radialGradient id="{{ $uid }}-orb" cx="50%" cy="50%" r="50%">
                <stop offset="0%" stop-color="#9B84FF"/>
                <stop offset="55%" stop-color="#EA580C"/>
                <stop offset="100%" stop-color="#5B45D6"/>
            </radialGradient>
            <filter id="{{ $uid }}-glow" x="-80%" y="-80%" width="260%" height="260%">
                <feGaussianBlur stdDeviation="5" result="blur"/>
                <feMerge>
                    <feMergeNode in="blur"/>
                    <feMergeNode in="SourceGraphic"/>
                </feMerge>
            </filter>
        </defs>

        <path id="{{ $uid }}-p1" d="M64,80 C170,80 220,160 300,200" stroke="url(#{{ $uid }}-rail)" stroke-width="1.75" fill="none" opacity="0.7"/>
        <path id="{{ $uid }}-p2" d="M64,200 C160,200 240,200 300,200" stroke="url(#{{ $uid }}-rail)" stroke-width="1.75" fill="none" opacity="0.7"/>
        <path id="{{ $uid }}-p3" d="M64,320 C170,320 220,240 300,200" stroke="url(#{{ $uid }}-rail)" stroke-width="1.75" fill="none" opacity="0.7"/>
        <path id="{{ $uid }}-p4" d="M300,200 C380,200 430,140 536,140" stroke="url(#{{ $uid }}-rail)" stroke-width="1.75" fill="none" opacity="0.7"/>
        <path id="{{ $uid }}-p5" d="M300,200 C380,200 430,260 536,260" stroke="url(#{{ $uid }}-rail)" stroke-width="1.75" fill="none" opacity="0.7"/>

        <g>
            <circle cx="64" cy="80" r="22" fill="#FFFFFF" stroke="#D4D8F0"/>
            <path d="M55,74 h18 v12 h-18 z M55,74 l9,7 l9,-7" stroke="#5B647E" stroke-width="1.3" fill="none" stroke-linejoin="round"/>
            <text x="64" y="118" text-anchor="middle" class="mkt-node-label">Inbox</text>
        </g>
        <g>
            <circle cx="64" cy="200" r="22" fill="#FFFFFF" stroke="#D4D8F0"/>
            <path d="M55,192 h18 v16 h-18 z M55,197 h18 M55,202 h18 M61,192 v16 M67,192 v16" stroke="#5B647E" stroke-width="1" fill="none"/>
            <text x="64" y="238" text-anchor="middle" class="mkt-node-label">Sheets</text>
        </g>
        <g>
            <circle cx="64" cy="320" r="22" fill="#FFFFFF" stroke="#D4D8F0"/>
            <path d="M55,313 h18 v10 a2,2 0 0 1 -2,2 h-11 l-5,4 v-4 a2,2 0 0 1 -2,-2 z" stroke="#5B647E" stroke-width="1.2" fill="none" stroke-linejoin="round"/>
            <text x="64" y="358" text-anchor="middle" class="mkt-node-label">Chats</text>
        </g>

        <circle cx="300" cy="200" r="34" fill="url(#{{ $uid }}-orb)" filter="url(#{{ $uid }}-glow)">
            <animate attributeName="r" values="32;36;32" dur="3.4s" repeatCount="indefinite"/>
        </circle>
        <path d="M289,190 L300,200 L289,210 M300,200 L313,200" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        <text x="300" y="250" text-anchor="middle" class="mkt-node-label mkt-node-label--accent">ClicFlow AI</text>

        <g>
            <circle cx="536" cy="140" r="22" fill="#FFFFFF" stroke="#D4D8F0"/>
            <path d="M527,132 h18 M527,140 h18 M527,148 h11" stroke="#5B647E" stroke-width="1.4" stroke-linecap="round"/>
            <text x="536" y="178" text-anchor="middle" class="mkt-node-label">Reports</text>
        </g>
        <g>
            <circle cx="536" cy="260" r="22" fill="#FFFFFF" stroke="#D4D8F0"/>
            <path d="M528,258 l4,4 l8,-9" stroke="#EA580C" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <text x="536" y="298" text-anchor="middle" class="mkt-node-label">Tasks done</text>
        </g>

        <circle r="3.6" fill="#EA580C"><animateMotion dur="3s" begin="0s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p1"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="3s" begin="1.5s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p1"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="2.6s" begin="0.4s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p2"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="2.6s" begin="1.8s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p2"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="3.2s" begin="0.8s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p3"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="3.2s" begin="2.3s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p3"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="2.8s" begin="0.2s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p4"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="2.8s" begin="1.6s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p4"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="3s" begin="0.9s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p5"/></animateMotion></circle>
        <circle r="3.6" fill="#EA580C"><animateMotion dur="3s" begin="2.4s" repeatCount="indefinite"><mpath href="#{{ $uid }}-p5"/></animateMotion></circle>
    </svg>
</div>
