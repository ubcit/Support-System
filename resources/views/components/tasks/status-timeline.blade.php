@props([
    'task',
    'segments' => null,
])

@php
    $segments = $segments ?? $task->lifecycleSegments();

    $cycleLabel = $task->cycleDurationLabel();
    $currentStatusLabel = $task->status->label();
    $currentStatusTime = $task->currentStatusDurationLabel();
@endphp

<div class="space-y-4">
    <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
            <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Created
            </div>
            <div class="font-mono text-xs text-gray-700 dark:text-gray-300">
                {{ $task->created_at?->format('M d, Y H:i') ?? '—' }}
            </div>
            @if($task->created_at)
                <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                    {{ $task->created_at->diffForHumans() }}
                </div>
            @endif
        </div>

        <div class="text-right space-y-1">
            <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Cycle Time
            </div>
            <div class="font-mono text-sm font-bold text-brand-600 dark:text-brand-500">
                {{ $cycleLabel }}
            </div>
            <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                Current: {{ $currentStatusLabel }} • {{ $currentStatusTime }}
            </div>
        </div>
    </div>

    <div class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 space-y-6 mt-2">
        @foreach($segments as $seg)
            @php
                $status = $seg['status'];
                $isCurrent = (bool) ($seg['is_current'] ?? false);

                $dotClasses = $isCurrent
                    ? 'bg-brand-500 ring-4 ring-brand-500/30'
                    : match ($status) {
                        \Modules\Tasks\Enums\TaskStatus::Todo => 'bg-gray-400 ring-4 ring-gray-400/30',
                        \Modules\Tasks\Enums\TaskStatus::InProgress => 'bg-blue-500 ring-4 ring-blue-500/30',
                        \Modules\Tasks\Enums\TaskStatus::Review => 'bg-purple-500 ring-4 ring-purple-500/30',
                        \Modules\Tasks\Enums\TaskStatus::Done => 'bg-emerald-500 ring-4 ring-emerald-500/30',
                        \Modules\Tasks\Enums\TaskStatus::Cancelled => 'bg-red-500 ring-4 ring-red-500/30',
                        default => 'bg-gray-400 ring-4 ring-gray-400/30',
                    };
            @endphp

            <div class="relative pl-6">
                <div class="absolute w-3 h-3 rounded-full -left-1.75 top-1.5 {{ $dotClasses }}">
                    @if($isCurrent)
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-500 opacity-75"></span>
                    @endif
                </div>

                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span>{{ $seg['label'] }}</span>
                            @if($isCurrent)
                                <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-md bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400 border border-brand-200 dark:border-brand-500/20">
                                    Now
                                </span>
                            @endif
                        </div>

                        <div class="text-[10px] font-mono text-gray-500 dark:text-gray-400">
                            {{ $seg['entered_at']?->format('M d, H:i') ?? '—' }}
                            → {{ $seg['exited_at']?->format('M d, H:i') ?? 'now' }}
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-[10px] font-mono font-bold text-gray-600 dark:text-gray-300">
                            {{ $seg['duration_label'] ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
 </div>

