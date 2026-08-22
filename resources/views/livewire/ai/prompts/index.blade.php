<div>
    <x-common.page-breadcrumb pageTitle="AI Prompts">
        <x-slot:actions>
            <x-ui.button href="{{ route('ai.prompts.create') }}">
                Create AI Prompt
            </x-ui.button>
        </x-slot:actions>
    </x-common.page-breadcrumb>


    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Prompt library</h3>
            <div class="w-full max-w-sm">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search prompts..." class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
        </div>
        <div class="max-w-full overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[800px]">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Name</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Version</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Schema</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Active</p></th>
                        <th class="px-5 py-3 text-right sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prompts as $prompt)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-800 text-theme-sm dark:text-white/90 font-medium">{{ $prompt->name }}</p></td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $prompt->version }}</p></td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $prompt->schema?->name ?? '-' }}</p></td>
                            <td class="px-5 py-4 sm:px-6">
                                <x-ui.status-badge :status="$prompt->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.button href="{{ route('ai.prompts.view', $prompt) }}" variant="ghost" size="xs">View</x-ui.button>
                                    <x-ui.button href="{{ route('ai.prompts.edit', $prompt) }}" variant="ghost" size="xs">Edit</x-ui.button>
                                    <x-ui.confirm-button type="button" heading="Delete this prompt?" message="This cannot be undone." confirm-label="Delete" method="delete" :params="[$prompt->id]" variant="danger-ghost" size="xs">Delete</x-ui.confirm-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">No prompts found. <a href="{{ route('ai.prompts.create') }}" class="text-brand-500">Create one</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $prompts->links() }}
    </div>
</div>
