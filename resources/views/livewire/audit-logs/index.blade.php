<div>
    <x-common.page-breadcrumb pageTitle="Audit Log" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] lg:col-span-7">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Database operations</h3>
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="text-sm font-medium text-brand-500 hover:text-brand-600"
                    >
                        Reset filters
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Search</label>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Values, URL, IP..."
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>

                    <div>
                        <x-form.select.searchable
                            wire:model.live="action"
                            label="Action"
                            :options="$actionOptions"
                            placeholder="All actions"
                            empty-option="All actions"
                            search-placeholder="Search actions..."
                            size="sm"
                        />
                    </div>

                    <div>
                        <x-form.select.searchable
                            wire:model.live="modelType"
                            label="Model"
                            :options="$modelTypeOptions"
                            placeholder="All models"
                            empty-option="All models"
                            search-placeholder="Search models..."
                            size="sm"
                        />
                    </div>

                    <div>
                        <x-form.select.searchable
                            wire:model.live="userId"
                            label="User"
                            :options="$userOptions"
                            placeholder="All users"
                            empty-option="All users"
                            search-placeholder="Search users..."
                            size="sm"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">From</label>
                        <input
                            type="date"
                            wire:model.live="dateFrom"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">To</label>
                        <input
                            type="date"
                            wire:model.live="dateTo"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                        />
                    </div>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto custom-scrollbar">
                <table class="w-full min-w-[720px]">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">When</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Action</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Model</p></th>
                            <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actor</p></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr
                                wire:click="selectLog({{ $log->id }})"
                                class="cursor-pointer border-b border-gray-100 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03] {{ (int) $selectedLogId === (int) $log->id ? 'bg-brand-50/60 dark:bg-brand-500/10' : '' }}"
                            >
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">{{ $log->created_at?->format('M d, Y H:i:s') }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    @php
                                        $actionColor = match ($log->action) {
                                            'created' => 'success',
                                            'updated' => 'info',
                                            'deleted', 'force_deleted' => 'error',
                                            'restored' => 'warning',
                                            default => 'light',
                                        };
                                    @endphp
                                    <x-ui.badge :color="$actionColor" size="sm">{{ str_replace('_', ' ', $log->action) }}</x-ui.badge>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">{{ $log->modelLabel() }}</p>
                                    <p class="font-mono text-xs text-gray-500 dark:text-gray-400">#{{ $log->auditable_id }}</p>
                                </td>
                                <td class="px-5 py-4 sm:px-6">
                                    <p class="text-gray-800 text-theme-sm dark:text-white/90">{{ $log->user?->name ?? $log->employee?->name ?? 'System' }}</p>
                                    @if ($log->ip_address)
                                        <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $log->ip_address }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No audit log entries found for these filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                {{ $logs->links() }}
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] lg:col-span-5">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Change details</h3>
            </div>

            <div class="p-5 sm:p-6">
                @if ($selectedLog)
                    <div class="mb-4 space-y-2">
                        <p class="text-xl font-semibold text-gray-800 dark:text-white/90">
                            {{ ucfirst(str_replace('_', ' ', $selectedLog->action)) }}
                            {{ $selectedLog->modelLabel() }}
                            #{{ $selectedLog->auditable_id }}
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs font-mono text-gray-500 dark:text-gray-400">
                            <span class="rounded bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ $selectedLog->created_at?->toDateTimeString() }}</span>
                            @if ($selectedLog->user)
                                <span class="rounded bg-gray-100 px-2 py-1 dark:bg-gray-800">{{ $selectedLog->user->email }}</span>
                            @endif
                            @if ($selectedLog->url)
                                <span class="max-w-full truncate rounded bg-gray-100 px-2 py-1 dark:bg-gray-800" title="{{ $selectedLog->url }}">{{ $selectedLog->url }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Old values</h4>
                        <div class="overflow-x-auto rounded-lg bg-gray-900 p-4">
                            <pre class="text-sm font-mono text-red-300"><code>{{ json_encode($selectedLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null' }}</code></pre>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">New values</h4>
                        <div class="overflow-x-auto rounded-lg bg-gray-900 p-4">
                            <pre class="text-sm font-mono text-green-300"><code>{{ json_encode($selectedLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null' }}</code></pre>
                        </div>
                    </div>
                @else
                    <div class="flex h-64 flex-col items-center justify-center gap-3 text-gray-400">
                        <p class="text-sm">Select a row to inspect old vs new values</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
