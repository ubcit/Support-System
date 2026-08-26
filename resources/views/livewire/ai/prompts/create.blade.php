<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            Create AI Prompt
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="{{ route('dashboard') }}">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('ai.prompts.index') }}">AI Prompts /</a></li>
                <li class="font-medium text-brand-500">Create</li>
            </ol>
        </nav>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <!-- Basic Details -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">Basic Details</h3>
                </div>
                <div class="p-5 flex flex-col gap-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name <span class="text-error-500">*</span></label>
                        <input type="text" wire:model="name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('name') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="grid grid-cols-2 gap-5">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Version <span class="text-error-500">*</span></label>
                            <input type="text" wire:model="version" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            @error('version') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Temperature</label>
                            <input type="number" step="0.01" wire:model="temperature" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                            @error('temperature') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Purpose</label>
                        <input type="text" wire:model="purpose" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('purpose') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Schema</label>
                        <x-form.select.searchable
                            wire:model="ai_schema_id"
                            :options="$schemas->pluck('name', 'id')->toArray()"
                            placeholder="Select Schema (Optional)"
                            empty-option="Select Schema (Optional)"
                            search-placeholder="Search schemas..."
                        />
                        @error('ai_schema_id') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                            <div class="relative">
                                <input type="checkbox" wire:model="is_active" class="sr-only peer" />
                                <div class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-transparent peer-checked:border-brand-500 peer-checked:bg-brand-500 dark:border-gray-700">
                                    <span class="opacity-0 peer-checked:opacity-100">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            Is Active
                        </label>
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
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">System Prompt</label>
                        <textarea wire:model="system_prompt" rows="4" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        @error('system_prompt') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">User Prompt Template</label>
                        <textarea wire:model="user_prompt_template" rows="4" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        @error('user_prompt_template') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Provider Overrides (JSON)</label>
                        <textarea wire:model="provider_overrides" rows="3" class="w-full font-mono rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        @error('provider_overrides') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('ai.prompts.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-2.5 text-center font-medium text-white hover:bg-opacity-90">
                <span wire:loading.remove wire:target="save">Create Prompt</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                    <x-ui.spinner size="sm" />
                    Saving…
                </span>
            </button>
        </div>
    </form>
</div>
