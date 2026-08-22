{{-- Secondary sidebar: context panel next to the primary rail. lg and up only. --}}
<aside
    class="hidden lg:flex fixed inset-y-0 left-16 z-30 flex-col border-r border-gray-200 bg-shell-secondary transition-all duration-300 ease-in-out dark:border-gray-800"
    :class="$store.shell.secondaryCollapsed ? 'w-0 overflow-hidden border-0 pointer-events-none' : 'w-[272px]'"
>
    {{-- Panel header: section title + collapse toggle --}}
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-gray-200 px-3 dark:border-gray-800">
        <span x-show="!$store.shell.secondaryCollapsed && !forceMore" x-cloak class="truncate text-sm font-bold text-gray-800 dark:text-white/90">
            {{ $activeDestination['label'] ?? 'Menu' }}
        </span>
        <span x-show="!$store.shell.secondaryCollapsed && forceMore" x-cloak class="truncate text-sm font-bold text-gray-800 dark:text-white/90">
            More
        </span>
        <button
            type="button"
            @click="$store.shell.toggleSecondary()"
            class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300"
            aria-label="Collapse sidebar"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 6l-6 6 6 6" />
            </svg>
        </button>
    </div>

    {{-- Panel body (each panel manages its own internal scroll region / footer) --}}
    <div class="flex-1 overflow-hidden">
        {{-- Section matching the current route --}}
        <div x-show="!forceMore" x-cloak class="h-full">
            @if (($activeDestination['panel'] ?? null) === 'projects')
                @include('layouts.app-shell.secondary-sidebar.projects-panel', ['panelKey' => 'projects-secondary'])
            @elseif (($activeDestination['panel'] ?? null) === 'generic')
                @include('layouts.app-shell.secondary-sidebar.generic-panel')
            @elseif (($activeDestination['panel'] ?? null) === 'more')
                @include('layouts.app-shell.secondary-sidebar.more-panel')
            @elseif (($activeDestination['panel'] ?? null) === 'home')
                @include('layouts.app-shell.secondary-sidebar.home-panel')
            @elseif (($activeDestination['panel'] ?? null) === 'tasks')
                @include('layouts.app-shell.secondary-sidebar.tasks-panel', ['panelKey' => 'tasks-secondary'])
            @elseif (($activeDestination['panel'] ?? null) === 'inbox')
                @include('layouts.app-shell.secondary-sidebar.inbox-panel', ['panelKey' => 'inbox-secondary'])
            @elseif (($activeDestination['panel'] ?? null) === 'profile')
                @include('layouts.app-shell.secondary-sidebar.profile-panel')
            @else
                <div class="p-4">
                    <p class="text-xs text-gray-400">No sub-navigation for this section.</p>
                    @if ($activeDestination['item'] ?? null)
                        <a href="{{ $activeDestination['item']['path'] }}" wire:navigate class="mt-2 inline-flex text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">
                            Open {{ $activeDestination['label'] }}
                        </a>
                    @endif
                </div>
            @endif
        </div>

        {{-- Forced-open "More" panel (click on the More rail icon, no navigation) --}}
        @if ($destinations->firstWhere('key', 'more'))
            <div x-show="forceMore" x-cloak class="h-full">
                @include('layouts.app-shell.secondary-sidebar.more-panel')
            </div>
        @endif
    </div>
</aside>
