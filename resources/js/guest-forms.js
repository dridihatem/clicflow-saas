function bindPhoneNationalDigits() {
    document.querySelectorAll('[data-flowdesk-digits-only]').forEach((el) => {
        el.classList.add('flowdesk-ltr-num', 'tabular-nums');
        el.addEventListener('input', () => {
            el.value = el.value.replace(/\D/g, '');
        });
    });
}

function syncPhoneIsoFromCountry() {
    const country = document.getElementById('country');
    const phoneIso = document.getElementById('phone_country_iso');
    if (!country || !phoneIso) {
        return;
    }

    const v = country.value;
    if (!v) {
        return;
    }

    const hasOption = [...phoneIso.options].some((o) => o.value === v);
    if (!hasOption) {
        return;
    }

    if (phoneIso.value === v) {
        // Still notify Alpine UI in case native was set without a prior event
        phoneIso.dispatchEvent(new Event('change', { bubbles: true }));
        return;
    }

    phoneIso.value = v;
    phoneIso.dispatchEvent(new Event('change', { bubbles: true }));
}

function bindCountryToPhoneIso() {
    const country = document.getElementById('country');
    if (!country) {
        return;
    }

    country.addEventListener('change', syncPhoneIsoFromCountry);
    // Apply once if country is already chosen (e.g. old input / defaults)
    syncPhoneIsoFromCountry();

    // Expose for register wizard when entering the phone step
    window.flowdeskSyncPhoneIsoFromCountry = syncPhoneIsoFromCountry;
}

function bindCountryDefaults() {
    const country = document.getElementById('country');
    if (!country) {
        return;
    }

    const defaults = window.flowdeskCountryDefaults || {};
    const vatRates = defaults.vat || {};
    const currencies = defaults.currency || {};
    const vatInput = document.getElementById('vat_percent');
    const currencyInput = document.getElementById('default_currency');

    if (vatInput) {
        vatInput.addEventListener('input', () => {
            vatInput.dataset.userEdited = '1';
        });
    }

    const apply = () => {
        const code = country.value;
        if (!code) {
            return;
        }

        if (vatInput && vatInput.dataset.userEdited !== '1') {
            if (Object.prototype.hasOwnProperty.call(vatRates, code)) {
                const rate = Number(vatRates[code]);
                vatInput.value = Number.isInteger(rate) ? String(rate) : String(rate);
            }
        }

        if (currencyInput && currencyInput.value.trim() === '') {
            const platformDefault = (window.flowdeskCountryDefaults && window.flowdeskCountryDefaults.defaultCurrency) || 'EUR';
            currencyInput.value = platformDefault;
        }
    };

    country.addEventListener('change', apply);
    apply();
}

document.addEventListener('DOMContentLoaded', () => {
    bindPhoneNationalDigits();
    bindCountryToPhoneIso();
    bindCountryDefaults();
});
