@props([
    'pageTitle' => 'Page',
    'compact' => false,
])

@php
    $subtitleText = isset($subtitle) ? trim((string) $subtitle) : '';
    $heading = $compact
        ? ($subtitleText !== '' ? $subtitle : null)
        : $pageTitle;
    $showSubtitle = ! $compact && $subtitleText !== '';
    $hasActions = isset($actions);
@endphp

@if ($heading || $showSubtitle || $hasActions)
    <div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-center justify-between gap-3']) }}>
        <div class="min-w-0">
            @if ($heading)
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                    {{ $heading }}
                </h2>
            @endif
            @if ($showSubtitle)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
        </div>

        @if ($hasActions)
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
@endif
