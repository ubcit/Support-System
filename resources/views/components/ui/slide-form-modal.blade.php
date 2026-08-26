@props([
    'show' => false,
    'entangle' => null,
    'title' => '',
    'closeMethod' => null,
    'size' => 'md',
    'description' => null,
    'variant' => 'modal', // modal | drawer
    'loadingTarget' => null,
])

@php
    // sm: 1–3 simple fields · md: short forms · lg: multi-row / grids · xl: dense editors
    $sizeClass = match ($size) {
        'sm' => 'max-w-md',
        'md' => 'max-w-xl',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-3xl',
        'full' => 'max-w-5xl',
        default => 'max-w-xl',
    };
    $isDrawer = $variant === 'drawer';
    $closeIsSet = is_string($closeMethod) && str_starts_with($closeMethod, '$set');
    $closeClick = $closeMethod
        ? ($closeIsSet
            ? "open = false; \$wire.{$closeMethod}"
            : "open = false; \$wire.{$closeMethod}()")
        : 'open = false';
@endphp

<div
    wire:ignore.self
    @if ($entangle)
        x-data="{ open: $wire.entangle(@js($entangle)).live }"
    @else
        x-data="{ open: {{ $show ? 'true' : 'false' }} }"
    @endif
>
    <div
        @class([
            'fixed inset-0 z-[99999] flex',
            'items-end justify-center sm:items-stretch sm:justify-end' => $isDrawer,
            'items-end justify-center sm:items-center sm:p-4' => ! $isDrawer,
        ])
        wire:key="modal-shell-{{ md5(($entangle ?? '').$title.$size.$variant) }}"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        x-effect="document.body.classList.toggle('overflow-hidden', !!open)"
        @keydown.escape.window="if (open) $refs.closeBtn?.click()"
    >
        <div
            class="fixed inset-0 bg-gray-900/50 backdrop-blur-[2px]"
            @click="{{ $closeClick }}"
        ></div>

        <div
            {{ $attributes->class([
                'relative z-10 flex w-full flex-col overflow-hidden border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900',
                $sizeClass,
                'rounded-t-2xl sm:h-full sm:max-h-none sm:rounded-none sm:border-y-0 sm:border-r-0' => $isDrawer,
                'max-h-[min(90dvh,100%)]' => $isDrawer,
                'rounded-t-2xl sm:rounded-2xl max-h-[min(90dvh,880px)]' => ! $isDrawer,
            ]) }}
            role="dialog"
            aria-modal="true"
            aria-label="{{ $title }}"
            @click.stop
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        >
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                <div class="min-w-0 pr-2">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
                    @if ($description)
                        <p class="mt-0.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $description }}</p>
                    @endif
                </div>
                <button
                    type="button"
                    x-ref="closeBtn"
                    @click="{{ $closeClick }}"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-800 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                    aria-label="Close"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div @class([
                'min-h-0 flex-1 overflow-y-auto px-5 sm:px-6',
                isset($footer) ? 'py-5' : 'py-5 pb-6',
            ])>
                @if ($loadingTarget)
                    <div wire:loading.flex wire:target="{{ $loadingTarget }}" class="min-h-[8rem] flex-col items-center justify-center gap-3 py-8">
                        <span class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-brand-500 border-t-transparent" aria-hidden="true"></span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">Loading…</span>
                    </div>
                    <div wire:loading.remove wire:target="{{ $loadingTarget }}">
                        {{ $slot }}
                    </div>
                @else
                    {{ $slot }}
                @endif
            </div>

            @isset($footer)
                <div
                    class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-gray-100 bg-gray-50/90 px-5 py-4 dark:border-gray-800 dark:bg-white/[0.02] sm:px-6"
                    @if ($loadingTarget) wire:loading.remove wire:target="{{ $loadingTarget }}" @endif
                >
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
