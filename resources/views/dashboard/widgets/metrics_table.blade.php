{{-- Merged into company_cards; kept so widget prefs that enable metrics_table alone still render the snapshot. --}}
@include('dashboard.widgets.company_cards', ['metrics' => $metrics ?? []])
