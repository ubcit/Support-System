@props([
    'task',
    'allTags' => [],
    'editable' => true,
    'maxVisible' => 3,
])

@php
    $taskTags = $task->relationLoaded('tags') ? $task->tags : collect();
    $selectedIds = $taskTags->pluck('id')->map(fn ($id) => (int) $id)->all();
@endphp

<div
    class="inline-flex flex-wrap items-center gap-1 {{ $attributes->get('class') }}"
    @mousedown.stop
    @click.stop
    draggable="false"
    x-data="{
        ...createFloatingPanelState({ width: 224, align: 'left', menuHeight: 280 }),
        search: '',
        close() {
            this.search = '';
            this.closePanel();
        },
    }"
    @keydown.escape.window="if (open) close()"
    x-on:destroy="destroy()"
>
    @foreach($taskTags->take($maxVisible) as $tag)
        <x-tasks.tag-chip :tag="$tag" size="xs" />
    @endforeach
    @if($taskTags->count() > $maxVisible)
        <span class="text-[10px] font-semibold text-gray-400">+{{ $taskTags->count() - $maxVisible }}</span>
    @endif

    @if($editable)
        <div class="relative">
            <button
                type="button"
                x-ref="trigger"
                @click.stop="togglePanel(() => $refs.tagSearch?.focus({ preventScroll: true }))"
                class="inline-flex h-5 w-5 items-center justify-center rounded-md border border-dashed border-gray-300 text-gray-400 hover:border-brand-400 hover:text-brand-500 dark:border-gray-600 dark:hover:border-brand-400"
                title="Add tag"
                aria-label="Add tag"
            >
                <x-heroicon-m-plus class="h-3 w-3"/>
            </button>

            <template x-teleport="body">
                <div
                    x-show="open"
                    @click.outside="onOutside($event); if (!open) search = ''"
                    x-cloak
                    :style="panelStyle"
                    class="fixed rounded-xl border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                >
                    <input
                        type="text"
                        x-ref="tagSearch"
                        x-model="search"
                        @keydown.enter.prevent="
                            const name = search.trim();
                            if (!name) return;
                            $wire.quickCreateTaskTag({{ $task->id }}, name);
                            close();
                        "
                        maxlength="50"
                        placeholder="Search or create…"
                        class="mb-2 h-8 w-full rounded-lg border border-gray-200 bg-gray-50 px-2 text-xs outline-none focus:border-brand-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <div class="max-h-40 space-y-0.5 overflow-y-auto">
                        @forelse($allTags as $tag)
                            @php $isOn = in_array((int) $tag->id, $selectedIds, true); @endphp
                            <button
                                type="button"
                                x-show="!search.trim() || @js(strtolower($tag->name)).includes(search.trim().toLowerCase())"
                                wire:click="toggleTaskTag({{ $task->id }}, {{ $tag->id }})"
                                @click="close()"
                                class="flex w-full items-center gap-2 rounded-lg px-1.5 py-1 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <x-tasks.tag-chip :tag="$tag" size="xs" />
                                @if($isOn)
                                    <x-heroicon-m-check class="ml-auto h-3.5 w-3.5 text-brand-500"/>
                                @endif
                            </button>
                        @empty
                            <p class="px-1 py-2 text-center text-[11px] text-gray-400">No tags yet</p>
                        @endforelse
                    </div>

                    <button
                        type="button"
                        x-show="search.trim().length > 0"
                        x-cloak
                        @click="
                            const name = search.trim();
                            if (!name) return;
                            $wire.quickCreateTaskTag({{ $task->id }}, name);
                            close();
                        "
                        class="mt-1 flex w-full items-center gap-1 rounded-lg px-1.5 py-1.5 text-[11px] font-semibold text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10"
                    >
                        Create “<span x-text="search.trim()"></span>”
                    </button>
                </div>
            </template>
        </div>
    @endif
</div>
