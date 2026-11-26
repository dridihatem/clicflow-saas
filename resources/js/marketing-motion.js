/**
 * Marketing Motion (https://motion.dev) — scroll, reveal, hover, parallax.
 * Vanilla JS API for Blade pages.
 */
import { animate, hover, inView, scroll, scrollInfo, stagger } from 'motion';

const EASE = [0.22, 1, 0.36, 1];
const seen = new WeakSet();

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function revealNow(els) {
    els.forEach((el) => {
        if (!el) {
            return;
        }
        el.style.opacity = '1';
        el.style.transform = 'none';
        el.style.filter = 'none';
        el.classList.add('is-mkt-revealed');
    });
}

function bootHero() {
    const hero = document.querySelector('[data-mkt-hero]');
    if (!hero) {
        return;
    }

    const copy = [...hero.querySelectorAll('[data-mkt-hero-item]')];
    const visual = hero.querySelector('[data-mkt-hero-visual]');

    if (prefersReducedMotion()) {
        revealNow([...copy, visual]);
        return;
    }

    if (copy.length) {
        animate(
            copy,
            { opacity: [0, 1], y: [28, 0], filter: ['blur(6px)', 'blur(0px)'] },
            { delay: stagger(0.07, { startDelay: 0.05 }), duration: 0.7, easing: EASE },
        );
    }

    if (visual) {
        animate(
            visual,
            { opacity: [0, 1], scale: [0.94, 1], y: [18, 0] },
            { delay: 0.22, duration: 0.85, easing: EASE },
        );
    }
}

function bootReveals() {
    const targets = document.querySelectorAll('[data-mkt-reveal]');
    if (!targets.length) {
        return;
    }

    if (prefersReducedMotion()) {
        revealNow([...targets]);
        return;
    }

    inView(
        '[data-mkt-reveal]',
        (element) => {
            if (seen.has(element)) {
                return;
            }
            seen.add(element);
            const delay = Number(element.getAttribute('data-mkt-delay') || 0);
            animate(
                element,
                { opacity: [0, 1], y: [26, 0] },
                { duration: 0.65, delay, easing: EASE },
            ).finished.then(() => {
                element.classList.add('is-mkt-revealed');
            });
        },
        { amount: 0.22, margin: '0px 0px -8% 0px' },
    );
}

function bootStaggerGroups() {
    document.querySelectorAll('[data-mkt-stagger]').forEach((group) => {
        const kids = group.querySelectorAll('[data-mkt-stagger-item]');
        const items = kids.length ? kids : group.querySelectorAll(':scope > *');
        if (!items.length) {
            return;
        }

        if (prefersReducedMotion()) {
            revealNow([...items]);
            return;
        }

        items.forEach((el) => {
            el.style.opacity = '0';
        });

        inView(
            group,
            (element) => {
                if (seen.has(element)) {
                    return;
                }
                seen.add(element);
                animate(
                    items,
                    { opacity: [0, 1], y: [22, 0] },
                    { delay: stagger(0.08), duration: 0.55, easing: EASE },
                );
            },
            { amount: 0.2 },
        );
    });
}

function bootCardHovers() {
    if (prefersReducedMotion()) {
        return;
    }

    hover('[data-mkt-hover]', (element) => {
        const enter = animate(
            element,
            { y: -5, scale: 1.01 },
            { type: 'spring', stiffness: 380, damping: 24 },
        );
        return () => {
            enter.stop();
            animate(element, { y: 0, scale: 1 }, { type: 'spring', stiffness: 380, damping: 28 });
        };
    });
}

function bootAmbientParallax() {
    if (prefersReducedMotion()) {
        return;
    }

    document.querySelectorAll('[data-mkt-parallax]').forEach((el) => {
        const strength = Number(el.getAttribute('data-mkt-parallax') || 36);
        scroll(animate(el, { y: [strength, -strength] }, { ease: 'linear' }));
    });
}

function bootNav() {
    const nav = document.querySelector('[data-mkt-nav]');
    if (!nav) {
        return;
    }

    scrollInfo(({ y }) => {
        nav.classList.toggle('is-scrolled', y.current > 18);
    });
}

function bootCtaPulse() {
    if (prefersReducedMotion()) {
        return;
    }

    hover('.mkt-btn-primary', (element) => {
        const enter = animate(
            element,
            { scale: 1.03, y: -2 },
            { type: 'spring', stiffness: 420, damping: 22 },
        );
        return () => {
            enter.stop();
            animate(element, { scale: 1, y: 0 }, { type: 'spring', stiffness: 420, damping: 26 });
        };
    });
}

function bootFlowVisual() {
    const visual = document.querySelector('.mkt-flow-visual');
    if (!visual || prefersReducedMotion()) {
        return;
    }

    inView(
        visual,
        (element) => {
            if (seen.has(element)) {
                return;
            }
            seen.add(element);
            animate(
                element,
                {
                    boxShadow: [
                        '0 12px 40px -24px rgba(79, 70, 229, 0.18)',
                        '0 22px 56px -20px rgba(124, 92, 252, 0.32)',
                        '0 12px 40px -24px rgba(79, 70, 229, 0.18)',
                    ],
                },
                { duration: 2.8, repeat: Infinity, easing: 'ease-in-out' },
            );
        },
        { amount: 0.35 },
    );
}

export function bootMarketingMotion() {
    document.documentElement.classList.add('mkt-motion');
    bootHero();
    bootReveals();
    bootStaggerGroups();
    bootCardHovers();
    bootAmbientParallax();
    bootNav();
    bootCtaPulse();
    bootFlowVisual();
}

export function registerMarketingMotion() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootMarketingMotion, { once: true });
    } else {
        bootMarketingMotion();
    }
}
