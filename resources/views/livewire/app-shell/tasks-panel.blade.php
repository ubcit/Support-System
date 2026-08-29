{{-- ClickUp-style My Tasks panel: projects first, then filters, then views. --}}
@php
    $taskQuery = function (array $overrides = []) use ($currentView, $currentDue, $currentProject, $currentScope, $currentCompleted, $currentTrashed, $currentPriority, $currentAssignee) {
        return \App\Helpers\TaskNav::clean([
            'view' => array_key_exists('view', $overrides) ? $overrides['view'] : $currentView,
            'project' => array_key_exists('project', $overrides) ? $overrides['project'] : $currentProject,
            'due' => array_key_exists('due', $overrides) ? $overrides['due'] : $currentDue,
            'scope' => array_key_exists('scope', $overrides) ? $overrides['scope'] : $currentScope,
            'completed' => array_key_exists('completed', $overrides) ? $overrides['completed'] : ($currentCompleted ? 1 : null),
            'trashed' => array_key_exists('trashed', $overrides) ? $overrides['trashed'] : ($currentTrashed ? 1 : null),
            'priority' => array_key_exists('priority', $overrides) ? $overrides['priority'] : $currentPriority,
            'assignee' => array_key_exists('assignee', $overrides) ? $overrides['assignee'] : $currentAssignee,
            'queue' => array_key_exists('queue', $overrides) ? $overrides['queue'] : null,
            'create' => array_key_exists('create', $overrides) ? $overrides['create'] : null,
        ]);
    };

    $dashboardUrl = fn (array $overrides = []) => \App\Helpers\TaskNav::dashboardUrl($taskQuery($overrides));

    $navClass = function (bool $active): string {
        $base = 'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors';

        return $active
            ? $base.' bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400'
            : $base.' text-gray-600 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white';
    };
@endphp

<div class="flex h-full flex-col" wire:poll.10s>
    <div
        x-data="{ search: '' }"
        class="flex min-h-0 flex-1 flex-col"
    >
        <div class="flex-1 overflow-y-auto no-scrollbar p-3">
            <div class="mb-3 px-0">
                <div class="relative mb-2 px-0">
                    <svg class="pointer-events-none absolute left-5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
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

            <h4 class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Projects</h4>
            <ul class="space-y-0.5">
                <li x-show="search.trim() === ''">
                    <a
                        href="{{ $dashboardUrl(['project' => null, 'trashed' => null]) }}"
                        wire:navigate
                        class="{{ $navClass(empty($currentProject) && ! $currentTrashed) }}"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ empty($currentProject) && ! $currentTrashed ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                        <span class="min-w-0 flex-1 truncate">All projects</span>
                        <span class="shrink-0 font-mono text-[10px] text-gray-400">{{ $counts['all'] }}</span>
                    </a>
                </li>
                @forelse ($counts['projects'] as $project)
                    @php $isProjectActive = ! $currentTrashed && (string) $currentProject === (string) $project['id']; @endphp
                    <li x-show="search.trim() === '' || {{ \Illuminate\Support\Js::from(mb_strtolower($project['name'])) }}.includes(search.trim().toLowerCase())">
                        <a
                            href="{{ $dashboardUrl(['project' => $isProjectActive ? null : $project['id'], 'trashed' => null]) }}"
                            wire:navigate
                            class="{{ $navClass($isProjectActive) }}"
                            title="{{ $project['name'] }}"
                        >
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $isProjectActive ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                            <span class="min-w-0 flex-1 truncate">{{ $project['name'] }}</span>
                            <span class="shrink-0 font-mono text-[10px] text-gray-400">{{ $project['count'] }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-3 py-2 text-xs text-gray-400">No projects yet.</li>
                @endforelse
            </ul>

            <h4 class="mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Filters</h4>
            <ul class="space-y-0.5">
                @php
                    $filterItems = [
                        ['Assigned to me', 'scope', 'mine', $counts['mine']],
                        ['Due today', 'due', 'today', $counts['today']],
                        ['Overdue', 'due', 'overdue', $counts['overdue']],
                        ['This week', 'due', 'week', $counts['week']],
                        ['Unassigned', 'scope', 'unassigned', $counts['unassigned']],
                    ];
                @endphp
                @foreach ($filterItems as [$label, $key, $value, $count])
                    @php
                        $isActive = ! $currentTrashed && ($key === 'due' ? $currentDue === $value : $currentScope === $value);
                    @endphp
                    <li>
                        <a
                            href="{{ $dashboardUrl([$key => $isActive ? ($key === 'scope' ? 'all' : null) : $value, 'trashed' => null, 'completed' => null]) }}"
                            wire:navigate
                            class="{{ $navClass($isActive) }}"
                        >
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $isActive ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                            <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                            <span class="shrink-0 font-mono text-[10px] text-gray-400">{{ $count }}</span>
                        </a>
                    </li>
                @endforeach
                @if ($isManager)
                    @php $isReview = ! $currentTrashed && ($currentQueue ?? null) === 'review'; @endphp
                    <li>
                        <a
                            href="{{ $dashboardUrl(['queue' => $isReview ? null : 'review', 'due' => $isReview ? $currentDue : null, 'completed' => null, 'trashed' => null]) }}"
                            wire:navigate
                            class="{{ $navClass($isReview) }}"
                        >
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $isReview ? 'bg-amber-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                            <span class="min-w-0 flex-1 truncate">Review Queue</span>
                            <span class="shrink-0 font-mono text-[10px] text-gray-400">{{ $counts['review'] ?? 0 }}</span>
                        </a>
                    </li>
                @endif
                <li>
                    <a
                        href="{{ $dashboardUrl(['completed' => $currentCompleted ? null : 1, 'trashed' => null, 'due' => $currentCompleted ? $currentDue : null]) }}"
                        wire:navigate
                        class="{{ $navClass($currentCompleted && ! $currentTrashed) }}"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $currentCompleted ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                        <span class="min-w-0 flex-1 truncate">Completed</span>
                        <span class="shrink-0 font-mono text-[10px] text-gray-400">{{ $counts['completed'] }}</span>
                    </a>
                </li>
                @if ($canManageTrash ?? false)
                <li>
                    <a
                        href="{{ $dashboardUrl(['trashed' => $currentTrashed ? null : 1, 'completed' => null, 'due' => null, 'queue' => null]) }}"
                        wire:navigate
                        class="{{ $navClass($currentTrashed) }}"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $currentTrashed ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                        <span class="min-w-0 flex-1 truncate">Trash</span>
                        <span class="shrink-0 font-mono text-[10px] text-gray-400">{{ $counts['trashed'] ?? 0 }}</span>
                    </a>
                </li>
                @endif
                @if ($currentDue || $currentScope !== 'all' || $currentProject || $currentCompleted || $currentTrashed || $currentPriority || $currentAssignee || ($currentQueue ?? null))
                    <li>
                        <a href="{{ $dashboardUrl(['priority' => null, 'due' => null, 'project' => null, 'assignee' => null, 'scope' => 'all', 'completed' => null, 'trashed' => null]) }}" wire:navigate
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-300">
                            Clear filters
                        </a>
                    </li>
                @endif
            </ul>

            <h4 class="mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Views</h4>
            <ul class="space-y-0.5">
                @foreach ([
                    ['list', 'List', 'list'],
                    ['board', 'Board', 'board'],
                    ['table', 'Table', 'task'],
                    ['calendar', 'Calendar', 'calendar'],
                    ['timeline', 'Timeline', 'charts'],
                ] as [$view, $label, $icon])
                    @php $isActive = $onTaskDashboard && $currentView === $view && ! $currentTrashed; @endphp
                    <li>
                        <a
                            href="{{ $dashboardUrl(['view' => $view, 'trashed' => null]) }}"
                            wire:navigate
                            class="{{ $navClass($isActive) }}"
                        >
                            <span class="[&>svg]:h-4 [&>svg]:w-4 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg($icon) !!}</span>
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="shrink-0 border-t border-gray-200 p-2 dark:border-gray-800">
            @if (! $currentTrashed)
            <a
                href="{{ $dashboardUrl(['create' => 1, 'project' => $currentProject, 'view' => $currentView !== 'list' ? $currentView : null, 'scope' => $currentScope !== 'all' ? $currentScope : null]) }}"
                wire:navigate
                class="flex w-full items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-950/30"
            >
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                New task
            </a>
            @endif
        </div>
    </div>
</div>
