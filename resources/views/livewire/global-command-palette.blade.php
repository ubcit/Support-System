<div>
    <!-- Keyboard Listener for Ctrl+K / Cmd+K -->
    <div x-data="{
            isOpen: @entangle('isOpen'),
            init() {
                document.addEventListener('keydown', (e) => {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                        e.preventDefault();
                        this.isOpen = true;
                        setTimeout(() => $refs.searchInput.focus(), 50);
                    }
                    if (e.key === 'Escape' && this.isOpen) {
                        this.isOpen = false;
                    }
                });
            }
        }" x-show="isOpen" style="display: none;" class="relative z-50" role="dialog" aria-modal="true">

        <!-- Backdrop -->
        <div x-show="isOpen" x-transition.opacity.duration.200ms
            class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"></div>

        <div class="fixed inset-0 z-10 overflow-y-auto p-4 sm:p-6 md:p-20">
            <!-- Modal Panel -->
            <div x-show="isOpen" x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95" @click.away="isOpen = false"
                class="mx-auto max-w-2xl transform divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden rounded-xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-black/5 transition-all">

                <!-- Search Input -->
                <div class="relative">
                    <x-heroicon-o-magnifying-glass
                        class="pointer-events-none absolute left-4 top-3 h-5 w-5 text-gray-400" />
                    <input x-ref="searchInput" wire:model.live.debounce.300ms="query"
                        wire:keydown.down.prevent="selectNext" wire:keydown.up.prevent="selectPrevious"
                        wire:keydown.enter.prevent="executeSelected" type="text"
                        class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-0 sm:text-sm"
                        placeholder="Search or type '>' for commands, '/' for macros..." role="combobox"
                        aria-expanded="false" aria-controls="options">

                    <!-- Keyboard Shortcuts Hint -->
                    <div class="absolute right-4 top-3 flex gap-1">
                        <kbd
                            class="px-2 py-0.5 text-[10px] font-semibold text-gray-500 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">ESC</kbd>
                    </div>
                </div>

                <!-- Results List -->
                @if(count($results) > 0)
                    <ul class="max-h-96 scroll-py-3 overflow-y-auto p-3" role="listbox">
                        @foreach($results as $index => $result)
                            <li class="group flex cursor-default select-none rounded-xl p-3 {{ $index === $selectedIndex ? 'bg-brand-50 dark:bg-brand-900/20 ring-1 ring-brand-500/50' : 'hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                                id="option-{{ $index }}" role="option" tabindex="-1"
                                wire:click="$set('selectedIndex', {{ $index }}); executeSelected()">

                                <div
                                    class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800">
                                    <x-dynamic-component :component="$result['icon'] ?? 'heroicon-o-document'"
                                        class="h-6 w-6 text-gray-500 {{ $index === $selectedIndex ? 'text-brand-600 dark:text-brand-400' : '' }}" />
                                </div>
                                <div class="ml-4 flex-auto">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-2">
                                        {{ $result['title'] }}
                                        <span
                                            class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 uppercase font-bold">{{ $result['type'] }}</span>
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $result['subtitle'] ?? '' }}</p>
                                </div>

                                <!-- Inline Actions -->
                                @if(isset($result['actions']) && $index === $selectedIndex)
                                    <div class="flex items-center gap-2 ml-4">
                                        @foreach($result['actions'] as $action)
                                            <button
                                                class="text-[10px] font-bold uppercase tracking-wider bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-2 py-1 rounded hover:bg-gray-50 dark:hover:bg-gray-600 transition shadow-sm">
                                                {{ $action }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                                @if($index === $selectedIndex)
                                    <div class="flex items-center ml-4">
                                        <kbd
                                            class="px-2 py-0.5 text-[10px] font-semibold text-gray-500 bg-gray-100 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">↵</kbd>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @elseif(strlen($query) > 0)
                    <!-- Empty State -->
                    <div class="px-6 py-14 text-center text-sm sm:px-14">
                        <x-heroicon-o-face-frown class="mx-auto h-6 w-6 text-gray-400" />
                        <p class="mt-4 font-semibold text-gray-900 dark:text-white">No results found</p>
                        <p class="mt-2 text-gray-500">We couldn't find anything matching "{{ $query }}". Try searching for a
                            UUID or Correlation ID.</p>
                    </div>
                @endif

                <!-- Footer Help -->
                <div
                    class="flex flex-wrap items-center bg-gray-50 dark:bg-gray-800 px-4 py-2.5 text-xs text-gray-500 gap-4">
                    <span class="flex items-center gap-1"><kbd
                            class="font-sans px-1 rounded border border-gray-300 dark:border-gray-600">↑↓</kbd> to
                        navigate</span>
                    <span class="flex items-center gap-1"><kbd
                            class="font-sans px-1 rounded border border-gray-300 dark:border-gray-600">↵</kbd> to
                        select</span>
                    <span class="flex items-center gap-1"><kbd
                            class="font-sans px-1 rounded border border-gray-300 dark:border-gray-600">></kbd> for
                        commands</span>
                    <span class="flex items-center gap-1"><kbd
                            class="font-sans px-1 rounded border border-gray-300 dark:border-gray-600">/</kbd> for
                        macros</span>
                </div>
            </div>
        </div>
    </div>
</div>
