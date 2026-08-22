<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            View AI Prompt
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="{{ route('dashboard') }}">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('ai.prompts.index') }}">AI Prompts /</a></li>
                <li class="font-medium text-brand-500">View</li>
            </ol>
        </nav>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <!-- Basic Details -->
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Basic Details</h3>
            </div>
            <div class="p-5 flex flex-col gap-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                    <p class="text-base text-gray-800 dark:text-white/90 font-medium">{{ $prompt->name }}</p>
                </div>
                
                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Version</label>
                        <p class="text-base text-gray-800 dark:text-white/90">{{ $prompt->version }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Temperature</label>
                        <p class="text-base text-gray-800 dark:text-white/90">{{ $prompt->temperature ?? '-' }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Purpose</label>
                    <p class="text-base text-gray-800 dark:text-white/90">{{ $prompt->purpose ?? '-' }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Schema</label>
                    <p class="text-base text-gray-800 dark:text-white/90">
                        @if($prompt->schema)
                            <a href="{{ route('ai.schemas.view', $prompt->schema) }}" class="text-brand-500 hover:underline">{{ $prompt->schema->name }}</a>
                        @else
                            -
                        @endif
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                    <div>
                        @if($prompt->is_active)
                            <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-sm font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Active</span>
                        @else
                            <span class="inline-flex rounded-full bg-error-50 px-2.5 py-0.5 text-sm font-medium text-error-700 dark:bg-error-500/10 dark:text-error-400">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Prompts -->
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Prompts & Settings</h3>
            </div>
            <div class="p-5 flex flex-col gap-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">System Prompt</label>
                    <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800">
                        <pre class="whitespace-pre-wrap font-mono text-sm text-gray-700 dark:text-gray-300">{{ $prompt->system_prompt ?? 'None' }}</pre>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">User Prompt Template</label>
                    <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800">
                        <pre class="whitespace-pre-wrap font-mono text-sm text-gray-700 dark:text-gray-300">{{ $prompt->user_prompt_template ?? 'None' }}</pre>
                    </div>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Provider Overrides (JSON)</label>
                    <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800">
                        <pre class="whitespace-pre-wrap font-mono text-sm text-gray-700 dark:text-gray-300">{{ $prompt->provider_overrides ?? 'None' }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('ai.prompts.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
            Back to List
        </a>
        <a href="{{ route('ai.prompts.edit', $prompt) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-2.5 text-center font-medium text-white hover:bg-opacity-90">
            Edit Prompt
        </a>
    </div>
</div>
