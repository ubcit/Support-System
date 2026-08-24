{{-- Secondary sidebar: context panel next to the primary rail. lg and up only.
     Content follows hoveredPanelKey when set, otherwise forceMore / active route. --}}
<aside
    class="hidden lg:flex fixed inset-y-0 left-16 z-30 flex-col border-r border-gray-200 bg-shell-secondary transition-all duration-300 ease-in-out dark:border-gray-800"
    :class="$store.shell.secondaryCollapsed ? 'w-0 overflow-hidden border-0 pointer-events-none' : 'w-[272px]'"
    @mouseenter="if ($store.shell.hoveredPanelKey) $store.shell.setHoveredPanel($store.shell.hoveredPanelKey)"
    @mouseleave="$store.shell.clearHoveredPanel()"
>
    {{-- Panel header: section title + collapse toggle --}}
    <div class="flex h-14 shrink-0 items-center justify-between border-b border-gray-200 px-3 dark:border-gray-800">
        @foreach ($destinations as $destination)
            @continue(empty($destination['panel']))
            <span
                x-show="!$store.shell.secondaryCollapsed && ($store.shell.hoveredPanelKey ?? (forceMore ? 'more' : '{{ $activeKey }}')) === '{{ $destination['key'] }}'"
                x-cloak
                class="truncate text-sm font-bold text-gray-800 dark:text-white/90"
            >
                {{ $destination['label'] }}
            </span>
        @endforeach
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
        @foreach ($destinations as $destination)
            @continue(empty($destination['panel']))
            @php $flyoutDestination = $destination; @endphp
            <div
                x-show="($store.shell.hoveredPanelKey ?? (forceMore ? 'more' : '{{ $activeKey }}')) === '{{ $destination['key'] }}'"
                x-cloak
                class="h-full"
            >
                @if ($destination['panel'] === 'projects')
                    @include('layouts.app-shell.secondary-sidebar.projects-panel', ['panelKey' => 'projects-secondary'])
                @elseif ($destination['panel'] === 'generic')
                    @include('layouts.app-shell.secondary-sidebar.generic-panel')
                @elseif ($destination['panel'] === 'more')
                    @include('layouts.app-shell.secondary-sidebar.more-panel')
                @elseif ($destination['panel'] === 'home')
                    @include('layouts.app-shell.secondary-sidebar.home-panel')
                @elseif ($destination['panel'] === 'tasks')
                    @include('layouts.app-shell.secondary-sidebar.tasks-panel', ['panelKey' => 'tasks-secondary'])
                @elseif ($destination['panel'] === 'inbox')
                    @include('layouts.app-shell.secondary-sidebar.inbox-panel', ['panelKey' => 'inbox-secondary'])
                @elseif ($destination['panel'] === 'profile')
                    @include('layouts.app-shell.secondary-sidebar.profile-panel')
                @endif
            </div>
        @endforeach
    </div>
</aside>
