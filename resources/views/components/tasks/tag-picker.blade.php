@props([
    'options' => [],
    'tagModels' => null,
    'colors' => [
        '#6B7280',
        '#EF4444',
        '#F59E0B',
        '#10B981',
        '#3B82F6',
        '#8B5CF6',
        '#EC4899',
    ],
    'label' => 'Tags',
    'size' => 'md',
    'idsModel' => 'formTagIds',
    'createMethod' => 'createFormTag',
    'newColorModel' => 'formNewTagColor',
    'selectedColor' => '#6B7280',
    'selectedIds' => [],
])

@php
    $tagsById = collect($tagModels ?? [])->keyBy(fn ($t) => (string) $t->id);
    $optionList = collect($options)->map(function ($label, $value) use ($tagsById) {
        $id = (string) $value;
        $tag = $tagsById->get($id);

        return [
            'value' => $id,
            'label' => is_array($label) ? (string) ($label['label'] ?? $label['name'] ?? '') : (string) $label,
            'color' => $tag?->color ?? '#6B7280',
        ];
    })->values()->all();

    $selected = collect($selectedIds)->map(fn ($id) => (string) $id)->values()->all();
    $pickerKey = 'tag-picker-'.md5(json_encode($optionList).'|'.implode(',', $selected));
    $inputClass = $size === 'sm' ? 'h-7 text-xs' : 'h-8 text-sm';
@endphp

<div
    wire:key="{{ $pickerKey }}"
    class="space-y-2"
    x-data="{
        open: false,
        search: '',
        options: @js($optionList),
        selected: @entangle($idsModel),
        newColor: @entangle($newColorModel).live,
        createMethod: @js($createMethod),
        get selectedValues() {
            return Array.isArray(this.selected) ? this.selected.map(String) : [];
        },
        get selectedTags() {
            return this.options.filter(o => this.selectedValues.includes(String(o.value)));
        },
        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.options;
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        get exactMatch() {
            const q = this.search.trim().toLowerCase();
            if (!q) return null;
            return this.options.find(o => o.label.toLowerCase() === q) || null;
        },
        get canCreate() {
            return this.search.trim().length > 0 && !this.exactMatch;
        },
        toggle(value) {
            const v = String(value);
            let next = this.selectedValues.slice();
            if (next.includes(v)) {
                next = next.filter(i => i !== v);
            } else {
                next.push(v);
            }
            this.selected = next;
        },
        remove(value) {
            this.selected = this.selectedValues.filter(i => i !== String(value));
        },
        async onEnter() {
            if (this.exactMatch) {
                if (!this.selectedValues.includes(String(this.exactMatch.value))) {
                    this.toggle(this.exactMatch.value);
                }
                this.search = '';
                return;
            }
            if (this.canCreate) {
                const name = this.search.trim();
                this.search = '';
                await $wire.call(this.createMethod, name);
            }
        },
    }"
>
    @if($label)
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $label }}</label>
    @endif

    <div
        class="min-h-11 rounded-lg border border-gray-300 bg-white px-2 py-1.5 dark:border-gray-700 dark:bg-gray-900"
        @click.outside="open = false"
    >
        <div class="flex flex-wrap items-center gap-1.5">
            <template x-for="tag in selectedTags" :key="tag.value">
                <span
                    class="inline-flex items-center gap-1 rounded-md border border-black/5 px-1.5 py-0.5 text-[11px] font-semibold leading-none dark:border-white/10"
                    :style="`background-color: ${tag.color}22; color: ${tag.color};`"
                >
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="`background-color: ${tag.color};`"></span>
                    <span class="max-w-[8rem] truncate" x-text="tag.label"></span>
                    <button type="button" @click.stop="remove(tag.value)" class="rounded hover:opacity-70" title="Remove tag" aria-label="Remove tag">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </span>
            </template>

            <input
                type="text"
                x-model="search"
                @focus="open = true"
                @keydown.enter.prevent="onEnter()"
                @keydown.escape.prevent="open = false; search = ''"
                maxlength="50"
                placeholder="Search or create tag…"
                class="{{ $inputClass }} min-w-[8rem] flex-1 border-0 bg-transparent px-1 text-gray-800 outline-none placeholder:text-gray-400 dark:text-white/90"
            />
        </div>

        <div x-show="open" x-cloak class="mt-2 max-h-44 overflow-y-auto border-t border-gray-100 pt-2 dark:border-gray-800">
            <template x-for="option in filtered" :key="option.value">
                <button
                    type="button"
                    @click="toggle(option.value)"
                    class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs hover:bg-gray-50 dark:hover:bg-white/5"
                >
                    <span
                        class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-semibold"
                        :style="`background-color: ${option.color}22; color: ${option.color};`"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :style="`background-color: ${option.color};`"></span>
                        <span x-text="option.label"></span>
                    </span>
                    <svg x-show="selectedValues.includes(String(option.value))" class="ml-auto h-3.5 w-3.5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                </button>
            </template>

            <button
                type="button"
                x-show="canCreate"
                @click="onEnter()"
                class="mt-1 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs font-semibold text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10"
            >
                <span>Create</span>
                <span class="truncate" x-text="'“' + search.trim() + '”'"></span>
            </button>

            <div
                x-show="filtered.length === 0 && !canCreate"
                class="px-2 py-4 text-center text-xs text-gray-400"
                x-text="options.length === 0 ? 'No tags yet — type a name to create one' : 'No matches found'"
            ></div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">Color for new tags</span>
        <div class="flex items-center gap-1.5">
            @foreach($colors as $color)
                <button
                    type="button"
                    @click="newColor = '{{ $color }}'"
                    class="h-5 w-5 rounded-full border-2 transition"
                    :class="newColor === '{{ $color }}' ? 'border-gray-900 scale-110 dark:border-white' : 'border-transparent'"
                    style="background-color: {{ $color }};"
                    title="{{ $color }}"
                    aria-label="Pick color {{ $color }}"
                ></button>
            @endforeach
        </div>
    </div>
</div>
