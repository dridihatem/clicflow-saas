{{-- Soft deterrent only — cannot fully prevent DevTools. Disabled in local/debug unless forced. --}}
@php
    $blockDevtools = (bool) config('flowdesk.block_devtools', false);
@endphp
@if ($blockDevtools)
<script>
(function () {
    'use strict';
    var block = function (e) {
        e.preventDefault();
        return false;
    };

    document.addEventListener('contextmenu', block, { capture: true });
    document.addEventListener('dragstart', block, { capture: true });
    document.addEventListener('selectstart', function (e) {
        var t = e.target;
        if (t && (t.closest('input, textarea, [contenteditable="true"], .flow-input, .note-editable'))) {
            return;
        }
        e.preventDefault();
    }, { capture: true });

    document.addEventListener('keydown', function (e) {
        var key = (e.key || '').toLowerCase();
        var ctrl = e.ctrlKey || e.metaKey;
        var shift = e.shiftKey;

        if (key === 'f12') {
            e.preventDefault();
            return false;
        }
        if (ctrl && shift && (key === 'i' || key === 'j' || key === 'c' || key === 'k')) {
            e.preventDefault();
            return false;
        }
        if (ctrl && (key === 'u' || key === 's')) {
            e.preventDefault();
            return false;
        }
    }, { capture: true });

    // Size-delta DevTools heuristic — skip on phones/tablets: Chrome mobile
    // chrome often makes outer-inner deltas look like DevTools and would blur + block taps.
    var isCoarsePointer = false;
    try {
        isCoarsePointer = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    } catch (e) {}
    var isTouchUa = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
    if (!isCoarsePointer && !isTouchUa) {
        var threshold = 160;
        var armed = false;
        var check = function () {
            var open = (window.outerWidth - window.innerWidth > threshold)
                || (window.outerHeight - window.innerHeight > threshold);
            if (open && !armed) {
                armed = true;
                document.documentElement.classList.add('fd-devtools-blocked');
            } else if (!open && armed) {
                armed = false;
                document.documentElement.classList.remove('fd-devtools-blocked');
            }
        };
        setInterval(check, 800);
        window.addEventListener('resize', check);
    }
})();
</script>
<style>
    html.fd-devtools-blocked body {
        filter: blur(8px);
        pointer-events: none;
        user-select: none;
    }
</style>
@endif
