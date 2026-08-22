@props([
    'value' => null,
    'urgency' => null, // overdue | today | tomorrow | null
    'overdue' => false, // legacy; prefer urgency
    'clearable' => true,
    'align' => 'right', // right | left | center
    'wireAction' => null,
    'wireParams' => [],
    'label' => null,
    'embedded' => false, // panel only (no pill trigger) — for bulk toolbar etc.
])

@php
    use Modules\Tasks\Models\Task;

    $display = $label;
    if ($display === null) {
        $display = $value
            ? \Illuminate\Support\Carbon::parse($value)->format('M d')
            : 'Due date';
    }

    $alignClass = match ($align) {
        'left' => 'left-0',
        'center' => 'left-1/2 -translate-x-1/2',
        default => 'right-0',
    };

    $resolvedUrgency = $urgency ?? ($overdue ? 'overdue' : ($value ? Task::urgencyForDueDate($value) : null));
    $triggerClass = Task::toneClassesForUrgency($resolvedUrgency, 'pill', (bool) $value);
@endphp

<div
    {{ $attributes->class($embedded ? 'shrink-0' : 'relative shrink-0') }}
    x-data="{
        open: @js((bool) $embedded),
        value: @js($value),
        wireAction: @js($wireAction),
        wireParams: @js($wireParams),
        embedded: @js((bool) $embedded),
        fp: null,
        init() {
            if (this.embedded) {
                this.$nextTick(() => this.mountCalendar());
                return;
            }
            this.$watch('open', (isOpen) => {
                if (isOpen) {
                    this.$nextTick(() => this.mountCalendar());
                } else {
                    this.destroyCalendar();
                }
            });
        },
        mountCalendar() {
            if (this.fp || ! this.$refs.calendar || typeof flatpickr === 'undefined') return;
            this.fp = flatpickr(this.$refs.calendar, {
                inline: true,
                static: true,
                monthSelectorType: 'static',
                dateFormat: 'Y-m-d',
                defaultDate: this.value || null,
                onChange: (selectedDates, dateStr) => {
                    this.pick(dateStr || null);
                },
            });
        },
        destroyCalendar() {
            if (this.fp) {
                this.fp.destroy();
                this.fp = null;
            }
        },
        async pick(dateStr) {
            const next = dateStr || null;
            this.value = next;
            if (this.wireAction) {
                await $wire[this.wireAction](...this.wireParams, next);
            }
            this.$dispatch('due-date-selected', { date: next });
            if (! this.embedded) {
                this.open = false;
            }
        },
        formatPreset(days) {
            const d = new Date();
            d.setHours(0, 0, 0, 0);
            d.setDate(d.getDate() + days);
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },
    }"
    x-on:mousedown.stop
    x-on:click.stop
    x-on:dragstart.prevent.stop
    draggable="false"
>
    @unless($embedded)
        <button
            type="button"
            @click.stop="open = !open"
            @mousedown.stop
            class="text-[10px] font-mono shrink-0 px-2 py-1 rounded-lg flex items-center gap-1 transition-all border {{ $triggerClass }}"
            title="{{ $value ? 'Due: '.\Illuminate\Support\Carbon::parse($value)->format('M d, Y') : 'Set due date' }}"
        >
            <x-heroicon-o-calendar class="w-3.5 h-3.5 shrink-0 opacity-70"/>
            <span>{{ $display }}</span>
        </button>
    @endunless

    <div
        x-show="open"
        @unless($embedded)
            @click.outside="open = false"
            x-transition.opacity.duration.100ms
        @endunless
        x-cloak
        @class([
            'w-[280px] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl overflow-hidden',
            "absolute {$alignClass} mt-1 z-50" => ! $embedded,
        ])
    >
        <div class="grid grid-cols-2 gap-1 p-2 border-b border-gray-100 dark:border-gray-700/80">
            <button type="button" @click="pick(formatPreset(0))" class="rounded-lg px-2 py-1.5 text-[11px] font-semibold text-yellow-700 dark:text-yellow-400 hover:bg-yellow-50 dark:hover:bg-yellow-500/10 text-left">
                Today
            </button>
            <button type="button" @click="pick(formatPreset(1))" class="rounded-lg px-2 py-1.5 text-[11px] font-semibold text-cyan-700 dark:text-cyan-400 hover:bg-cyan-50 dark:hover:bg-cyan-500/10 text-left">
                Tomorrow
            </button>
            <button type="button" @click="pick(formatPreset(7))" class="rounded-lg px-2 py-1.5 text-[11px] font-semibold text-gray-700 dark:text-gray-200 hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-500/10 dark:hover:text-brand-400 text-left">
                Next week
            </button>
            @if($clearable)
                <button type="button" @click="pick(null)" class="rounded-lg px-2 py-1.5 text-[11px] font-semibold text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 text-left">
                    Clear
                </button>
            @endif
        </div>
        <div class="p-1 custom-datepicker due-date-popover-calendar" wire:ignore>
            <div x-ref="calendar"></div>
        </div>
    </div>
</div>
