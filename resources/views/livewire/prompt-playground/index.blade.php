<div>
    <x-common.page-breadcrumb pageTitle="Prompt Playground" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <form wire:submit.prevent="runPlayground" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AI Model</label>
                        <x-form.select.searchable
                            wire:model="ai_model_id"
                            :options="$models->pluck('name', 'id')->toArray()"
                            placeholder="Select an AI Model"
                            empty-option="Select an AI Model"
                            search-placeholder="Search models..."
                        />
                        @error('ai_model_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AI Prompt</label>
                        <x-form.select.searchable
                            wire:model="ai_prompt_id"
                            :options="$prompts->pluck('name', 'id')->toArray()"
                            placeholder="Select a Prompt"
                            empty-option="Select a Prompt"
                            search-placeholder="Search prompts..."
                        />
                        @error('ai_prompt_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Test Input (e.g. customer message)</label>
                        <textarea wire:model="customer_message" rows="4" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 focus-visible:shadow-none dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required></textarea>
                        @error('customer_message') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="chaos_mode" id="chaos_mode" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                        <label for="chaos_mode" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Enable Chaos Mode (Mock only)
                        </label>
                    </div>

                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" class="flex w-full justify-center rounded bg-brand-500 p-3 font-medium text-gray hover:bg-opacity-90">
                            Run Playground
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div>
            @if ($output)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Execution Result</h3>
                    
                    <div class="mb-4 text-sm text-gray-700 dark:text-gray-300 space-y-1">
                        <div>
                            <span class="font-bold">Status:</span> 
                            <span class="px-2 py-1 rounded {{ $output['validation_status'] === 'passed' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400' }}">
                                {{ $output['validation_status'] }}
                            </span>
                        </div>
                        <div><span class="font-bold">Latency:</span> {{ $output['latency_ms'] }}ms</div>
                        <div><span class="font-bold">Tokens:</span> {{ $output['total_tokens'] }}</div>
                        <div><span class="font-bold">Cost:</span> ${{ number_format($output['cost'], 5) }}</div>
                    </div>

                    @if ($output['error_message'])
                        <div class="text-red-600 mb-4 p-3 bg-red-50 dark:bg-red-500/10 dark:text-red-400 rounded-lg text-sm">
                            {{ $output['error_message'] }}
                        </div>
                    @endif

                    <h4 class="font-bold text-sm text-gray-900 dark:text-white mb-2">Parsed JSON</h4>
                    <pre class="text-xs bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 p-4 rounded-lg overflow-auto border border-gray-200 dark:border-gray-700">{{ json_encode($output['parsed_json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </div>
    </div>
</div>
