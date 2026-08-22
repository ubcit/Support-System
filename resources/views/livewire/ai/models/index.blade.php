<div>
    <x-common.page-breadcrumb pageTitle="AI Models">
        <x-slot:actions>
            <x-ui.button href="{{ route('ai.models.create') }}">
                Create AI Model
            </x-ui.button>
        </x-slot:actions>
    </x-common.page-breadcrumb>


    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Model registry</h3>
            <div class="w-full max-w-sm">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search models..." class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
        </div>
        <div class="max-w-full overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-[960px]">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Name</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Provider</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Context</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">JSON</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Active</p></th>
                        <th class="px-5 py-3 text-left sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Shadow</p></th>
                        <th class="px-5 py-3 text-right sm:px-6"><p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Actions</p></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($models as $model)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-800 text-theme-sm dark:text-white/90 font-medium">{{ $model->name }}</p></td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ ucfirst($model->provider) }}</p></td>
                            <td class="px-5 py-4 sm:px-6"><p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ number_format($model->context_length) }}</p></td>
                            <td class="px-5 py-4 sm:px-6">
                                <x-ui.status-badge :status="$model->supports_json ? 'Success' : 'Inactive'">
                                    {{ $model->supports_json ? 'Yes' : 'No' }}
                                </x-ui.status-badge>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <x-ui.status-badge :status="$model->is_active ? 'Active' : 'Inactive'" />
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                @if($model->is_shadow_mode)
                                    <x-ui.status-badge status="Pending">Shadow</x-ui.status-badge>
                                @else
                                    <span class="text-theme-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex items-center justify-end gap-1">
                                    <x-ui.button href="{{ route('ai.models.view', $model) }}" variant="ghost" size="xs">View</x-ui.button>
                                    <x-ui.button href="{{ route('ai.models.edit', $model) }}" variant="ghost" size="xs">Edit</x-ui.button>
                                    <x-ui.confirm-button type="button" heading="Delete this model?" message="This cannot be undone." confirm-label="Delete" method="delete" :params="[$model->id]" variant="danger-ghost" size="xs">Delete</x-ui.confirm-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-sm text-gray-500">No models found. <a href="{{ route('ai.models.create') }}" class="text-brand-500">Create one</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $models->links() }}
    </div>
</div>
