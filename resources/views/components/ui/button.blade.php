@props([
    'size' => 'md',
    'variant' => 'primary',
    'href' => null,
    'disabled' => false,
    'loadingTarget' => null,
    'loadingLabel' => null,
])

@php
    $classes = \App\Helpers\UiButton::classes($variant, $size);
    $target = $loadingTarget ?? $attributes->get('wire:target');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $attributes->get('type', 'button') }}"
        @if ($disabled) disabled @endif
        @if ($target)
            wire:loading.attr="disabled"
        @endif
        {{ $attributes->except('type')->class($classes.' relative inline-flex items-center justify-center gap-1.5 disabled:pointer-events-none disabled:opacity-60') }}
    >
        @if ($target)
            <span class="inline-flex items-center justify-center gap-1.5" wire:loading.remove wire:target="{{ $target }}">
                {{ $slot }}
            </span>
            <span class="inline-flex items-center justify-center gap-1.5" wire:loading wire:target="{{ $target }}">
                <x-ui.spinner size="sm" />
                @if ($loadingLabel)
                    <span>{{ $loadingLabel }}</span>
                @endif
            </span>
        @else
            {{ $slot }}
        @endif
    </button>
@endif
