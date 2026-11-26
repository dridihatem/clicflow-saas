/**
 * Marketing brand: split-char logo + sticky menubar + chatbot open hooks.
 */

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function animateLogo(root) {
    if (!root || root.dataset.mktLogoReady === '1') {
        return;
    }
    root.dataset.mktLogoReady = '1';
    const chars = root.querySelectorAll('.mkt-logo__char');
    if (!chars.length) {
        return;
    }
    if (prefersReducedMotion()) {
        chars.forEach((el) => {
            el.style.opacity = '1';
            el.style.transform = 'none';
        });
        return;
    }
    chars.forEach((el, i) => {
        el.style.setProperty('--i', String(i));
        el.classList.add('mkt-logo__char--in');
    });
}

function setupMenubar(bar) {
    if (!bar || bar.dataset.mktNavReady === '1') {
        return;
    }
    bar.dataset.mktNavReady = '1';

    const onScroll = () => {
        const scrolled = window.scrollY > 24;
        bar.classList.toggle('is-scrolled', scrolled);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}

function setupChatbotTriggers() {
    document.querySelectorAll('[data-mkt-open-chatbot]').forEach((btn) => {
        if (btn.dataset.mktChatReady === '1') {
            return;
        }
        btn.dataset.mktChatReady = '1';
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('mkt-open-chatbot'));
        });
    });
}

export function bootMarketingBrand() {
    document.querySelectorAll('[data-mkt-logo="1"]').forEach(animateLogo);
    document.querySelectorAll('[data-mkt-menubar]').forEach(setupMenubar);
    setupChatbotTriggers();
}

export function registerMarketingBrand() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootMarketingBrand, { once: true });
    } else {
        bootMarketingBrand();
    }
}
