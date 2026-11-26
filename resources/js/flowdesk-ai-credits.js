/**
 * Keep the top-nav AI credits badge in sync after billable Nova / AI actions.
 */

function formatCredits(value) {
    const n = Math.max(0, Math.floor(Number(value) || 0));
    try {
        return n.toLocaleString();
    } catch {
        return String(n);
    }
}

function creditRoots() {
    if (typeof document === 'undefined') {
        return [];
    }

    return Array.from(document.querySelectorAll('[data-flowdesk-ai-credits]'));
}

/**
 * Set absolute remaining credits on every nav badge.
 */
export function flowdeskSetAiCreditsRemaining(remaining) {
    if (remaining === null || remaining === undefined || remaining === '') {
        return;
    }

    const value = Math.max(0, Math.floor(Number(remaining) || 0));

    creditRoots().forEach((el) => {
        if (el.getAttribute('data-unlimited') === '1') {
            return;
        }

        el.setAttribute('data-remaining', String(value));
        const num = el.querySelector('[data-flowdesk-ai-credits-remaining]');
        if (num) {
            num.textContent = formatCredits(value);
        }

        const limit = el.getAttribute('data-limit');
        if (limit !== null && limit !== '') {
            el.setAttribute(
                'aria-label',
                `${formatCredits(value)} AI credits left of ${formatCredits(limit)} this month`,
            );
        }
    });

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('flowdesk-ai-credits-changed', {
            detail: { remaining: value },
        }));
    }
}

/**
 * Subtract charged credits from the nav badge (fallback when remaining is unknown).
 */
export function flowdeskApplyAiCreditsCharged(charged) {
    const amount = Math.max(0, Math.floor(Number(charged) || 0));
    if (amount === 0) {
        return;
    }

    const root = creditRoots()[0];
    if (!root || root.getAttribute('data-unlimited') === '1') {
        return;
    }

    const current = Math.max(0, Math.floor(Number(root.getAttribute('data-remaining')) || 0));
    flowdeskSetAiCreditsRemaining(Math.max(0, current - amount));
}

/**
 * Prefer server remaining; otherwise subtract charged.
 *
 * @param {{ charged?: number|string|null, remaining?: number|string|null }} payload
 */
export function flowdeskSyncAiCredits(payload = {}) {
    const remaining = payload.remaining;
    if (remaining !== null && remaining !== undefined && remaining !== '') {
        flowdeskSetAiCreditsRemaining(remaining);

        return;
    }

    flowdeskApplyAiCreditsCharged(payload.charged);
}

/**
 * Read credit headers from a fetch Response.
 */
export function flowdeskSyncAiCreditsFromHeaders(headers) {
    if (!headers || typeof headers.get !== 'function') {
        return;
    }

    const charged = headers.get('X-AI-Credits-Charged');
    const remaining = headers.get('X-AI-Credits-Remaining');
    flowdeskSyncAiCredits({ charged, remaining });
}

/**
 * Read credit fields from a JSON API body.
 */
export function flowdeskSyncAiCreditsFromJson(data) {
    if (!data || typeof data !== 'object') {
        return;
    }

    flowdeskSyncAiCredits({
        charged: data.ai_credits_charged ?? data.credits_charged,
        remaining: data.ai_credits_remaining ?? data.credits_remaining,
    });
}

if (typeof window !== 'undefined') {
    window.flowdeskSyncAiCredits = flowdeskSyncAiCredits;
    window.flowdeskApplyAiCreditsCharged = flowdeskApplyAiCreditsCharged;
}
