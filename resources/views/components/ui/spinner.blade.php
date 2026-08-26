@props([
    'size' => 'sm',
])

@php
    $sizeClass = match ($size) {
        'xs' => 'h-3 w-3 border',
        'sm' => 'h-3.5 w-3.5 border-2',
        'md' => 'h-5 w-5 border-2',
        'lg' => 'h-8 w-8 border-2',
        default => 'h-3.5 w-3.5 border-2',
    };
@endphp

<span
    {{ $attributes->class([
        'inline-block shrink-0 animate-spin rounded-full border-solid border-current border-t-transparent',
        $sizeClass,
    ]) }}
    role="status"
    aria-label="Loading"
></span>
