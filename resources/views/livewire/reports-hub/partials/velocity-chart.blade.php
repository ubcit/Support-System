<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6">
    <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
        </div>
        <span class="text-theme-xs font-medium text-gray-400">{{ $footer }}</span>
    </div>

    <div class="relative h-52 border-b border-gray-200 pb-8 dark:border-gray-800">
        <div class="absolute left-0 top-0 flex h-full flex-col justify-between text-[10px] font-mono text-gray-400">
            <span>{{ $max_chart_value }}</span>
            <span>{{ round($max_chart_value / 2) }}</span>
            <span>0</span>
        </div>
        <div class="ml-8 flex h-full items-end justify-between gap-2">
            @foreach($chart_data as $data)
                <div class="group relative flex flex-1 flex-col items-center justify-end gap-2">
                    <div class="pointer-events-none absolute -top-8 rounded bg-gray-900 px-2 py-1 text-[10px] text-white opacity-0 transition group-hover:opacity-100 dark:bg-white dark:text-gray-900">
                        {{ $data['value'] }} tasks
                    </div>
                    <div
                        class="w-full max-w-[40px] rounded-t-md bg-brand-500 transition group-hover:bg-brand-600 dark:bg-brand-400"
                        style="height: {{ max(4, ($data['value'] / $max_chart_value) * 100) }}%"
                    ></div>
                    <span class="absolute -bottom-6 text-[10px] font-mono uppercase text-gray-500 dark:text-gray-400">{{ $data['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
