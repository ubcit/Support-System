<div>
    <x-common.page-breadcrumb pageTitle="Certification Tests">
        <x-slot:actions>
            <x-ui.button href="{{ route('certification-tests.create') }}">
                Create Test
            </x-ui.button>
        </x-slot:actions>
    </x-common.page-breadcrumb>


    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Test suite</h3>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search tests..." class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:max-w-xs" />
                <select wire:model.live="category" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:max-w-[180px]">
                    <option value="">All Categories</option>
                    <option value="functional">Functional</option>
                    <option value="chaos">Chaos</option>
                    <option value="golden">Golden</option>
                    <option value="regression">Regression</option>
                    <option value="performance">Performance</option>
                </select>
                <select wire:model.live="is_golden" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 sm:max-w-[180px]">
                    <option value="">All Types</option>
                    <option value="1">Golden Tests Only</option>
                    <option value="0">Standard Tests Only</option>
                </select>
            </div>
        </div>
        <div class="max-w-full overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[800px]">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Name</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Category</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Golden</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Active</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Created</p></th>
                        <th class="px-5 py-3 text-right sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tests as $test)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-800 text-theme-sm dark:text-white/90 font-medium">{{ $test->name }}</p></td>
                            <td class="px-5 py-4 sm:px-6">
                                @php
                                    $colors = [
                                        'functional' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400',
                                        'chaos' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
                                        'golden' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
                                        'regression' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
                                        'performance' => 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400',
                                    ];
                                    $color = $colors[$test->category] ?? 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $color }}">{{ ucfirst($test->category) }}</span>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <x-ui.status-badge :status="$test->is_golden ? 'Pending' : 'Inactive'">
                                    {{ $test->is_golden ? 'Yes' : 'No' }}
                                </x-ui.status-badge>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <x-ui.status-badge :status="$test->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $test->created_at->format('M d, Y') }}</p></td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.button href="{{ route('certification-tests.view', $test) }}" variant="ghost" size="xs">View</x-ui.button>
                                    <x-ui.button href="{{ route('certification-tests.edit', $test) }}" variant="ghost" size="xs">Edit</x-ui.button>
                                    <x-ui.confirm-button type="button" heading="Delete this test?" message="This cannot be undone." confirm-label="Delete" method="delete" :params="[$test->id]" variant="danger-ghost" size="xs">Delete</x-ui.confirm-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No certification tests found. <a href="{{ route('certification-tests.create') }}" class="text-brand-500">Create one</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $tests->links() }}
    </div>
</div>
