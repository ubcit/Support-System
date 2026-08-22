<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            Create AI Model
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="/">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('ai.models.index') }}">AI Models /</a></li>
                <li class="font-medium text-brand-500">Create</li>
            </ol>
        </nav>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
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
                    
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">API Model ID <span class="text-error-500">*</span></label>
                        <input type="text" wire:model="api_model_id" placeholder="e.g. gpt-4o, gemini-1.5-flash, llama-3.1-8b-instant" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        <p class="mt-1 text-[11px] text-gray-500">The exact model identifier sent to the provider API.</p>
                        @error('api_model_id') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Provider <span class="text-error-500">*</span></label>
                        <div class="relative z-20 bg-transparent">
                            <select wire:model="provider" class="h-11 w-full appearance-none rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 pr-11 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                <option value="openai">OpenAI</option>
                                <option value="gemini">Gemini</option>
                                <option value="groq">Groq</option>
                                <option value="anthropic">Anthropic</option>
                                <option value="qwen">Qwen Local</option>
                                <option value="ollama">Ollama Local</option>
                            </select>
                            <span class="pointer-events-none absolute top-1/2 right-4 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                <svg class="stroke-current" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.79175 7.396L10.0001 12.6043L15.2084 7.396" stroke="" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </div>
                        @error('provider') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Context Length</label>
                        <input type="number" wire:model="context_length" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('context_length') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
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

            <!-- Capabilities -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">Capabilities</h3>
                </div>
                <div class="p-5 flex flex-col gap-5">
                    @php
                        $capabilities = [
                            'supports_vision' => 'Supports Vision',
                            'supports_audio' => 'Supports Audio',
                            'supports_json' => 'Supports JSON',
                            'supports_tools' => 'Supports Tools',
                            'is_shadow_mode' => 'Shadow Mode (Run in background)',
                        ];
                    @endphp

                    @foreach($capabilities as $field => $label)
                        <div>
                            <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                                <div class="relative">
                                    <input type="checkbox" wire:model="{{ $field }}" class="sr-only peer" />
                                    <div class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-transparent peer-checked:border-brand-500 peer-checked:bg-brand-500 dark:border-gray-700">
                                        <span class="opacity-0 peer-checked:opacity-100">
                                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                                {{ $label }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('ai.models.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-2.5 text-center font-medium text-white hover:bg-opacity-90">
                <span wire:loading.remove wire:target="save">Create Model</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
</div>
