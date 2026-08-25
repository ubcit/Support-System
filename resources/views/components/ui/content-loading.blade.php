@props([
    'target' => null,
])

{{--
    In-component loading overlay. Parent must be position:relative.
    Uses wire:loading.delay so brief requests do not flash.
--}}
<div
    {{ $attributes->class([
        'absolute inset-0 z-20 items-center justify-center rounded-[inherit] bg-white/60 backdrop-blur-[1px] dark:bg-gray-950/50',
    ]) }}
    wire:loading.delay.flex
    @if ($target) wire:target="{{ $target }}" @endif
    aria-busy="true"
    aria-live="polite"
>
    <div
        class="h-10 w-10 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"
        role="status"
        aria-label="Loading"
    ></div>
</div>
