<div>
    <div class="space-y-6">
        <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:flex-row md:items-center md:justify-between md:p-6">
            <div>
                <span class="text-theme-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Executive command center</span>
                <h1 class="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90 sm:text-title-md">
                    {{ $greeting }}, {{ $boss_name }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Live operations briefing for {{ now()->format('l, M j') }}.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('task-dashboard', ['create' => 1]) }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path></svg>
                    New Task
                </a>
                <a href="{{ route('conversation-center') }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                    Live Inbox
                </a>
                <a href="{{ route('task-dashboard', ['view' => 'calendar']) }}"
                   class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">
                    Schedule
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
            <x-ui.metric-card label="Today's Work" :value="$today_work_count" href="{{ route('task-dashboard', ['due' => 'today']) }}" tone="default" hint="Due today">
                <x-slot:icon>
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 2C8.41421 2 8.75 2.33579 8.75 2.75V3.75H15.25V2.75C15.25 2.33579 15.5858 2 16 2C16.4142 2 16.75 2.33579 16.75 2.75V3.75H18.5C19.7426 3.75 20.75 4.75736 20.75 6V19C20.75 20.2426 19.7426 21.25 18.5 21.25H5.5C4.25736 21.25 3.25 20.2426 3.25 19V6C3.25 4.75736 4.25736 3.75 5.5 3.75H7.25V2.75C7.25 2.33579 7.58579 2 8 2ZM4.75 8.25H19.25V6C19.25 5.58579 18.9142 5.25 18.5 5.25H5.5C5.08579 5.25 4.75 5.58579 4.75 6V8.25ZM4.75 9.75V19C4.75 19.4142 5.08579 19.75 5.5 19.75H18.5C18.9142 19.75 19.25 19.4142 19.25 19V9.75H4.75Z" fill=""/></svg>
                </x-slot:icon>
            </x-ui.metric-card>

            <x-ui.metric-card label="Tasks Waiting" :value="$tasks_waiting" href="{{ route('task-dashboard', ['scope' => 'unassigned']) }}" tone="warning" hint="Unassigned">
                <x-slot:icon>
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2.25C6.61522 2.25 2.25 6.61522 2.25 12C2.25 17.3848 6.61522 21.75 12 21.75C17.3848 21.75 21.75 17.3848 21.75 12C21.75 6.61522 17.3848 2.25 12 2.25ZM12.75 7C12.75 6.58579 12.4142 6.25 12 6.25C11.5858 6.25 11.25 6.58579 11.25 7V12C11.25 12.4142 11.5858 12.75 12 12.75H16C16.4142 12.75 16.75 12.4142 16.75 12C16.75 11.5858 16.4142 11.25 16 11.25H12.75V7Z" fill=""/></svg>
                </x-slot:icon>
            </x-ui.metric-card>

            <x-ui.metric-card label="Conversations Waiting" :value="$conversations_waiting" href="{{ route('conversation-center') }}" tone="info" hint="Open inbox">
                <x-slot:icon>
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M4.5 5.25C3.25736 5.25 2.25 6.25736 2.25 7.5V14.25C2.25 15.4926 3.25736 16.5 4.5 16.5H7.93934L10.7197 19.2803C11.0126 19.5732 11.4874 19.5732 11.7803 19.2803L14.5607 16.5H19.5C20.7426 16.5 21.75 15.4926 21.75 14.25V7.5C21.75 6.25736 20.7426 5.25 19.5 5.25H4.5ZM7.5 9C7.08579 9 6.75 9.33579 6.75 9.75C6.75 10.1642 7.08579 10.5 7.5 10.5H16.5C16.9142 10.5 17.25 10.1642 17.25 9.75C17.25 9.33579 16.9142 9 16.5 9H7.5ZM6.75 12.75C6.75 12.3358 7.08579 12 7.5 12H12C12.4142 12 12.75 12.3358 12.75 12.75C12.75 13.1642 12.4142 13.5 12 13.5H7.5C7.08579 13.5 6.75 13.1642 6.75 12.75Z" fill=""/></svg>
                </x-slot:icon>
            </x-ui.metric-card>

            <x-ui.metric-card label="Overdue Tasks" :value="$overdue_tasks" href="{{ route('task-dashboard', ['due' => 'overdue']) }}" tone="danger" hint="Past due">
                <x-slot:icon>
                    <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2.25C6.61522 2.25 2.25 6.61522 2.25 12C2.25 17.3848 6.61522 21.75 12 21.75C17.3848 21.75 21.75 17.3848 21.75 12C21.75 6.61522 17.3848 2.25 12 2.25ZM12 8.25C12.4142 8.25 12.75 8.58579 12.75 9V12.75C12.75 13.1642 12.4142 13.5 12 13.5C11.5858 13.5 11.25 13.1642 11.25 12.75V9C11.25 8.58579 11.5858 8.25 12 8.25ZM12 17C12.5523 17 13 16.5523 13 16C13 15.4477 12.5523 15 12 15C11.4477 15 11 15.4477 11 16C11 16.5523 11.4477 17 12 17Z" fill=""/></svg>
                </x-slot:icon>
            </x-ui.metric-card>
        </div>

        <div class="grid grid-cols-12 gap-4 md:gap-6">
            <div class="col-span-12 xl:col-span-8 space-y-6">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Team capacity</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Active assignments by employee</p>
                        </div>
                        <a href="{{ route('employee-management') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">Manage team</a>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-800">
                        @forelse($employees as $employee)
                            <div class="flex items-center justify-between gap-3 px-5 py-3.5 sm:px-6 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-ui.person-avatar :person="$employee" size="xl" />
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $employee->name }}</p>
                                        <p class="truncate text-theme-xs text-gray-500 dark:text-gray-400">{{ $employee->role }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-theme-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                        {{ $employee->active_tasks }} active
                                    </span>
                                    <x-ui.status-badge :status="$employee->active_tasks > 5 ? 'Heavy Load' : 'Optimal'" />
                                </div>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No employees yet. <a href="{{ route('employee-management') }}" class="text-brand-500 hover:text-brand-600">Hire someone</a></div>
                        @endforelse
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Recent activity</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Latest task actions across the workspace</p>
                        </div>
                        <a href="{{ route('global-timeline') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">Open timeline</a>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-800">
                        @forelse($recent_activity as $act)
                            <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-800' : '' }}">
                                <div class="min-w-0 text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $act->employee?->name ?? 'System' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400"> {{ $act->summary() }} on </span>
                                    @if ($act->task)
                                        <a href="{{ route('task-detail', $act->task) }}" class="font-medium text-brand-500 hover:text-brand-600">{{ $act->task->title }}</a>
                                    @else
                                        <span class="italic">Task</span>
                                    @endif
                                </div>
                                <span class="shrink-0 text-theme-xs text-gray-400 dark:text-gray-500">{{ $act->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <div class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No activity logged yet today.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-span-12 space-y-6 xl:col-span-4">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">System health</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $unread_notifications_count }} unread notifications</p>
                        </div>
                        <a href="{{ route('operations-dashboard') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">Details</a>
                    </div>
                    <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                        @empty($system_health['checks'])
                            <p class="text-sm text-gray-500 dark:text-gray-400">No health checks registered.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($system_health['checks'] as $checkName => $checkData)
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $checkName }}</span>
                                        <div class="flex items-center gap-2">
                                            @if(!empty($checkData['metadata']['latency']))
                                                <span class="text-theme-xs font-mono text-gray-400">{{ $checkData['metadata']['latency'] }}ms</span>
                                            @elseif(!empty($checkData['metadata']['jobs_pending']))
                                                <span class="text-theme-xs font-mono text-gray-400">{{ $checkData['metadata']['jobs_pending'] }} pending</span>
                                            @endif
                                            <x-ui.status-badge :status="$checkData['status'] ?? 'Unknown'" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endempty
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Quick links</h3>
                    <div class="mt-4 space-y-2">
                        <a href="{{ route('project-hub') }}" class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:border-brand-500/40">
                            Project Hub <span class="text-theme-xs text-gray-400">{{ $total_projects }} projects</span>
                        </a>
                        <a href="{{ route('task-dashboard', ['view' => 'board']) }}" class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:border-brand-500/40">
                            Kanban Board <span class="text-theme-xs text-gray-400">Execute</span>
                        </a>
                        <a href="{{ route('reports-hub') }}" class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:border-brand-500/40">
                            Reports Hub <span class="text-theme-xs text-gray-400">Analytics</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
