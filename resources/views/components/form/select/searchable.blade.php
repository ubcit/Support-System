@props([
    'options' => [],
    'placeholder' => 'Select...',
    'searchPlaceholder' => 'Search...',
    'multiple' => false,
    'label' => null,
    'size' => 'md',
    'nullable' => true,
    'emptyOption' => null,
    'emptyListLabel' => 'No options yet',
])

@php
    $normalized = collect($options)->map(function ($label, $value) {
        if (is_array($label)) {
            return [
                'value' => (string) ($label['value'] ?? $label['id'] ?? ''),
                'label' => (string) ($label['label'] ?? $label['name'] ?? ''),
            ];
        }

        return [
            'value' => (string) $value,
            'label' => (string) $label,
        ];
    })->values()->all();

    $optionsKey = 'select-opts-'.md5(json_encode($normalized));
    $wireModel = $attributes->wire('model');
    $isLive = $wireModel && method_exists($wireModel, 'hasModifier') && $wireModel->hasModifier('live');
    $controlClass = $size === 'sm'
        ? 'min-h-9 px-3 py-1.5 text-xs rounded-xl'
        : 'min-h-11 px-3.5 py-2 text-sm rounded-lg';
@endphp

<div
    wire:key="{{ $optionsKey }}"
    {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'relative w-full']) }}
    x-data="{
        open: false,
        search: '',
        multiple: @js((bool) $multiple),
        options: @js($normalized),
        emptyOption: @js($emptyOption),
        emptyListLabel: @js($emptyListLabel),
        selected: @if($isLive) @entangle($wireModel).live @else @entangle($wireModel) @endif,
        dropdownStyle: {},
        _reposition: null,
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.options;
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        choose(value) {
            const v = String(value);
            if (this.multiple) {
                let next = Array.isArray(this.selected) ? this.selected.map(String) : [];
                if (next.includes(v)) {
                    next = next.filter(i => i !== v);
                } else {
                    next = [...next, v];
                }
                this.selected = next;
            } else {
                this.selected = this.selectedValues.includes(v) ? null : v;
                this.closeDropdown();
            }
        },
        clear() {
            this.selected = this.multiple ? [] : null;
            this.closeDropdown();
        },
        isSelected(value) {
            return this.selectedValues.includes(String(value));
        },
        get selectedValues() {
            if (this.multiple) {
                return Array.isArray(this.selected) ? this.selected.map(String) : [];
            }
            return this.selected === null || this.selected === undefined || this.selected === '' ? [] : [String(this.selected)];
        },
        get selectedLabels() {
            return this.options.filter(o => this.selectedValues.includes(String(o.value)));
        },
        get displayLabel() {
            if (this.multiple) {
                if (!this.selectedLabels.length) return null;
                if (this.selectedLabels.length === 1) return this.selectedLabels[0].label;
                return this.selectedLabels.length + ' selected';
            }
            if (this.selectedLabels.length) return this.selectedLabels[0].label;
            if (this.emptyOption && (this.selected === null || this.selected === '' || this.selected === undefined)) {
                return this.emptyOption;
            }
            return null;
        },
        get showEmptyOption() {
            if (this.multiple || !this.emptyOption) return false;
            const q = this.search.trim().toLowerCase();
            return !q || this.emptyOption.toLowerCase().includes(q);
        },
        get showNoMatches() {
            return this.filtered.length === 0 && !this.showEmptyOption;
        },
        get emptyMessage() {
            if (this.options.length === 0 && !this.search.trim()) {
                return this.emptyListLabel || 'No options yet';
            }
            return 'No matches found';
        },
        updatePosition() {
            const btn = this.$refs.trigger;
            if (!btn) return;
            const r = btn.getBoundingClientRect();
            const menuHeight = 260;
            const gap = 6;
            const spaceBelow = window.innerHeight - r.bottom;
            const openUp = spaceBelow < menuHeight && r.top > spaceBelow;
            this.dropdownStyle = {
                position: 'fixed',
                top: openUp ? 'auto' : (r.bottom + gap) + 'px',
                bottom: openUp ? (window.innerHeight - r.top + gap) + 'px' : 'auto',
                left: r.left + 'px',
                width: r.width + 'px',
                zIndex: 100000,
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
        openDropdown() {
            this.open = true;
            this.$nextTick(() => {
                this.updatePosition();
                this.bindReposition();
                this.$refs.searchInput?.focus();
            });
        },
        closeDropdown() {
            this.open = false;
            this.search = '';
            this.unbindReposition();
        },
        onOutside(event) {
            if (this.$refs.trigger?.contains(event.target)) return;
            this.closeDropdown();
        },
    }"
    @keydown.escape.window="closeDropdown()"
>
    @if ($label)
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }}</label>
    @endif

    <button
        type="button"
        x-ref="trigger"
        @click="open ? closeDropdown() : openDropdown()"
        class="{{ $controlClass }} shadow-theme-xs flex w-full cursor-pointer items-center justify-between gap-2 border border-gray-300 bg-white text-left transition focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        :class="open ? 'border-brand-300 ring-3 ring-brand-500/10' : ''"
    >
        <span class="min-w-0 flex-1 truncate" :class="displayLabel ? 'text-gray-800 dark:text-white/90' : 'text-gray-400 dark:text-gray-500'">
            <span x-text="displayLabel || @js($placeholder)"></span>
        </span>
        <span class="flex shrink-0 items-center gap-1">
            @if ($nullable)
                <span
                    x-show="selectedValues.length > 0"
                    x-cloak
                    @click.stop="clear()"
                    class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-200"
                    title="Clear"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </span>
            @endif
            <svg class="h-4 w-4 text-gray-400 transition" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </button>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-ref="panel"
            @click.outside="onOutside($event)"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            :style="dropdownStyle"
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="border-b border-gray-100 p-2 dark:border-gray-800">
                <div class="relative">
                    <svg class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m21 21-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z"/>
                    </svg>
                    <input
                        type="text"
                        x-ref="searchInput"
                        x-model="search"
                        @keydown.enter.prevent="filtered.length === 1 && choose(filtered[0].value)"
                        placeholder="{{ $searchPlaceholder }}"
                        class="h-10 w-full rounded-lg border border-gray-200 bg-gray-50 py-2 pr-3 pl-9 text-sm text-gray-800 outline-none focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90"
                    />
                </div>
            </div>

            <div class="max-h-56 overflow-y-auto py-1">
                @if ($emptyOption && ! $multiple)
                    <button
                        type="button"
                        x-show="showEmptyOption"
                        @click="clear()"
                        class="flex w-full items-center px-3 py-2.5 text-left text-sm text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5"
                        :class="selectedValues.length === 0 ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' : ''"
                    >
                        {{ $emptyOption }}
                    </button>
                @endif

                <template x-for="option in filtered" :key="option.value">
                    <button
                        type="button"
                        @click="choose(option.value)"
                        class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-white/5"
                        :class="isSelected(option.value) ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'text-gray-800 dark:text-white/90'"
                    >
                        <span class="truncate" x-text="option.label"></span>
                        <svg x-show="isSelected(option.value)" class="h-4 w-4 shrink-0 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </button>
                </template>

                <div x-show="showNoMatches" class="px-3 py-6 text-center text-sm text-gray-400" x-text="emptyMessage"></div>
            </div>
        </div>
    </template>
</div>
