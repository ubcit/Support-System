@props([
    'id' => null,
    'mode' => 'single', // 'single', 'multiple', 'range', 'time'
    'defaultDate' => null,
    'label' => null,
    'placeholder' => 'Select date',
    'name' => null,
    'dateFormat' => 'Y-m-d',
    'size' => 'md', // md | sm
    'allowClear' => false,
])

@php
    $id = $id ?? 'datepicker-'.uniqid();
    $wireModel = $attributes->wire('model');
    $isLive = $wireModel && method_exists($wireModel, 'hasModifier') && $wireModel->hasModifier('live');
    $inputClass = $size === 'sm'
        ? 'h-9 w-full rounded-xl border appearance-none px-3 py-1.5 pr-9 text-xs shadow-theme-xs'
        : 'h-11 w-full rounded-lg border appearance-none px-4 py-2.5 pr-10 text-sm shadow-theme-xs';
@endphp

<div
    {{ $attributes->whereDoesntStartWith('wire:model')->class('w-full') }}
    x-data="{
        flatpickrInstance: null,
        value: @if($wireModel) @if($isLive) @entangle($wireModel).live @else @entangle($wireModel) @endif @else @js($defaultDate) @endif,
        init() {
            this.$nextTick(() => this.mount());
            this.$watch('value', (next) => {
                if (! this.flatpickrInstance) return;
                const current = this.flatpickrInstance.input.value || null;
                if ((next || null) !== (current || null)) {
                    this.flatpickrInstance.setDate(next || null, false);
                }
            });
        },
        mount() {
            if (this.flatpickrInstance || typeof flatpickr === 'undefined') return;
            this.flatpickrInstance = flatpickr(this.$refs.dateInput, {
                mode: @js($mode),
                static: true,
                monthSelectorType: 'static',
                dateFormat: @js($dateFormat),
                defaultDate: this.value || null,
                allowInput: false,
                clickOpens: true,
                onChange: (selectedDates, dateStr) => {
                    this.value = dateStr || null;
                    this.$dispatch('date-change', { dateStr: dateStr || null });
                },
            });
        },
        clear() {
            this.value = null;
            if (this.flatpickrInstance) {
                this.flatpickrInstance.clear();
            }
            this.$dispatch('date-change', { dateStr: null });
        },
        destroy() {
            if (this.flatpickrInstance) {
                this.flatpickrInstance.destroy();
                this.flatpickrInstance = null;
            }
        }
    }"
    x-init="init()"
    x-on:destroy="destroy()"
>
    @if($label)
        <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
            {{ $label }}
        </label>
    @endif

    <div class="relative custom-datepicker">
        <input
            x-ref="dateInput"
            type="text"
            id="{{ $id }}"
            name="{{ $name }}"
            placeholder="{{ $placeholder }}"
            readonly
            class="{{ $inputClass }} placeholder:text-gray-400 focus:outline-hidden focus:ring-3 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 bg-transparent text-gray-800 border-gray-300 focus:border-brand-300 focus:ring-brand-500/20 dark:border-gray-700 dark:focus:border-brand-800 cursor-pointer"
            autocomplete="off"
        />
        <span class="absolute text-gray-500 -translate-y-1/2 pointer-events-none right-3 top-1/2 dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="{{ $size === 'sm' ? 'size-4' : 'size-5' }}">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V9V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V9V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM8 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H16H8ZM19.25 9.75H4.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75Z" fill="currentColor"></path>
            </svg>
        </span>
        @if($allowClear)
            <button
                type="button"
                x-show="value"
                x-cloak
                @click.prevent="clear()"
                class="absolute right-9 top-1/2 -translate-y-1/2 p-0.5 rounded text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                title="Clear date"
                aria-label="Clear date"
            >
                <x-heroicon-m-x-mark class="w-3.5 h-3.5"/>
            </button>
        @endif
    </div>
</div>
