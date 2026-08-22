{{-- Hover flyout: shown when the secondary sidebar is collapsed and a primary
     rail icon with a panel is hovered. Does not permanently expand the sidebar. --}}
@foreach ($destinations as $destination)
    @continue(empty($destination['panel']))
    @php $flyoutDestination = $destination; @endphp
    <aside
        x-cloak
        x-show="$store.shell.secondaryCollapsed && $store.shell.hoveredPanelKey === '{{ $destination['key'] }}'"
        @mouseenter="$store.shell.setHoveredPanel('{{ $destination['key'] }}')"
        @mouseleave="$store.shell.clearHoveredPanel()"
        class="fixed inset-y-0 left-16 z-[35] hidden w-[272px] flex-col border-r border-gray-200 bg-shell-secondary shadow-lg dark:border-gray-800 lg:flex"
    >
        <div class="flex h-14 shrink-0 items-center border-b border-gray-200 px-4 dark:border-gray-800">
            <span class="truncate text-sm font-bold text-gray-800 dark:text-white/90">{{ $destination['label'] }}</span>
        </div>
        <div class="flex-1 overflow-hidden">
            @if ($destination['panel'] === 'projects')
                @include('layouts.app-shell.secondary-sidebar.projects-panel', ['panelKey' => 'projects-flyout'])
            @elseif ($destination['panel'] === 'generic')
                @include('layouts.app-shell.secondary-sidebar.generic-panel')
            @elseif ($destination['panel'] === 'more')
                @include('layouts.app-shell.secondary-sidebar.more-panel')
            @elseif ($destination['panel'] === 'home')
                @include('layouts.app-shell.secondary-sidebar.home-panel')
            @elseif ($destination['panel'] === 'tasks')
                @include('layouts.app-shell.secondary-sidebar.tasks-panel', ['panelKey' => 'tasks-flyout'])
            @elseif ($destination['panel'] === 'inbox')
                @include('layouts.app-shell.secondary-sidebar.inbox-panel', ['panelKey' => 'inbox-flyout'])
            @elseif ($destination['panel'] === 'profile')
                @include('layouts.app-shell.secondary-sidebar.profile-panel')
            @endif
        </div>
    </aside>
@endforeach
