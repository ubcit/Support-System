{{-- Combined primary + secondary navigation as a single slide-over, below lg. --}}
<div
    x-cloak
    x-show="$store.shell.mobileOpen"
    x-transition.opacity
    class="fixed inset-0 z-[45] bg-gray-900/50 lg:hidden"
    @click="$store.shell.closeMobile()"
></div>

<div
    x-cloak
    x-show="$store.shell.mobileOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    x-data="{ openKey: @js($activeKey) }"
    class="fixed inset-y-0 left-0 z-[45] flex w-[300px] max-w-[85vw] flex-col bg-shell-secondary lg:hidden"
>
    <div class="flex h-16 shrink-0 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-800">
        <a href="{{ $homeDestination['item']['path'] ?? '/' }}" wire:navigate @click="$store.shell.closeMobile()" class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl" aria-label="The Space">
            <img src="{{ asset('images/logo/logo-icon.svg') }}" alt="The Space" class="h-9 w-9" />
        </a>
        <button type="button" @click="$store.shell.closeMobile()" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5" aria-label="Close navigation">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto no-scrollbar p-2">
        @foreach ($destinations as $destination)
            @php
                $hasPanel = ! empty($destination['panel']);
                $isActiveRow = $activeKey === $destination['key'];
            @endphp
            <div class="mb-1">
                <div class="flex items-center rounded-lg {{ $isActiveRow ? 'bg-brand-50 dark:bg-brand-500/[0.12]' : '' }}">
                    <a
                        href="{{ $destination['item']['path'] ?? '#' }}"
                        @if ($destination['item']) wire:navigate @click="$store.shell.closeMobile()" @endif
                        class="flex flex-1 items-center gap-3 px-3 py-2.5 text-sm font-semibold {{ $isActiveRow ? 'text-brand-600 dark:text-brand-400' : 'text-gray-700 dark:text-gray-300' }}"
                    >
                        <span class="[&>svg]:h-5 [&>svg]:w-5">{!! \App\Helpers\MenuHelper::getIconSvg($destination['icon']) !!}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $destination['label'] }}</span>
                        @if ($destination['key'] === 'inbox')
                            <livewire:app-shell.rail-badge kind="inbox" placement="mobile" :key="'rail-badge-inbox-mobile'" />
                        @elseif ($destination['key'] === 'my-tasks')
                            <livewire:app-shell.rail-badge kind="tasks" placement="mobile" :key="'rail-badge-tasks-mobile'" />
                        @endif
                    </a>
                    @if ($hasPanel)
                        <button
                            type="button"
                            @click="openKey = (openKey === '{{ $destination['key'] }}' ? null : '{{ $destination['key'] }}')"
                            class="px-3 py-2.5 text-gray-400"
                            aria-label="Toggle {{ $destination['label'] }} sub-menu"
                        >
                            <svg class="h-4 w-4 transition-transform" :class="openKey === '{{ $destination['key'] }}' ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 7.5l5 5 5-5" />
                            </svg>
                        </button>
                    @endif
                </div>

                @if ($hasPanel)
                    <div
                        x-show="openKey === '{{ $destination['key'] }}'"
                        x-cloak
                        @click="if ($event.target.closest('a[href]')) $store.shell.closeMobile()"
                        class="ml-8 mt-1 space-y-0.5 border-l border-gray-100 pl-3 dark:border-white/5"
                    >
                        @if ($destination['panel'] === 'projects')
                            @php $mobileProjects = \App\Helpers\ProjectNavHelper::getProjects(); @endphp
                            @forelse ($mobileProjects as $project)
                                <a href="{{ route('project-hub') }}" wire:navigate @click="$store.shell.closeMobile()" class="block truncate rounded-lg px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                    {{ $project['name'] }}
                                </a>
                            @empty
                                <p class="px-2 py-1 text-xs text-gray-400">No projects yet.</p>
                            @endforelse
                            <a href="{{ route('project-hub') }}" wire:navigate @click="$store.shell.closeMobile()" class="mt-1 block rounded-lg px-2 py-1.5 text-xs font-bold text-brand-600 dark:text-brand-400">
                                + New project
                            </a>
                        @elseif ($destination['panel'] === 'generic')
                            @foreach ($destination['subItems'] ?? [] as $item)
                                <a href="{{ $item['path'] }}" wire:navigate @click="$store.shell.closeMobile()" class="flex items-center gap-2 truncate rounded-lg px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                    <span class="[&>svg]:h-3.5 [&>svg]:w-3.5 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg(\App\Helpers\MenuHelper::resolveItemIcon($item['name'])) !!}</span>
                                    {{ $item['name'] }}
                                </a>
                            @endforeach
                        @elseif ($destination['panel'] === 'home')
                            @include('layouts.app-shell.secondary-sidebar.home-panel', ['flyoutDestination' => $destination])
                        @elseif ($destination['panel'] === 'tasks')
                            @include('layouts.app-shell.secondary-sidebar.tasks-panel', ['panelKey' => 'tasks-mobile'])
                        @elseif ($destination['panel'] === 'inbox')
                            @include('layouts.app-shell.secondary-sidebar.inbox-panel', ['panelKey' => 'inbox-mobile'])
                        @elseif ($destination['panel'] === 'profile')
                            @include('layouts.app-shell.secondary-sidebar.profile-panel')
                        @elseif ($destination['panel'] === 'more')
                            @forelse ($moreLeftoverItems ?? [] as $item)
                                <a href="{{ $item['path'] }}" wire:navigate @click="$store.shell.closeMobile()" class="flex items-center gap-2 truncate rounded-lg px-2 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                    <span class="[&>svg]:h-3.5 [&>svg]:w-3.5 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon'] ?? \App\Helpers\MenuHelper::resolveItemIcon($item['name'])) !!}</span>
                                    {{ $item['name'] }}
                                </a>
                            @empty
                                <p class="px-2 py-1 text-xs text-gray-400">Nothing else to show.</p>
                            @endforelse
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </nav>

    @php $mobileUser = auth()->user(); @endphp
    <div class="shrink-0 border-t border-gray-200 p-3 dark:border-gray-800">
        <a
            href="{{ $isWorkspaceContext ? route('workspace.profile') : route('profile') }}"
            wire:navigate
            @click="$store.shell.closeMobile()"
            class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-gray-100 dark:hover:bg-white/5"
        >
            <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full">
                <x-ui.person-avatar :person="$mobileUser" size="lg" class="h-full w-full" />
            </span>
            <span class="truncate text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $mobileUser?->name ?? 'Profile' }}</span>
        </a>
    </div>
</div>
