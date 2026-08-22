<div wire:poll.15s>
    <x-common.page-breadcrumb pageTitle="Operations Dashboard">
        <x-slot:subtitle>Platform health — auto-refreshes every 15s</x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('ai.request-logs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">AI Logs</a>
            <a href="{{ route('pipeline-logs.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Pipeline</a>
        </x-slot:actions>
    </x-common.page-breadcrumb>

    @php
        $overall = $health['overall_status'] ?? 'Unknown';
        $isHealthy = $overall === 'Healthy';
    @endphp

    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:gap-6">
            <x-ui.metric-card
                label="Platform Health"
                :value="$overall"
                :tone="$isHealthy ? 'success' : 'danger'"
                :hint="$isHealthy ? 'All systems nominal' : 'Degradation detected'"
                href="{{ route('benchmark-dashboard') }}"
            />
            <x-ui.metric-card
                label="AI Requests Today"
                :value="$ai_requests_today"
                tone="info"
                hint="Total AI calls tracked"
                href="{{ route('ai.request-logs.index') }}"
            />
        </div>

        @if(!empty($health['checks']))
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="px-5 py-4 sm:px-6">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Health checks</h3>
                </div>
                <div class="grid grid-cols-1 gap-3 border-t border-gray-100 p-5 dark:border-gray-800 md:grid-cols-2 sm:p-6">
                    @foreach($health['checks'] as $checkName => $checkData)
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50/60 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.02]">
                            <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $checkName }}</span>
                            <x-ui.status-badge :status="$checkData['status'] ?? 'Unknown'" />
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Infrastructure</h3>
            </div>
            <div class="grid grid-cols-1 gap-3 border-t border-gray-100 p-5 dark:border-gray-800 md:grid-cols-2 sm:p-6">
                @foreach($system_status as $name => $state)
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                        <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $name }}</span>
                        <x-ui.status-badge :status="$state" />
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
