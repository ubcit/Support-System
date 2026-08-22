@props([
    'priority' => 'medium',
    'withLabel' => false,
])

@php
    $key = is_object($priority) ? (string) ($priority->value ?? 'medium') : (string) ($priority ?: 'medium');
    $dot = match ($key) {
        'urgent' => 'bg-red-500',
        'high' => 'bg-amber-500',
        'medium' => 'bg-brand-500',
        default => 'bg-gray-400',
    };
    $label = match ($key) {
        'urgent' => 'Urgent',
        'high' => 'High',
        'medium' => 'Medium',
        default => 'Low',
    };
    $text = match ($key) {
        'urgent' => 'text-red-600 dark:text-red-400',
        'high' => 'text-amber-600 dark:text-amber-400',
        'medium' => 'text-brand-600 dark:text-brand-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5', $withLabel ? $text : '']) }}>
    <span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
    @if ($withLabel)
        <span>{{ $label }}</span>
    @endif
</span>
