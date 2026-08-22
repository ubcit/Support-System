@props([
    'size' => 'md',
    'variant' => 'primary',
    'href' => null,
    'disabled' => false,
])

@php
    $classes = \App\Helpers\UiButton::classes($variant, $size);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button
        type="{{ $attributes->get('type', 'button') }}"
        @if ($disabled) disabled @endif
        {{ $attributes->except('type')->class($classes) }}
    >
        {{ $slot }}
    </button>
@endif
