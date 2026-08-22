<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">Issues</h2>
        <div class="flex flex-wrap items-center gap-3">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search issues..."
                   class="rounded-lg border border-gray-300 bg-transparent px-4 py-2 text-sm text-gray-800 placeholder-gray-400 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90 dark:placeholder-gray-500" />
            <select wire:model.live="statusFilter"
                    class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 focus:border-brand-300 focus:outline-none dark:border-gray-700 dark:text-white/90">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Title</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Project</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Customer</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tasks</th>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($issues as $issue)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-4 text-sm font-medium text-gray-800 dark:text-white/90">
                                @if($issue->conversation_id)
                                    <a href="{{ route('conversation-center', ['conversation' => $issue->conversation_id]) }}" class="hover:text-brand-600 dark:hover:text-brand-400">{{ $issue->title }}</a>
                                @else
                                    {{ $issue->title }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $issue->status?->label() ?? '-' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                @if($issue->project_id)
                                    <a href="{{ route('project-hub', ['project' => $issue->project_id]) }}" class="hover:text-brand-600 dark:hover:text-brand-400">{{ $issue->project?->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                @if($issue->customer_id)
                                    <a href="{{ route('customer-crm', ['customer' => $issue->customer_id]) }}" class="hover:text-brand-600 dark:hover:text-brand-400">{{ $issue->customer?->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $issue->tasks_count }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-500 dark:text-gray-400">{{ $issue->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400 dark:text-gray-500">No issues found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($issues->hasPages())
            <div class="border-t border-gray-100 px-5 py-3 dark:border-gray-800">
                {{ $issues->links() }}
            </div>
        @endif
    </div>
</div>
