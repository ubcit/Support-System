<div>
    <div
        x-data="{
            isOpen: @entangle('isOpen').live,
            selectedIndex: 0,
            resultCount: {{ count($results) }},
            openPalette() {
                this.isOpen = true;
                this.selectedIndex = 0;
                $wire.set('query', '');
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },
            closePalette() {
                this.isOpen = false;
            },
            selectNext() {
                if (this.resultCount < 1) return;
                this.selectedIndex = Math.min(this.selectedIndex + 1, this.resultCount - 1);
            },
            selectPrevious() {
                if (this.resultCount < 1) return;
                this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
            },
            executeSelected() {
                $wire.set('selectedIndex', this.selectedIndex);
                $wire.executeSelected();
            },
            init() {
                this.$watch('isOpen', (open) => {
                    if (open) {
                        this.selectedIndex = 0;
                        this.$nextTick(() => this.$refs.searchInput?.focus());
                    }
                });

                window.addEventListener('open-command-palette', () => this.openPalette());

                document.addEventListener('keydown', (e) => {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                        e.preventDefault();
                        this.openPalette();
                    }
                    if (e.key === 'Escape' && this.isOpen) {
                        this.closePalette();
                    }
                });
            }
        }"
        x-effect="(() => { const count = {{ count($results) }}; if (count !== resultCount) { selectedIndex = 0; } resultCount = count; selectedIndex = Math.min(selectedIndex, Math.max(resultCount - 1, 0)); })()"
        x-show="isOpen"
        x-cloak
        class="relative z-50"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div
            x-show="isOpen"
            x-transition.opacity.duration.200ms
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"
        ></div>

        <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
            <!-- Modal Panel -->
            <div
                x-show="isOpen"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.away="closePalette()"
                class="mx-auto max-w-2xl transform divide-y divide-gray-100 overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/5 transition-all dark:divide-gray-800 dark:bg-gray-900"
            >
                <!-- Search Input -->
                <div class="relative">
                    <x-heroicon-o-magnifying-glass
                        class="pointer-events-none absolute left-4 top-3 h-5 w-5 text-gray-400"
                    />
                    <input
                        x-ref="searchInput"
                        wire:model.live.debounce.300ms="query"
                        @keydown.down.prevent="selectNext()"
                        @keydown.up.prevent="selectPrevious()"
                        @keydown.enter.prevent="executeSelected()"
                        type="text"
                        class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white sm:text-sm"
                        placeholder="Search or type '>' for commands, '/' for macros..."
                        role="combobox"
                        aria-expanded="false"
                        aria-controls="options"
                    >

                    <div class="absolute right-4 top-3 flex gap-1">
                        <kbd class="rounded border border-gray-200 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-800">ESC</kbd>
                    </div>
                </div>

                <!-- Searching -->
                <div
                    wire:loading.flex
                    wire:target="query"
                    class="min-h-[8rem] flex-col items-center justify-center gap-3 px-6 py-10"
                >
                    <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" aria-hidden="true"></span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">Searching…</span>
                </div>

                <div wire:loading.remove wire:target="query">
                    @if (count($results) > 0)
                        <ul class="max-h-96 scroll-py-3 overflow-y-auto p-3" role="listbox" id="options">
                            @foreach ($results as $index => $result)
                                <li
                                    class="group flex cursor-default select-none rounded-xl p-3"
                                    :class="selectedIndex === {{ $index }}
                                        ? 'bg-brand-50 ring-1 ring-brand-500/50 dark:bg-brand-900/20'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                    id="option-{{ $index }}"
                                    role="option"
                                    tabindex="-1"
                                    @click="selectedIndex = {{ $index }}; executeSelected()"
                                    @mouseenter="selectedIndex = {{ $index }}"
                                >
                                    <div
                                        class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800"
                                        :class="selectedIndex === {{ $index }} ? 'text-brand-600 dark:text-brand-400' : 'text-gray-500'"
                                    >
                                        <x-dynamic-component
                                            :component="$result['icon'] ?? 'heroicon-o-document'"
                                            class="h-6 w-6"
                                        />
                                    </div>
                                    <div class="ml-4 flex-auto">
                                        <p class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $result['title'] }}
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-gray-500 dark:bg-gray-800">{{ $result['type'] }}</span>
                                        </p>
                                        <p class="text-xs text-gray-500">{{ $result['subtitle'] ?? '' }}</p>
                                    </div>

                                    @if (isset($result['actions']))
                                        <div
                                            x-show="selectedIndex === {{ $index }}"
                                            class="ml-4 flex items-center gap-2"
                                        >
                                            @foreach ($result['actions'] as $action)
                                                <button
                                                    type="button"
                                                    class="rounded border border-gray-200 bg-white px-2 py-1 text-[10px] font-bold uppercase tracking-wider shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
                                                >
                                                    {{ $action }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div
                                        x-show="selectedIndex === {{ $index }}"
                                        class="ml-4 flex items-center"
                                    >
                                        <kbd class="rounded border border-gray-200 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500 dark:border-gray-700 dark:bg-gray-800">↵</kbd>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @elseif (strlen($query) > 0)
                        <div class="px-6 py-14 text-center text-sm sm:px-14">
                            <x-heroicon-o-face-frown class="mx-auto h-6 w-6 text-gray-400" />
                            <p class="mt-4 font-semibold text-gray-900 dark:text-white">No results found</p>
                            <p class="mt-2 text-gray-500">We couldn't find anything matching "{{ $query }}". Try searching for a UUID or Correlation ID.</p>
                        </div>
                    @endif
                </div>

                <!-- Footer Help -->
                <div class="flex flex-wrap items-center gap-4 bg-gray-50 px-4 py-2.5 text-xs text-gray-500 dark:bg-gray-800">
                    <span class="flex items-center gap-1"><kbd class="rounded border border-gray-300 px-1 font-sans dark:border-gray-600">↑↓</kbd> to navigate</span>
                    <span class="flex items-center gap-1"><kbd class="rounded border border-gray-300 px-1 font-sans dark:border-gray-600">↵</kbd> to select</span>
                    <span class="flex items-center gap-1"><kbd class="rounded border border-gray-300 px-1 font-sans dark:border-gray-600">></kbd> for commands</span>
                    <span class="flex items-center gap-1"><kbd class="rounded border border-gray-300 px-1 font-sans dark:border-gray-600">/</kbd> for macros</span>
                </div>
            </div>
        </div>
    </div>
</div>
