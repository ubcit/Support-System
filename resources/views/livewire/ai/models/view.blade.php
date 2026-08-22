<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            View AI Model: {{ $model->name }}
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="/">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('ai.models.index') }}">AI Models /</a></li>
                <li class="font-medium text-brand-500">View</li>
            </ol>
        </nav>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Basic Details</h3>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <div>
                    <span class="text-sm font-medium text-gray-500">Name</span>
                    <p class="text-gray-800 dark:text-white">{{ $model->name }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Provider</span>
                    <p class="text-gray-800 dark:text-white">{{ ucfirst($model->provider) }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Context Length</span>
                    <p class="text-gray-800 dark:text-white">{{ number_format($model->context_length) }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-500">Status</span>
                    <p class="mt-1">
                        @if($model->is_active)
                            <x-ui.badge color="success">Active</x-ui.badge>
                        @else
                            <x-ui.badge color="error">Inactive</x-ui.badge>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Capabilities</h3>
            </div>
            <div class="p-5 flex flex-col gap-4">
                <div class="flex items-center gap-2">
                    <span class="text-gray-800 dark:text-white">Supports Vision:</span>
                    @if($model->supports_vision) <x-ui.badge color="success">Yes</x-ui.badge> @else <x-ui.badge color="error">No</x-ui.badge> @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-800 dark:text-white">Supports Audio:</span>
                    @if($model->supports_audio) <x-ui.badge color="success">Yes</x-ui.badge> @else <x-ui.badge color="error">No</x-ui.badge> @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-800 dark:text-white">Supports JSON:</span>
                    @if($model->supports_json) <x-ui.badge color="success">Yes</x-ui.badge> @else <x-ui.badge color="error">No</x-ui.badge> @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-800 dark:text-white">Supports Tools:</span>
                    @if($model->supports_tools) <x-ui.badge color="success">Yes</x-ui.badge> @else <x-ui.badge color="error">No</x-ui.badge> @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-800 dark:text-white">Shadow Mode:</span>
                    @if($model->is_shadow_mode) <x-ui.badge color="warning">Enabled</x-ui.badge> @else <x-ui.badge color="error">Disabled</x-ui.badge> @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6">
        <a href="{{ route('ai.models.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
            Back to List
        </a>
        <a href="{{ route('ai.models.edit', $model) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-2.5 text-center font-medium text-white hover:bg-opacity-90 ml-3">
            Edit Model
        </a>
    </div>
</div>
