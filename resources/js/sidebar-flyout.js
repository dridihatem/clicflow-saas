/**
 * Floating flyout panels for the workspace sidebar (hover + click, RTL-aware).
 * Panel is teleported to <body> so backdrop-filter on the sidebar does not trap fixed positioning.
 *
 * Vertical placement:
 * - Parent near the top → panel top-aligns with the trigger (default).
 * - Parent near the bottom → panel bottom-aligns with the trigger / viewport
 *   so the submenu sits on the last visible line and is never clipped at the top.
 */
export function registerSidebarFlyout(Alpine) {
    Alpine.data('flowdeskSidebarFlyout', () => ({
        open: false,
        panelStyle: '',
        closeTimer: null,
        _activeTrigger: null,

        init() {
            this._onViewportChange = () => {
                if (this.open && this._activeTrigger) {
                    this.positionPanel(this._activeTrigger);
                }
            };
            window.addEventListener('resize', this._onViewportChange, { passive: true });
            window.addEventListener('scroll', this._onViewportChange, { passive: true, capture: true });
        },

        destroy() {
            window.removeEventListener('resize', this._onViewportChange);
            window.removeEventListener('scroll', this._onViewportChange, true);
        },

        openFlyout(el) {
            clearTimeout(this.closeTimer);
            this._activeTrigger = el;
            this.positionPanel(el);
            this.open = true;
            // Remeasure with the real panel height after Alpine paints it.
            this.$nextTick?.(() => {
                requestAnimationFrame(() => this.positionPanel(el));
            });
        },

        toggle(el) {
            if (this.open) {
                this.close();
            } else {
                this.openFlyout(el);
            }
        },

        positionPanel(el) {
            if (!el) {
                return;
            }

            const rect = el.getBoundingClientRect();
            const gap = 10;
            const panelWidth = 248;
            const margin = 8;
            const preferredMaxHeight = Math.min(window.innerHeight * 0.7, 384);
            const rtl = document.documentElement.dir === 'rtl';

            let left = rtl ? rect.left - panelWidth - gap : rect.right + gap;
            left = Math.max(margin, Math.min(left, window.innerWidth - panelWidth - margin));

            const panel = this.$refs?.panel;
            const measuredHeight = panel && this.open
                ? Math.min(panel.scrollHeight || 0, preferredMaxHeight)
                : 0;
            const estimatedHeight = measuredHeight > 0
                ? measuredHeight
                : Math.min(preferredMaxHeight, 160);

            const spaceBelow = window.innerHeight - rect.top - margin;
            const spaceAboveFromTriggerBottom = rect.bottom - margin;
            const fitsTopAligned = estimatedHeight <= spaceBelow;

            let top;
            let maxPanelHeight;

            if (fitsTopAligned) {
                // Parent in the upper/middle area: align panel top with trigger top.
                top = Math.max(margin, rect.top);
                maxPanelHeight = Math.min(preferredMaxHeight, window.innerHeight - top - margin);
            } else {
                // Parent near the bottom: pin panel bottom to trigger bottom (or viewport),
                // grow upward so the top is never clipped off-screen.
                const bottom = Math.min(rect.bottom, window.innerHeight - margin);
                maxPanelHeight = Math.min(
                    preferredMaxHeight,
                    Math.max(96, Math.min(estimatedHeight, spaceAboveFromTriggerBottom, bottom - margin)),
                );
                top = bottom - maxPanelHeight;
                if (top < margin) {
                    top = margin;
                    maxPanelHeight = Math.max(96, bottom - margin);
                }
            }

            this.panelStyle = `top:${Math.round(top)}px;left:${Math.round(left)}px;width:${panelWidth}px;max-height:${Math.round(maxPanelHeight)}px`;
        },

        scheduleClose() {
            this.closeTimer = setTimeout(() => {
                this.open = false;
                this._activeTrigger = null;
            }, 140);
        },

        cancelClose() {
            clearTimeout(this.closeTimer);
        },

        close() {
            clearTimeout(this.closeTimer);
            this.open = false;
            this._activeTrigger = null;
        },
    }));
}
