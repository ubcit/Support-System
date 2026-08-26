/**
 * Shared Alpine helper for dropdowns that must escape overflow containers
 * (tables, board columns, etc.) via body teleport + fixed positioning.
 */
export function createFloatingPanelState(config = {}) {
    const align = config.align || 'left';
    const width = config.width || 224;
    const menuHeight = config.menuHeight || 260;
    const gap = config.gap ?? 6;
    const zIndex = config.zIndex ?? 100000;
    // Livewire morph briefly zeros the trigger rect; require sustained absence before closing.
    const missingTriggerCloseMs = config.missingTriggerCloseMs ?? 180;

    return {
        open: false,
        panelStyle: {},
        _reposition: null,
        _missingTriggerSince: null,
        _missingTriggerTimer: null,

        openPanel(afterOpen) {
            this.open = true;
            this._missingTriggerSince = null;
            this.clearMissingTriggerTimer();
            this.$nextTick(() => {
                this.updatePosition();
                this.bindReposition();
                if (typeof afterOpen === 'function') {
                    afterOpen();
                }
            });
        },

        closePanel() {
            this.open = false;
            this._missingTriggerSince = null;
            this.clearMissingTriggerTimer();
            this.unbindReposition();
        },

        togglePanel(afterOpen) {
            if (this.open) {
                this.closePanel();
            } else {
                this.openPanel(afterOpen);
            }
        },

        clearMissingTriggerTimer() {
            if (this._missingTriggerTimer) {
                clearTimeout(this._missingTriggerTimer);
                this._missingTriggerTimer = null;
            }
        },

        scheduleCloseIfTriggerStillMissing() {
            this.clearMissingTriggerTimer();
            this._missingTriggerTimer = setTimeout(() => {
                this._missingTriggerTimer = null;
                if (! this.open) {
                    return;
                }
                const btn = this.$refs.trigger;
                if (! btn || ! btn.isConnected) {
                    this.closePanel();
                    return;
                }
                const r = btn.getBoundingClientRect();
                if (r.width === 0 && r.height === 0) {
                    this.closePanel();
                } else {
                    this._missingTriggerSince = null;
                    this.updatePosition();
                }
            }, missingTriggerCloseMs);
        },

        updatePosition() {
            const btn = this.$refs.trigger;
            if (!btn) {
                return;
            }

            const r = btn.getBoundingClientRect();
            if (r.width === 0 && r.height === 0) {
                // Skip this frame during Livewire morph; only close if still gone shortly after.
                if (this.open) {
                    if (! this._missingTriggerSince) {
                        this._missingTriggerSince = Date.now();
                    }
                    this.scheduleCloseIfTriggerStillMissing();
                }
                return;
            }

            this._missingTriggerSince = null;
            this.clearMissingTriggerTimer();

            const spaceBelow = window.innerHeight - r.bottom;
            const openUp = spaceBelow < menuHeight && r.top > spaceBelow;
            const pad = 8;
            let left;

            if (align === 'right') {
                left = r.right - width;
            } else if (align === 'center') {
                left = r.left + r.width / 2 - width / 2;
            } else {
                left = r.left;
            }

            if (left < pad) {
                left = pad;
            }
            if (left + width > window.innerWidth - pad) {
                left = Math.max(pad, window.innerWidth - width - pad);
            }

            this.panelStyle = {
                position: 'fixed',
                top: openUp ? 'auto' : `${r.bottom + gap}px`,
                bottom: openUp ? `${window.innerHeight - r.top + gap}px` : 'auto',
                left: `${left}px`,
                width: `${width}px`,
                zIndex,
            };
        },

        bindReposition() {
            this.unbindReposition();
            this._reposition = () => this.updatePosition();
            window.addEventListener('resize', this._reposition);
            window.addEventListener('scroll', this._reposition, true);
        },

        unbindReposition() {
            this.clearMissingTriggerTimer();
            if (!this._reposition) {
                return;
            }
            window.removeEventListener('resize', this._reposition);
            window.removeEventListener('scroll', this._reposition, true);
            this._reposition = null;
        },

        onOutside(event) {
            if (this.$refs.trigger?.contains(event.target)) {
                return;
            }
            this.closePanel();
        },

        destroy() {
            this.unbindReposition();
        },
    };
}

window.createFloatingPanelState = createFloatingPanelState;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('floatingPanel', (config = {}) => createFloatingPanelState(config));
});
