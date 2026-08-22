@props([
    'show' => false,
    'title' => '',
    'closeMethod' => null,
    'size' => 'md',
    'description' => null,
    'variant' => 'modal', // modal | drawer
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
@endphp

@if ($show)
    <div
        @class([
            'fixed inset-0 z-[99999] flex',
            'items-end justify-center sm:items-stretch sm:justify-end' => $isDrawer,
            'items-end justify-center sm:items-center sm:p-4' => ! $isDrawer,
        ])
        wire:key="modal-{{ md5($title . $size . $variant) }}"
        x-data
        x-init="document.body.classList.add('overflow-hidden')"
        @keydown.escape.window="$refs.closeBtn?.click()"
    >
        <div
            class="fixed inset-0 bg-gray-900/50 backdrop-blur-[2px]"
            @if ($closeMethod) wire:click="{{ $closeMethod }}" @endif
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
        >
            <div class="flex shrink-0 items-start justify-between gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                <div class="min-w-0 pr-2">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
                    @if ($description)
                        <p class="mt-0.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $description }}</p>
                    @endif
                </div>
                @if ($closeMethod)
                    <button
                        type="button"
                        x-ref="closeBtn"
                        wire:click="{{ $closeMethod }}"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 transition hover:bg-gray-200 hover:text-gray-800 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                        aria-label="Close"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            <div @class([
                'min-h-0 flex-1 overflow-y-auto px-5 sm:px-6',
                isset($footer) ? 'py-5' : 'py-5 pb-6',
            ])>
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t border-gray-100 bg-gray-50/90 px-5 py-4 dark:border-gray-800 dark:bg-white/[0.02] sm:px-6">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
@else
    <div x-data x-init="document.body.classList.remove('overflow-hidden')" class="hidden" aria-hidden="true"></div>
@endif
