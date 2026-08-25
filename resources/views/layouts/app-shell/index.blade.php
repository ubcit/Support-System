@php
    use App\Helpers\MenuHelper;

    // ── Shared context for every app-shell partial below ──────────────────
    // Everything here is pure lookup/derivation from MenuHelper — the single
    // source of truth for nav items — plus the current request path. No new
    // routes or menu structures are invented here.
    $isWorkspaceContext = request()->is('workspace*')
        && ! (auth()->user()?->canAccessAdmin() ?? false);

    $mainItems = collect(MenuHelper::getMainNavItems());
    $othersItems = collect(MenuHelper::getOthersItems());
    $workspaceItems = collect(MenuHelper::getWorkspaceNavItems());

    $flattenItems = function ($items) {
        $flat = collect();
        foreach ($items as $item) {
            if (! empty($item['subItems'])) {
                foreach ($item['subItems'] as $sub) {
                    $flat->push($sub);
                }
            } else {
                $flat->push($item);
            }
        }

        return $flat;
    };

    $findItem = function (string $name) use ($mainItems, $othersItems, $workspaceItems, $flattenItems, $isWorkspaceContext) {
        if ($isWorkspaceContext) {
            return $flattenItems($workspaceItems)->firstWhere('name', $name)
                ?? $flattenItems($mainItems)->firstWhere('name', $name)
                ?? $flattenItems($othersItems)->firstWhere('name', $name);
        }

        return $flattenItems($mainItems)->firstWhere('name', $name)
            ?? $flattenItems($othersItems)->firstWhere('name', $name);
    };

    $subItemsOf = function (string $parentName) use ($mainItems, $othersItems) {
        $parent = $mainItems->firstWhere('name', $parentName) ?? $othersItems->firstWhere('name', $parentName);

        return $parent['subItems'] ?? [];
    };

    $collectItems = function (array $names) use ($findItem) {
        return collect($names)->map(fn ($name) => $findItem($name))->filter()->values()->all();
    };

    // ── Primary sidebar destinations (curated rail + leftover "More") ──
    $destinations = collect();

    if ($isWorkspaceContext) {
        $destinations->push([
            'key' => 'home', 'label' => 'Home', 'icon' => 'dashboard',
            'item' => $findItem('My Workspace'), 'panel' => 'home',
            // quickLaunch is display-only — must NOT be subItems or Home steals
            // the active rail highlight from My Tasks / Profile / etc.
            'quickLaunch' => $collectItems(['My Workspace', 'My Tasks', 'Profile']),
        ]);
        $destinations->push(['key' => 'my-tasks', 'label' => 'My Tasks', 'icon' => 'task', 'item' => $findItem('My Tasks'), 'panel' => 'tasks']);
        $destinations->push(['key' => 'settings', 'label' => 'Profile', 'icon' => 'settings', 'item' => $findItem('Profile'), 'panel' => 'profile']);
    } else {
        $destinations->push([
            'key' => 'home', 'label' => 'Home', 'icon' => 'dashboard',
            'item' => $findItem('Dashboard'), 'panel' => 'home',
            // quickLaunch is shortcuts to other rail sections. subItems are Home's
            // own pages (Insights) so they correctly highlight Home.
            'quickLaunch' => $collectItems(['Customers', 'Employee Hub', 'Project Hub', 'My Tasks', 'Conversation Center', 'AI Center']),
            'subItems' => $collectItems(['Reports & Analytics']),
        ]);
        $destinations->push([
            'key' => 'projects', 'label' => 'Projects', 'icon' => 'briefcase',
            'item' => $findItem('Project Hub'), 'panel' => 'projects',
        ]);
        $destinations->push(['key' => 'my-tasks', 'label' => 'My Tasks', 'icon' => 'task', 'item' => $findItem('My Tasks'), 'panel' => 'tasks']);
        $destinations->push([
            'key' => 'inbox', 'label' => 'Inbox', 'icon' => 'chat',
            'item' => $findItem('Conversation Center'), 'panel' => 'inbox',
        ]);
        $destinations->push([
            'key' => 'customers', 'label' => 'Customers', 'icon' => 'people',
            'item' => $findItem('Customers'), 'panel' => 'generic',
            'subItems' => $collectItems(['Customers', 'Customer AI Limits']),
        ]);
        $destinations->push([
            'key' => 'employees', 'label' => 'Employees', 'icon' => 'user-profile',
            'item' => $findItem('Employee Hub'), 'panel' => 'generic',
            'subItems' => $collectItems(['Employee Hub', 'Boss Workspace']),
        ]);
        $destinations->push([
            'key' => 'ai-center', 'label' => 'AI Center', 'icon' => 'sparkles',
            'item' => $findItem('AI Center'), 'panel' => 'generic',
            'subItems' => array_values(array_filter(array_merge(
                $subItemsOf('AI & Automation'),
                $collectItems(['Operations Dashboard']),
                $subItemsOf('System Administration'),
                $subItemsOf('Developer Tools'),
            ))),
        ]);
        $destinations->push([
            'key' => 'files', 'label' => 'File Manager', 'icon' => 'folder',
            'item' => $findItem('File Manager'), 'panel' => 'generic',
            'subItems' => $collectItems(['File Manager', 'Drive']),
        ]);
        $destinations->push([
            'key' => 'settings', 'label' => 'Settings', 'icon' => 'settings',
            'item' => $findItem('Workspace Settings'), 'panel' => 'generic',
            'subItems' => $collectItems(['Workspace Settings', 'Profile', 'Workspace Onboarding']),
        ]);
    }

    $destinations = $destinations->filter(fn ($d) => $d['item'] || $d['panel'] === 'more')->values();

    // More is overflow only: catalog pages that no rail section owns.
    $ownedPaths = $destinations->flatMap(function ($destination) {
        return collect([$destination['item']['path'] ?? null])
            ->merge(collect($destination['subItems'] ?? [])->pluck('path'));
    })->filter()->unique();

    $catalogItems = $flattenItems(
        $isWorkspaceContext ? $workspaceItems : $mainItems->concat($othersItems)
    );

    $moreLeftoverItems = $catalogItems
        ->filter(fn ($item) => ! empty($item['path']) && ! $ownedPaths->contains($item['path']))
        ->unique('path')
        ->values()
        ->all();

    if ($moreLeftoverItems !== []) {
        $destinations->push(['key' => 'more', 'label' => 'More', 'icon' => 'more', 'item' => null, 'panel' => 'more']);
    }

    // ── Which destination is active for the current request? ───────────────
    // Prefer each destination's own primary route first. Only then fall back to
    // subItems (Home Insights, Inbox/AI/Employees/Settings children).
    // Home `quickLaunch` is display-only so shortcuts never steal the rail highlight.
    $activeKey = null;
    foreach ($destinations as $destination) {
        if ($destination['item'] && MenuHelper::isActive($destination['item']['path'])) {
            $activeKey = $destination['key'];
            break;
        }
    }
    if ($activeKey === null) {
        foreach ($destinations as $destination) {
            foreach ($destination['subItems'] ?? [] as $sub) {
                if (MenuHelper::isActive($sub['path'])) {
                    $activeKey = $destination['key'];
                    break 2;
                }
            }
        }
    }
    // Task detail is a child of My Tasks, not a standalone More item.
    if ($activeKey === null && request()->routeIs('task-detail', 'workspace.task-detail')) {
        $activeKey = 'my-tasks';
    }
    // If the current route isn't one of the curated destinations, it's a
    // leftover that only lives in More — default there so the rail/panel
    // correctly reflect where we are.
    $activeKey = $activeKey ?? ($destinations->firstWhere('key', 'more') ? 'more' : optional($destinations->first())['key']);
    $activeDestination = $destinations->firstWhere('key', $activeKey);

    $homeDestination = $destinations->firstWhere('key', 'home');
@endphp

<div
    x-data="{ mobileOpen: false, forceMore: @js($activeKey === 'more') }"
    @keydown.escape.window="mobileOpen = false; $store.shell.closeMobile()"
    class="min-h-screen bg-shell-canvas"
>
    {{-- Primary + secondary sidebars (lg and up) --}}
    @include('layouts.app-shell.primary-sidebar')
    @include('layouts.app-shell.secondary-sidebar')
    @include('layouts.app-shell.secondary-sidebar-flyout')

    {{-- Mobile slide-over (below lg) --}}
    @include('layouts.app-shell.mobile-nav')

    {{-- Global toast notifications (see AppServiceProvider::registerFlashToastBridge) --}}
    @include('layouts.app-shell.toast')
    @include('layouts.app-shell.confirm')

    {{-- ClickUp-style expand tab: visible only when secondary sidebar is fully hidden --}}
    <button
        type="button"
        x-show="$store.shell.secondaryCollapsed && !$store.shell.hoveredPanelKey"
        x-cloak
        @click="$store.shell.toggleSecondary()"
        class="fixed left-16 top-[4.5rem] z-30 hidden h-8 w-5 items-center justify-center rounded-r-lg border border-l-0 border-gray-200 bg-shell-secondary text-gray-400 shadow-sm hover:bg-gray-50 hover:text-gray-600 dark:border-gray-800 dark:hover:bg-white/5 dark:hover:text-gray-300 lg:flex"
        title="Expand sidebar"
        aria-label="Expand sidebar"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 6l6 6-6 6" />
        </svg>
    </button>

    {{-- Main content column --}}
    <div
        class="min-h-screen transition-all duration-300 ease-in-out"
        :class="$store.shell.secondaryCollapsed ? 'lg:ml-16' : 'lg:ml-[336px]'"
    >
        @include('layouts.app-shell.topbar')

        <main
            class="relative p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6"
            x-data="{
                navigating: false,
                _navTimer: null,
                init() {
                    const on = () => {
                        this.navigating = true;
                        clearTimeout(this._navTimer);
                        // Safety: never leave the overlay stuck if navigated is missed.
                        this._navTimer = setTimeout(() => { this.navigating = false }, 8000);
                    };
                    const off = () => {
                        this.navigating = false;
                        clearTimeout(this._navTimer);
                        this._navTimer = null;
                    };
                    document.addEventListener('livewire:navigate', on);
                    document.addEventListener('livewire:navigated', off);
                    document.addEventListener('livewire:navigate-error', off);
                    document.addEventListener('alpine:navigated', off);
                },
            }"
        >
            <div
                x-show="navigating"
                x-cloak
                x-transition.opacity.duration.150ms
                class="absolute inset-0 z-30 flex items-start justify-center bg-white/50 pt-24 backdrop-blur-[1px] dark:bg-gray-950/40 md:pt-32"
                aria-busy="true"
                aria-live="polite"
            >
                <div
                    class="h-10 w-10 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"
                    role="status"
                    aria-label="Loading"
                ></div>
            </div>
            {{ $slot }}
        </main>
    </div>
</div>
