@props([
    'task',
])

@php
    $subtasks = $task->relationLoaded('directSubtasks')
        ? $task->directSubtasks
        : ($task->relationLoaded('subtasks') ? $task->subtasks : collect());
    $subtotal = $subtasks->count();
    $subdone = $subtasks->filter(fn ($s) => $s->isCompleted())->count();
    $attachmentsCount = (int) ($task->attachments_count ?? 0);
    $commentsCount = (int) ($task->comments_count ?? 0);
@endphp

@if($subtotal > 0 || $attachmentsCount > 0 || $commentsCount > 0)
    <div {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 shrink-0']) }}>
        @if($subtotal > 0)
            <span
                class="inline-flex items-center gap-1 rounded-md border border-gray-200/60 bg-gray-50 px-1.5 py-0.5 text-[10px] font-mono font-bold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                title="Subtasks: {{ $subdone }}/{{ $subtotal }} done"
            >
                <x-heroicon-o-queue-list class="h-3 w-3 text-gray-400 shrink-0"/>
                {{ $subdone }}/{{ $subtotal }}
            </span>
        @endif

        @if($attachmentsCount > 0)
            <span
                class="inline-flex items-center gap-1 rounded-md border border-gray-200/60 bg-gray-50 px-1.5 py-0.5 text-[10px] font-mono font-bold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                title="{{ $attachmentsCount }} attachment{{ $attachmentsCount === 1 ? '' : 's' }}"
            >
                <x-heroicon-o-paper-clip class="h-3 w-3 text-gray-400 shrink-0"/>
                {{ $attachmentsCount }}
            </span>
        @endif

        @if($commentsCount > 0)
            <span
                class="inline-flex items-center gap-1 rounded-md border border-gray-200/60 bg-gray-50 px-1.5 py-0.5 text-[10px] font-mono font-bold text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                title="{{ $commentsCount }} comment{{ $commentsCount === 1 ? '' : 's' }}"
            >
                <x-heroicon-o-chat-bubble-left-right class="h-3 w-3 text-gray-400 shrink-0"/>
                {{ $commentsCount }}
            </span>
        @endif
    </div>
@endif
