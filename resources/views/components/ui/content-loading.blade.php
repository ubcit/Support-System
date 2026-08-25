@props([
    'target' => null,
])

{{--
    In-component loading overlay. Parent must be position:relative.

    Uses class toggles (not wire:loading.flex) so Livewire morph cannot leave a
    stuck display:flex inline style. wire:ignore keeps this node stable across
    re-renders so the loading directive is not torn down mid-request.
--}}
<div
    wire:ignore
    {{ $attributes->class([
        'pointer-events-none absolute inset-0 z-20 hidden items-center justify-center rounded-[inherit] bg-white/60 backdrop-blur-[1px] dark:bg-gray-950/50',
    ]) }}
    wire:loading.delay.class.remove="hidden"
    wire:loading.delay.class="!flex pointer-events-auto"
    @if ($target) wire:target="{{ $target }}" @endif
    aria-live="polite"
>
    <div
        class="h-10 w-10 animate-spin rounded-full border-4 border-solid border-brand-500 border-t-transparent"
        role="status"
        aria-label="Loading"
    ></div>
</div>
