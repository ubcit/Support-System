<div>
    <x-common.page-breadcrumb pageTitle="Laravel Log Viewer" />

    @if (session('success'))
        <x-ui.alert variant="success" class="mb-4" :message="session('success')" />
    @endif
    @if (session('error'))
        <x-ui.alert variant="error" class="mb-4" :message="session('error')" />
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Application log</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 font-mono break-all">{{ $path }}</p>
                @if ($exists)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Size: {{ number_format($fileSize) }} bytes
                        @if ($truncated)
                            · truncated view
                        @endif
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button type="button" wire:click="refresh" variant="ghost" size="sm">Refresh</x-ui.button>
                <x-ui.confirm-button
                    type="button"
                    heading="Clear Laravel log?"
                    message="This will empty storage/logs/laravel.log. This cannot be undone."
                    confirm-label="Clear"
                    method="clear"
                    variant="danger"
                    size="sm"
                >
                    Clear log
                </x-ui.confirm-button>
            </div>
        </div>

        <div class="max-h-[70vh] overflow-auto custom-scrollbar bg-gray-950 px-4 py-4 sm:px-6">
            <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-gray-100">{{ $content }}</pre>
        </div>
    </div>
</div>
