{{-- Live "lines total" + "discount" rows for document totals. $rowClass, $suffix (JS expression appended to amounts). --}}
@php $sfx = $suffix ?? ''; @endphp
<div class="{{ $rowClass }}" x-show="totals.discount > 0" x-cloak>
    <dt>{{ __('Lines total (ex. VAT)') }}</dt>
    <dd class="tabular-nums font-medium" x-text="fmtMinor(totals.lines){!! $sfx !!}"></dd>
</div>
<div class="{{ $rowClass }} text-emerald-700 dark:text-emerald-300" x-show="totals.discount > 0" x-cloak data-cf-discount-row>
    <dt>{{ __('Discount') }}<span x-show="discountLabel()" x-text="' (' + discountLabel() + ')'"></span></dt>
    <dd class="tabular-nums font-medium" x-text="'− ' + fmtMinor(totals.discount){!! $sfx !!}"></dd>
</div>
