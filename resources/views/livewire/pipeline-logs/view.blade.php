<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            View Pipeline Inspector Log
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="{{ route('dashboard') }}">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('pipeline-logs.index') }}">Pipeline Inspector /</a></li>
                <li class="font-medium text-brand-500">View</li>
            </ol>
        </nav>
    </div>

    <div class="grid grid-cols-1 gap-6">
        <!-- Pipeline Information -->
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Pipeline Information</h3>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Execution ID</label>
                        <p class="text-sm font-mono text-gray-800 dark:text-white/90">{{ $log->uuid }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Correlation ID</label>
                        <p class="text-sm font-mono text-gray-800 dark:text-white/90">{{ $log->correlation_id ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                        <div>
                            @if($log->status === 'success')
                                <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-sm font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Success</span>
                            @elseif($log->status === 'failed')
                                <span class="inline-flex rounded-full bg-error-50 px-2.5 py-0.5 text-sm font-medium text-error-700 dark:bg-error-500/10 dark:text-error-400">Failed</span>
                            @elseif($log->status === 'running')
                                <span class="inline-flex rounded-full bg-warning-50 px-2.5 py-0.5 text-sm font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">Running</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-50 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-400">{{ ucfirst($log->status) }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Created At</label>
                        <p class="text-sm text-gray-800 dark:text-white/90">{{ $log->created_at->format('M d, Y H:i:s') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payload & Metrics -->
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h3 class="font-medium text-gray-800 dark:text-white/90">Payload & Metrics</h3>
            </div>
            <div class="p-5 flex flex-col gap-6">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Stage Timings</label>
                    <div class="overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Stage</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Time (ms)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if(is_array($log->stage_timings) && count($log->stage_timings) > 0)
                                    @foreach($log->stage_timings as $stage => $time)
                                        <tr class="border-t border-gray-100 dark:border-gray-800">
                                            <td class="px-4 py-2 text-sm text-gray-800 dark:text-white/90">{{ $stage }}</td>
                                            <td class="px-4 py-2 text-sm font-mono text-gray-600 dark:text-gray-400">{{ $time }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td colspan="2" class="px-4 py-4 text-center text-sm text-gray-500">No timings recorded.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Input Payload</label>
                        <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800 max-h-[500px] overflow-y-auto custom-scrollbar">
                            <pre class="whitespace-pre-wrap font-mono text-xs text-gray-700 dark:text-gray-300">{{ is_array($log->input_payload) ? json_encode($log->input_payload, JSON_PRETTY_PRINT) : ($log->input_payload ?? 'None') }}</pre>
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-400">Stage Status</label>
                        <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800 max-h-[500px] overflow-y-auto custom-scrollbar">
                            <pre class="whitespace-pre-wrap font-mono text-xs text-gray-700 dark:text-gray-300">{{ is_array($log->stage_status) ? json_encode($log->stage_status, JSON_PRETTY_PRINT) : ($log->stage_status ?? 'None') }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <a href="{{ route('pipeline-logs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
            Back to List
        </a>
    </div>
</div>
