@props([
    'tag',
    'removable' => false,
    'removeMethod' => null,
    'size' => 'sm',
])

@php
    $color = $tag->color ?? '#6B7280';
    $padding = $size === 'xs' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-0.5 text-[11px]';
@endphp

<span
    {{ $attributes->merge(['class' => "inline-flex items-center gap-1 {$padding} rounded-md font-semibold leading-none border border-black/5 dark:border-white/10"]) }}
    style="background-color: {{ $color }}22; color: {{ $color }};"
>
    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $color }};"></span>
    <span class="truncate max-w-[8rem]">{{ $tag->name }}</span>
    @if($removable && $removeMethod)
        <button type="button" wire:click="{{ $removeMethod }}" class="ml-0.5 rounded hover:opacity-70" title="Remove tag" aria-label="Remove tag">
            <x-heroicon-m-x-mark class="h-3 w-3"/>
        </button>
    @endif
</span>
