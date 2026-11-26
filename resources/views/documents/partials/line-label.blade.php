{{-- Invoice / quote line label: bold title + small muted description. Lines without title render exactly as before. --}}
@php
    $lineTitle = trim((string) ($row->title ?? ''));
    $lineDesc = (string) ($row->description ?? '');
    $lineVariant = $variant ?? 'html';
@endphp
@if ($lineTitle === ''){{ $lineDesc }}@elseif ($lineVariant === 'pdf')<span class="line-title" style="display:block;font-weight:bold;">{{ $lineTitle }}</span>@if (trim($lineDesc) !== '')<span class="line-desc" style="display:block;margin-top:2px;font-size:0.86em;line-height:1.35;color:#64748b;">{!! nl2br(e($lineDesc)) !!}</span>@endif
@else<span class="block font-semibold text-slate-900 dark:text-white" data-line-title>{{ $lineTitle }}</span>@if (trim($lineDesc) !== '')<span class="mt-0.5 block whitespace-pre-line text-xs leading-relaxed text-slate-500 dark:text-slate-400" data-line-desc>{{ $lineDesc }}</span>@endif
@endif
