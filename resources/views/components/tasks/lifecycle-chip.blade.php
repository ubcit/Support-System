@props([
    'task',
])

@php
    $segments = $task->lifecycleSegments();
    $cycleLabel = $task->cycleDurationLabel();
    $createdRelative = $task->created_at?->diffForHumans();

    $mainText = $task->completed_at
        ? 'Done in '.$cycleLabel
        : 'Open '.$cycleLabel;

    $tooltipTitle = $task->completed_at ? 'Time to complete' : 'Time since created';
@endphp

<div
    class="relative inline-flex"
    x-data="{
        open: false,
        panelStyle: {},
        hideTimer: null,
        _reposition: null,
        show() {
            clearTimeout(this.hideTimer);
            this.open = true;
            this.$nextTick(() => {
                this.updatePosition();
                this.bindReposition();
            });
        },
        hide() {
            this.hideTimer = setTimeout(() => {
                this.open = false;
                this.unbindReposition();
            }, 120);
        },
        updatePosition() {
            const chip = this.$refs.chip;
            const panel = this.$refs.panel;
            if (!chip || !panel) return;

            const r = chip.getBoundingClientRect();
            const pad = 8;
            const gap = 8;
            const w = panel.offsetWidth || 224;
            const h = panel.offsetHeight || 160;
            let left = r.right - w;
            let top = r.bottom + gap;

            if (left < pad) left = pad;
            if (left + w > window.innerWidth - pad) {
                left = Math.max(pad, window.innerWidth - w - pad);
            }

            if (top + h > window.innerHeight - pad && r.top > h + gap + pad) {
                top = r.top - h - gap;
            }
            if (top < pad) top = pad;

            this.panelStyle = {
                position: 'fixed',
                top: top + 'px',
                left: left + 'px',
                zIndex: 80,
            };
        },
        bindReposition() {
            this.unbindReposition();
            this._reposition = () => this.updatePosition();
            window.addEventListener('resize', this._reposition);
            window.addEventListener('scroll', this._reposition, true);
        },
        unbindReposition() {
            if (!this._reposition) return;
            window.removeEventListener('resize', this._reposition);
            window.removeEventListener('scroll', this._reposition, true);
            this._reposition = null;
        },
    }"
    @mousedown.stop
    @click.stop
    @mouseenter="show()"
    @mouseleave="hide()"
    @focusin="show()"
    @focusout="hide()"
    @keydown.escape.window="open = false"
    draggable="false"
>
    <button
        type="button"
        x-ref="chip"
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200/60 dark:border-gray-700 shadow-xs"
        aria-label="{{ $tooltipTitle }}: {{ $mainText }}"
    >
        <x-heroicon-o-clock class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400 shrink-0" />
        <span class="text-[10px] font-mono font-bold text-gray-700 dark:text-gray-200 whitespace-nowrap">
            {{ $mainText }}
        </span>
    </button>

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="open"
            x-cloak
            @mouseenter="show()"
            @mouseleave="hide()"
            :style="panelStyle"
            class="w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl p-3"
        >
            <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                {{ $tooltipTitle }}
            </div>
            <div class="mt-2 space-y-1.5 max-h-56 overflow-y-auto overscroll-contain pr-0.5">
                @foreach($segments as $seg)
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="text-gray-700 dark:text-gray-200 font-medium min-w-0">
                            {{ $seg['label'] }}
                            @if($seg['is_current'])
                                <span class="ml-1 inline-block text-[10px] font-mono px-1.5 py-0.5 rounded-md bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 border border-brand-200/60 dark:border-brand-500/20">
                                    Now
                                </span>
                            @endif
                        </span>
                        <span class="font-mono text-[11px] text-gray-600 dark:text-gray-300 font-bold shrink-0">
                            {{ $seg['duration_label'] }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-800 text-[10px] font-mono text-gray-500 dark:text-gray-400">
                Created {{ $createdRelative }}
            </div>
        </div>
    </template>
</div>
