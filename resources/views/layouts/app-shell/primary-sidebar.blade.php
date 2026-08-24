{{-- Primary sidebar: far-left icon rail, fixed, ~64px, icon-only. lg and up only. --}}
<aside class="hidden lg:flex fixed inset-y-0 left-0 z-40 w-16 flex-col items-center border-r border-gray-200 bg-shell-primary py-4 dark:border-gray-800">
    {{-- Workspace / logo mark --}}
    <a href="{{ $homeDestination['item']['path'] ?? '/' }}" wire:navigate class="mb-6 flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl" aria-label="The Space">
        <img src="{{ asset('images/logo/logo-icon.svg') }}" alt="The Space" class="h-9 w-9" />
    </a>

    {{-- Global destinations --}}
    <nav class="flex flex-1 flex-col items-center gap-1 overflow-y-auto no-scrollbar">
        @foreach ($destinations as $destination)
            @php
                $isServerActive = $activeKey === $destination['key'];
                $isMoreIcon = ! $destination['item'];
                $href = $destination['item']['path'] ?? '#';
                $hasPanel = ! empty($destination['panel']);
                $baseClasses = 'group flex h-11 w-11 items-center justify-center rounded-xl transition-colors';
                $activeClasses = 'bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400';
                $inactiveClasses = 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300';
            @endphp
            @if ($isMoreIcon)
                {{-- "More" has no single destination route — it just reveals its
                     own panel client-side without navigating anywhere. --}}
                <button
                    type="button"
                    @click="forceMore = !forceMore"
                    @if ($hasPanel)
                        @mouseenter="$store.shell.secondaryCollapsed && $store.shell.setHoveredPanel('{{ $destination['key'] }}')"
                        @mouseleave="$store.shell.clearHoveredPanel()"
                    @endif
                    title="{{ $destination['label'] }}"
                    aria-label="{{ $destination['label'] }}"
                    @if ($isServerActive) aria-current="page" @endif
                    :aria-current="forceMore ? 'page' : null"
                    class="relative {{ $baseClasses }}"
                    :class="forceMore ? '{{ $activeClasses }}' : '{{ $inactiveClasses }}'"
                >
                    <span class="[&>svg]:h-5 [&>svg]:w-5">{!! \App\Helpers\MenuHelper::getIconSvg($destination['icon']) !!}</span>
                    <livewire:app-shell.rail-badge kind="signup" placement="rail" :key="'rail-badge-signup'" />
                </button>
            @else
                <a
                    href="{{ $href }}"
                    wire:navigate
                    @click="forceMore = false"
                    @if ($hasPanel)
                        @mouseenter="$store.shell.secondaryCollapsed && $store.shell.setHoveredPanel('{{ $destination['key'] }}')"
                        @mouseleave="$store.shell.clearHoveredPanel()"
                    @endif
                    title="{{ $destination['label'] }}"
                    aria-label="{{ $destination['label'] }}"
                    @if ($isServerActive) aria-current="page" @endif
                    class="relative {{ $baseClasses }} {{ $isServerActive ? $activeClasses : $inactiveClasses }}"
                >
                    <span class="[&>svg]:h-5 [&>svg]:w-5">{!! \App\Helpers\MenuHelper::getIconSvg($destination['icon']) !!}</span>
                    @if ($destination['key'] === 'inbox')
                        <livewire:app-shell.rail-badge kind="inbox" placement="rail" :key="'rail-badge-inbox'" />
                    @elseif ($destination['key'] === 'my-tasks')
                        <livewire:app-shell.rail-badge kind="tasks" placement="rail" :key="'rail-badge-tasks'" />
                    @endif
                </a>
            @endif
        @endforeach
    </nav>

    {{-- Bottom: user avatar + settings shortcut --}}
    @php $user = auth()->user(); @endphp
    <div class="mt-2 flex flex-col items-center gap-2 border-t border-gray-200 pt-3 dark:border-gray-800">
        <a
            href="{{ $isWorkspaceContext ? route('workspace.profile') : route('profile') }}"
            wire:navigate
            title="{{ $user?->name ?? 'Profile' }}"
            aria-label="Profile"
            class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full"
        >
            <x-ui.person-avatar :person="$user" size="lg" class="h-full w-full" />
        </a>
    </div>
</aside>
