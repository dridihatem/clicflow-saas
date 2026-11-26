const STORAGE_KEY = 'clicflow-mkt-theme';

function storedTheme() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        if (value === 'dark' || value === 'light') {
            return value;
        }
    } catch {
        // ignore quota / private mode
    }

    return 'light';
}

export function applyMarketingTheme(theme) {
    const light = theme === 'light';
    document.documentElement.classList.toggle('mkt-light', light);
    document.documentElement.style.colorScheme = light ? 'light' : 'dark';
    const meta = document.querySelector('meta[name="color-scheme"]');
    if (meta) {
        meta.setAttribute('content', light ? 'light' : 'dark');
    }
    try {
        localStorage.setItem(STORAGE_KEY, light ? 'light' : 'dark');
    } catch {
        // ignore quota / private mode
    }
    document.querySelectorAll('[data-mkt-theme-toggle]').forEach((button) => {
        const next = light ? 'dark' : 'light';
        button.setAttribute('aria-pressed', light ? 'true' : 'false');
        button.setAttribute('aria-label', next === 'light'
            ? (button.dataset.labelLight || 'Light mode')
            : (button.dataset.labelDark || 'Dark mode'));
        button.setAttribute('title', button.getAttribute('aria-label') || '');
    });
}

export function bootMarketingTheme() {
    applyMarketingTheme(storedTheme());
    document.querySelectorAll('[data-mkt-theme-toggle]').forEach((button) => {
        if (button.dataset.mktThemeReady === '1') {
            return;
        }
        button.dataset.mktThemeReady = '1';
        button.addEventListener('click', () => {
            applyMarketingTheme(document.documentElement.classList.contains('mkt-light') ? 'dark' : 'light');
        });
    });
}

export function registerMarketingTheme() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootMarketingTheme, { once: true });
    } else {
        bootMarketingTheme();
    }
}
