<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            View AI Request Log
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="{{ route('dashboard') }}">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('ai.request-logs.index') }}">AI Request Logs /</a></li>
                <li class="font-medium text-brand-500">View</li>
            </ol>
        </nav>
    </div>

    <div class="grid grid-cols-1 gap-6">
        <!-- Request Metadata -->
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Request Metadata</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">UUID</label>
                        <p class="text-sm font-mono text-gray-800 dark:text-white/90">{{ $log->uuid }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Provider</label>
                        <p class="text-sm text-gray-800 dark:text-white/90 font-medium capitalize">{{ $log->provider }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Model Name</label>
                        <p class="text-sm text-gray-800 dark:text-white/90">{{ $log->model_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Validation Status</label>
                        <div>
                            @if($log->validation_status === 'passed')
                                <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-sm font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Passed</span>
                            @elseif($log->validation_status === 'failed')
                                <span class="inline-flex rounded-full bg-error-50 px-2.5 py-0.5 text-sm font-medium text-error-700 dark:bg-error-500/10 dark:text-error-400">Failed</span>
                            @else
                                <span class="inline-flex rounded-full bg-brand-50 px-2.5 py-0.5 text-sm font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">{{ ucfirst($log->validation_status) }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Latency</label>
                        <p class="text-sm text-gray-800 dark:text-white/90">{{ $log->latency_ms }} ms</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Tokens</label>
                        <p class="text-sm text-gray-800 dark:text-white/90">{{ number_format($log->total_tokens) }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Cost</label>
                        <p class="text-sm text-gray-800 dark:text-white/90">${{ number_format($log->cost, 4) }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Prompt</label>
                        <p class="text-sm text-gray-800 dark:text-white/90">
                            @if($log->aiPrompt)
                                <a href="{{ route('ai.prompts.view', $log->aiPrompt) }}" class="text-brand-500 hover:underline">{{ $log->aiPrompt->name }}</a>
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Created At</label>
                        <p class="text-sm text-gray-800 dark:text-white/90">{{ $log->created_at->format('M d, Y H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payloads -->
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Payloads</h3>
            </div>
            <div class="p-5 flex flex-col gap-6">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Raw Request</label>
                    <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800 max-h-96 overflow-y-auto custom-scrollbar">
                        <pre class="whitespace-pre-wrap font-mono text-xs text-gray-700 dark:text-gray-300">{{ is_array($log->raw_request) ? json_encode($log->raw_request, JSON_PRETTY_PRINT) : $log->raw_request }}</pre>
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Raw Response</label>
                    <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800 max-h-96 overflow-y-auto custom-scrollbar">
                        <pre class="whitespace-pre-wrap font-mono text-xs text-gray-700 dark:text-gray-300">{{ is_array($log->raw_response) ? json_encode($log->raw_response, JSON_PRETTY_PRINT) : $log->raw_response }}</pre>
                    </div>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Parsed JSON</label>
                    <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800 max-h-96 overflow-y-auto custom-scrollbar">
                        <pre class="whitespace-pre-wrap font-mono text-xs text-gray-700 dark:text-gray-300">{{ is_array($log->parsed_json) ? json_encode($log->parsed_json, JSON_PRETTY_PRINT) : ($log->parsed_json ?? 'None') }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <a href="{{ route('ai.request-logs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
            Back to List
        </a>
    </div>
</div>
