<div>
    <x-common.page-breadcrumb pageTitle="Reports & Analytics" compact>
        <x-slot:subtitle>Insights across tasks, customers, employees, and AI spend</x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('customer-ai-limits') }}" class="{{ \App\Helpers\UiButton::classes('outline') }}">AI Limits</a>
            <a href="{{ route('task-dashboard') }}" class="{{ \App\Helpers\UiButton::classes('primary') }}">Open Tasks</a>
        </x-slot:actions>
    </x-common.page-breadcrumb>

    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2 rounded-xl border border-gray-200 bg-white p-2 text-sm font-medium shadow-sm dark:border-gray-800 dark:bg-gray-800">
                @foreach (['overview' => 'Overview', 'tasks' => 'Tasks', 'employees' => 'Employees', 'customers' => 'Customers', 'ai-cost' => 'AI Cost'] as $key => $label)
                    <button type="button" wire:click="setTab('{{ $key }}')" class="rounded-lg px-4 py-2 transition-all {{ $tab === $key ? 'bg-brand-500 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($period_options as $key => $label)
                    <button type="button" wire:click="setPeriod('{{ $key }}')" class="rounded-lg border px-3 py-1.5 text-xs font-medium {{ $period === $key ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'border-gray-200 text-gray-600 dark:border-gray-700 dark:text-gray-400' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($tab === 'overview')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
                <x-ui.metric-card label="Completion Rate" :value="$overview['completion_rate'] . '%'" tone="success" href="{{ route('task-dashboard') }}" />
                <x-ui.metric-card label="Avg Resolution Time" :value="$overview['avg_response_time']" tone="info" />
                <x-ui.metric-card label="Tasks Processed" :value="$overview['total_tasks']" href="{{ route('task-dashboard') }}" />
                <x-ui.metric-card label="Active Projects" :value="$overview['total_projects']" href="{{ route('project-hub') }}" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.metric-card label="AI Spend Today" :value="$reports->formatUsd($overview['today_ai_spend'])" tone="info" href="{{ route('reports-hub', ['tab' => 'ai-cost']) }}" />
                <x-ui.metric-card label="Customers Over Budget" :value="$overview['customers_over_budget']" tone="{{ $overview['customers_over_budget'] ? 'warning' : 'default' }}" href="{{ route('customer-ai-limits') }}" />
                <x-ui.metric-card label="Sessions Need Review" :value="$overview['needs_review_sessions']" tone="{{ $overview['needs_review_sessions'] ? 'warning' : 'default' }}" href="{{ route('conversation-center') }}" />
            </div>
            @include('livewire.reports-hub.partials.velocity-chart', ['title' => 'Task completion velocity', 'subtitle' => 'Completed tasks over the last 7 days', 'footer' => $overview['completed_tasks'].' completed total · '.$overview['total_employees'].' employees'])
        @elseif ($tab === 'tasks')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
                <x-ui.metric-card label="Completion Rate" :value="$tasks['completion_rate'] . '%'" tone="success" />
                <x-ui.metric-card label="Completed in Period" :value="$tasks['period_completed']" />
                <x-ui.metric-card label="Overdue" :value="$tasks['overdue']" tone="{{ $tasks['overdue'] ? 'danger' : 'default' }}" />
                <x-ui.metric-card label="Unassigned" :value="$tasks['unassigned']" tone="{{ $tasks['unassigned'] ? 'warning' : 'default' }}" />
            </div>
            @include('livewire.reports-hub.partials.velocity-chart', ['title' => 'Task completion velocity', 'subtitle' => 'Completed tasks over the last 7 days', 'footer' => $tasks['avg_response_time'].' avg resolution'])
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">By priority</h3>
                    <div class="space-y-2">
                        @forelse ($tasks['by_priority'] as $priority => $count)
                            <div class="flex items-center justify-between text-sm">
                                <span class="capitalize text-gray-600 dark:text-gray-400">{{ $priority ?: 'unset' }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $count }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No tasks in this period.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">By project</h3>
                    <div class="space-y-2">
                        @forelse ($tasks['by_project'] as $row)
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate text-gray-600 dark:text-gray-400">{{ $row->project_name }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $row->total }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No tasks in this period.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @elseif ($tab === 'employees')
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Employee</th>
                            <th class="px-4 py-3">Active</th>
                            <th class="px-4 py-3">Completed</th>
                            <th class="px-4 py-3">Overdue</th>
                            <th class="px-4 py-3">Load</th>
                            <th class="px-4 py-3">Hours logged</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($employees as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['role'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['active'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['completed'] }}</td>
                                <td class="px-4 py-3 {{ $row['overdue'] ? 'text-error-600' : 'text-gray-700 dark:text-gray-300' }}">{{ $row['overdue'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="{{ $row['load_label'] === 'Heavy' ? 'text-warning-600' : 'text-gray-700 dark:text-gray-300' }}">{{ $row['active'] }}/{{ $row['max_workload'] }} {{ $row['load_label'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['logged_hours'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No employees yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($tab === 'customers')
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Conversations</th>
                            <th class="px-4 py-3">Sessions</th>
                            <th class="px-4 py-3">Needs review</th>
                            <th class="px-4 py-3">Tasks</th>
                            <th class="px-4 py-3">Period spend</th>
                            <th class="px-4 py-3">Daily budget</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($customers as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['conversations'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['sessions'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['needs_review'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['tasks'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $reports->formatUsd($row['period_spend']) }}</td>
                                <td class="px-4 py-3">
                                    @if ($row['over_budget'])
                                        <span class="rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">Over budget</span>
                                    @elseif ($row['daily_limit'] === null)
                                        <span class="text-xs text-gray-500">Unlimited</span>
                                    @else
                                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $reports->formatUsd($row['today_spend']) }} / {{ $reports->formatUsd((float) $row['daily_limit']) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No customers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">Estimated LLM spend from logged tokens. Set per-customer daily caps to send overflow to manual review.</p>
                <a href="{{ route('customer-ai-limits') }}" class="{{ \App\Helpers\UiButton::classes('outline', 'sm') }}">Manage daily limits</a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.metric-card label="Period Spend" :value="$reports->formatUsd($ai_cost['spend'])" tone="info" />
                <x-ui.metric-card label="Tokens" :value="number_format($ai_cost['tokens'])" />
                <x-ui.metric-card label="Requests" :value="$ai_cost['requests']" />
            </div>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Top customers by spend</h3>
                    <div class="space-y-2">
                        @forelse ($ai_cost['customers'] as $row)
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $row['name'] }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $reports->formatUsd($row['spend']) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No customer-attributed AI spend yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-4 text-sm font-semibold text-gray-800 dark:text-white/90">Top projects by spend</h3>
                    <div class="space-y-2">
                        @forelse ($ai_cost['projects'] as $row)
                            <div class="flex items-center justify-between text-sm">
                                <span class="truncate text-gray-700 dark:text-gray-300">{{ $row['name'] }}</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $reports->formatUsd($row['spend']) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No project-attributed AI spend yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white/90">Sessions by cost</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Session</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Project</th>
                            <th class="px-4 py-3">Tokens</th>
                            <th class="px-4 py-3">Cost</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($ai_cost['sessions'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ $row['url'] }}" class="font-medium text-brand-600 hover:underline">{{ $row['title'] }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['customer'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['project'] }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ number_format($row['tokens']) }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $reports->formatUsd($row['cost']) }}</td>
                                <td class="px-4 py-3 capitalize text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', $row['status']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No billed sessions in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
