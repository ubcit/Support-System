<div>
    <x-common.page-breadcrumb pageTitle="Boss Workspace">
        <x-slot:subtitle>Executive operations overview</x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('task-dashboard', ['create' => 1]) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">New Task</a>
            <a href="{{ route('employee-management') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Employee Hub</a>
        </x-slot:actions>
    </x-common.page-breadcrumb>

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
            <x-ui.metric-card label="Active Projects" :value="$total_projects" href="{{ route('project-hub') }}" />
            <x-ui.metric-card label="Customers" :value="$total_customers" href="{{ route('customer-crm') }}" tone="info" />
            <x-ui.metric-card label="Work Tasks" :value="$total_tasks" href="{{ route('task-dashboard') }}" />
            <x-ui.metric-card label="Overdue Escalations" :value="$overdue_tasks" href="{{ route('task-dashboard', ['due' => 'overdue']) }}" tone="danger" />
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Operations intelligence</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $insight }}</p>
                    <p class="mt-2 text-theme-xs text-gray-400">{{ $unassigned_tasks }} unassigned · {{ $total_employees }} employees</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('operations-dashboard') }}" class="inline-flex rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Health</a>
                    <a href="{{ route('task-dashboard', ['view' => 'calendar']) }}" class="inline-flex rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Schedule</a>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center justify-between px-5 py-4 sm:px-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Employee workload</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Active assignments and capacity signals</p>
                </div>
                <a href="{{ route('employee-management') }}" class="text-sm font-medium text-brand-500 hover:text-brand-600">Open hub</a>
            </div>
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[640px]">
                    <thead>
                        <tr class="border-t border-b border-gray-100 dark:border-gray-800">
                            <th class="px-5 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:px-6">Employee</th>
                            <th class="px-5 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:px-6">Role</th>
                            <th class="px-5 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:px-6">Active</th>
                            <th class="px-5 py-3 text-left text-theme-xs font-medium text-gray-500 dark:text-gray-400 sm:px-6">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employees as $emp)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-5 py-3.5 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <x-ui.person-avatar :person="$emp" size="lg" />
                                        <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $emp->name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400 sm:px-6">{{ $emp->role }}</td>
                                <td class="px-5 py-3.5 text-sm font-mono text-gray-700 dark:text-gray-300 sm:px-6">{{ $emp->active_count }}</td>
                                <td class="px-5 py-3.5 sm:px-6">
                                    <x-ui.status-badge :status="$emp->active_count > 5 ? 'Heavy Load' : 'Optimal'" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No employees found. <a href="{{ route('employee-management') }}" class="text-brand-500">Hire someone</a></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
