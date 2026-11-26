import data from '../icons/clicflow.json';

const SKIP_CLASSES = new Set([
    'fa-solid', 'fa-regular', 'fa-brands', 'fa-light', 'fa-thin', 'fa-duotone',
    'fa-sharp', 'fa-fw', 'fa-spin', 'fa-pulse', 'fa-beat', 'fa-fade', 'fa-sm',
    'fa-lg', 'fa-xl', 'fa-2x', 'fa-3x', 'fa-4x',
]);

function resolve(className) {
    const aliases = data.aliases || {};
    const icons = data.icons || {};
    const parts = String(className || '').split(/\s+/);
    const candidates = [];
    for (const p of parts) {
        const low = p.toLowerCase();
        if (!low || SKIP_CLASSES.has(low) || !low.startsWith('fa-')) continue;
        candidates.push(low, low.replace(/^fa-/, ''), `fa-solid ${low}`, `fa-regular ${low}`);
    }
    candidates.push(String(className || '').toLowerCase().trim());
    for (const c of candidates) {
        if (aliases[c]) return aliases[c];
        if (icons[c]) return c;
    }
    return null;
}

function svgFor(id) {
    const icon = (data.icons || {})[id];
    if (!icon) return '';
    const parts = [];
    let i = 0;
    for (const d of icon.paths || []) {
        i += 1;
        const delay = Math.min(0.28, (i - 1) * 0.06);
        parts.push(`<path class="cf-icon-stroke" pathLength="1" style="animation-delay:${delay}s" d="${escapeAttr(d)}"/>`);
    }
    for (const c of icon.circles || []) {
        i += 1;
        const delay = Math.min(0.28, (i - 1) * 0.06);
        const fill = !!c.fill;
        const cls = fill ? 'cf-icon-fill' : 'cf-icon-stroke';
        const fillAttr = fill ? 'fill="currentColor" stroke="none"' : 'fill="none" pathLength="1"';
        parts.push(`<circle class="${cls}" ${fillAttr} style="animation-delay:${delay}s" cx="${c.cx}" cy="${c.cy}" r="${c.r}"/>`);
    }
    return `<svg class="cf-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">${parts.join('')}</svg>`;
}

function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

function leftoverClasses(className) {
    return String(className || '')
        .split(/\s+/)
        .filter((c) => c && !c.startsWith('fa-') && c !== 'fas' && c !== 'far' && c !== 'fab')
        .join(' ');
}

export function enhanceCfIcons(root = document) {
    const nodes = root.querySelectorAll ? root.querySelectorAll('i[class*="fa-"]') : [];
    nodes.forEach((el) => {
        if (!(el instanceof Element)) return;
        if (el.closest('.cf-icon')) return;
        if (el.dataset.cfIconSkip === '1') return;
        if (el.classList.contains('fa-spin') || el.classList.contains('fa-pulse')) return;
        const id = resolve(el.className);
        if (!id) return; // keep Font Awesome as fallback
        const wrap = document.createElement('span');
        const extra = leftoverClasses(el.className);
        wrap.className = `cf-icon cf-icon--plain cf-icon--md cf-icon--swapped${extra ? ` ${extra}` : ''}`;
        wrap.setAttribute('aria-hidden', el.getAttribute('aria-hidden') || 'true');
        wrap.innerHTML = svgFor(id);
        el.replaceWith(wrap);
    });
}

let scheduled = false;
function schedule(root) {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => {
        scheduled = false;
        enhanceCfIcons(root || document);
    });
}

export function registerClicflowIcons() {
    const run = () => {
        enhanceCfIcons(document);
        if (!window.__cfIconObserver) {
            window.__cfIconObserver = new MutationObserver((mutations) => {
                for (const m of mutations) {
                    for (const n of m.addedNodes) {
                        if (!(n instanceof Element)) continue;
                        if (n.classList?.contains('cf-icon') || n.closest?.('.cf-icon')) continue;
                        if (n.matches?.('i[class*="fa-"]') || n.querySelector?.('i[class*="fa-"]')) {
                            schedule(document);
                            return;
                        }
                    }
                }
            });
            window.__cfIconObserver.observe(document.body, { childList: true, subtree: true });
        }
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        run();
    }
}
