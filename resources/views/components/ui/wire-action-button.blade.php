@props([
    'target',
    'loadingLabel' => null,
])

@php
    $targetAttr = is_string($target) ? $target : (string) $target;
@endphp

<button
    type="{{ $attributes->get('type', 'button') }}"
    wire:loading.attr="disabled"
    wire:target="{{ $targetAttr }}"
    {{ $attributes->except('type')->class('relative inline-flex items-center justify-center disabled:pointer-events-none disabled:opacity-60') }}
>
    <span
        class="inline-flex items-center justify-center gap-1.5"
        wire:loading.remove
        wire:target="{{ $targetAttr }}"
    >
        {{ $slot }}
    </span>
    <span
        class="inline-flex items-center justify-center gap-1.5"
        wire:loading
        wire:target="{{ $targetAttr }}"
    >
        <x-ui.spinner size="sm" />
        @if ($loadingLabel)
            <span>{{ $loadingLabel }}</span>
        @endif
    </span>
</button>
