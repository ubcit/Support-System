<div
    x-data="{
        search: '',
        favorites: JSON.parse(localStorage.getItem('appShell.favorites') || '[]'),
        matches(term, name) {
            term = (term || '').trim().toLowerCase();
            if (term === '') return true;
            return (name || '').toLowerCase().includes(term);
        },
        isFavorite(id) {
            return this.favorites.some(f => f.id === id);
        },
        toggleFavorite(project) {
            const idx = this.favorites.findIndex(f => f.id === project.id);
            if (idx === -1) {
                this.favorites.push(project);
            } else {
                this.favorites.splice(idx, 1);
            }
            localStorage.setItem('appShell.favorites', JSON.stringify(this.favorites));
        }
    }"
    class="flex h-full flex-col"
>
    {{-- Search --}}
    <div class="shrink-0 p-3">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15a6 6 0 100-12 6 6 0 000 12zM17 17l-4-4" />
            </svg>
            <input
                type="text"
                x-model="search"
                placeholder="Search projects..."
                class="h-8 w-full rounded-lg border border-gray-200 bg-gray-50 pl-8 pr-3 text-xs text-gray-700 placeholder:text-gray-400 focus:border-brand-300 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
            >
        </div>
    </div>

    {{-- Scrollable: favorites + projects --}}
    <div class="flex-1 overflow-y-auto no-scrollbar px-1.5 pb-3">
        <template x-if="favorites.length > 0">
            <div class="mb-3 px-1.5">
                <h4 class="mb-1 px-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">Favorites</h4>
                <ul class="space-y-0.5">
                    <template x-for="fav in favorites" :key="fav.id">
                        <li>
                            <a
                                :href="'{{ route('project-hub') }}?project=' + fav.id"
                                wire:navigate
                                class="group flex items-center justify-between rounded-lg px-2 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                                :class="String(fav.id) === '{{ $selectedProjectId }}' ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400' : ''"
                            >
                                <span class="flex min-w-0 items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-amber-400" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.363 1.118l1.287 3.957c.3.922-.755 1.688-1.539 1.118l-3.37-2.447a1 1 0 00-1.175 0l-3.37 2.447c-.783.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 00-.363-1.118L2.063 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.951-.69l1.285-3.958z"/></svg>
                                    <span x-text="fav.name" class="truncate"></span>
                                </span>
                                <button type="button" @click.prevent.stop="toggleFavorite(fav)" class="shrink-0 text-gray-300 opacity-0 hover:text-red-500 group-hover:opacity-100" title="Remove from favorites" aria-label="Remove from favorites">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" clip-rule="evenodd"/></svg>
                                </button>
                            </a>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <div>
            <h4 class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Projects</h4>
            @forelse ($sidebarProjects as $project)
                @php $isProjectActive = (string) $selectedProjectId === (string) $project['id']; @endphp
                <div x-show="matches(search, @js($project['name']))" x-cloak class="group py-0.5">
                    <div class="flex items-center justify-between gap-1 rounded-lg px-2 py-1 hover:bg-gray-100 dark:hover:bg-white/5 {{ $isProjectActive ? 'bg-brand-50 dark:bg-brand-500/[0.12]' : '' }}">
                        <a href="{{ route('project-hub', ['project' => $project['id']]) }}" wire:navigate class="min-w-0 truncate text-xs font-medium {{ $isProjectActive ? 'text-brand-600 dark:text-brand-400' : 'text-gray-700 dark:text-gray-300' }}">{{ $project['name'] }}</a>
                        <button
                            type="button"
                            @click="toggleFavorite(@js(['id' => $project['id'], 'name' => $project['name']]))"
                            class="shrink-0 opacity-0 group-hover:opacity-100"
                            :class="isFavorite(@js($project['id'])) ? '!opacity-100 text-amber-400' : 'text-gray-300 hover:text-amber-400'"
                            title="Toggle favorite"
                            aria-label="Toggle favorite"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.363 1.118l1.287 3.957c.3.922-.755 1.688-1.539 1.118l-3.37-2.447a1 1 0 00-1.175 0l-3.37 2.447c-.783.57-1.838-.196-1.539-1.118l1.287-3.957a1 1 0 00-.363-1.118L2.063 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.951-.69l1.285-3.958z"/></svg>
                        </button>
                    </div>
                    <div class="ml-4 flex items-center gap-2.5 pb-0.5">
                        <a href="{{ route('task-dashboard', ['view' => 'list', 'project' => $project['id']]) }}" wire:navigate class="flex items-center gap-1 text-[10px] font-semibold text-gray-400 hover:text-brand-600 dark:hover:text-brand-400">
                            <span class="[&>svg]:h-3 [&>svg]:w-3">{!! \App\Helpers\MenuHelper::getIconSvg('list') !!}</span>
                            List
                        </a>
                        <a href="{{ route('task-dashboard', ['view' => 'board', 'project' => $project['id']]) }}" wire:navigate class="flex items-center gap-1 text-[10px] font-semibold text-gray-400 hover:text-brand-600 dark:hover:text-brand-400">
                            <span class="[&>svg]:h-3 [&>svg]:w-3">{!! \App\Helpers\MenuHelper::getIconSvg('board') !!}</span>
                            Board
                        </a>
                    </div>
                </div>
            @empty
                <p class="px-3 py-4 text-xs text-gray-400">No projects yet.</p>
            @endforelse
        </div>
    </div>

    <div class="shrink-0 border-t border-gray-200 p-2 dark:border-gray-800">
        <a
            href="{{ route('project-hub', ['create' => 1]) }}"
            wire:navigate
            class="flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-950/30"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
            New project
        </a>
    </div>
</div>
