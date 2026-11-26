<script>
    window.flowdeskCountryDefaults = {
        vat: @json(config('flowdesk_country_vat', [])),
        currency: @json(config('flowdesk.country_currency', [])),
        defaultCurrency: @json(flowdesk_default_currency()),
    };
</script>
