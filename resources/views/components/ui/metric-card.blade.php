@props([
    'label' => '',
    'value' => '',
    'href' => null,
    'tone' => 'default',
    'hint' => null,
])

@php
    $toneClasses = match ($tone) {
        'success' => 'text-success-600 dark:text-success-500',
        'warning' => 'text-warning-600 dark:text-warning-500',
        'danger' => 'text-error-600 dark:text-error-500',
        'info' => 'text-blue-light-500 dark:text-blue-light-500',
        default => 'text-gray-800 dark:text-white/90',
    };

    $iconBg = match ($tone) {
        'success' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
        'warning' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-500',
        'danger' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
        'info' => 'bg-blue-light-50 text-blue-light-500 dark:bg-blue-light-500/15 dark:text-blue-light-500',
        default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-white/90',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'block rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40 md:p-6']) }}>
@else
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] md:p-6']) }}>
@endif
    <div class="flex items-center justify-center w-12 h-12 rounded-xl {{ $iconBg }}">
        @if (isset($icon))
            {{ $icon }}
        @else
            <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M11.665 3.74706C11.8762 3.85838 12.007 4.07757 12.007 4.3158V10.9074H18.5986C18.8368 10.9074 19.056 11.0382 19.1673 11.2494C19.2787 11.4606 19.2643 11.7177 19.1301 11.9142L12.4168 20.9143C12.2861 21.0893 12.0787 21.1825 11.8654 21.1634C11.6521 21.1443 11.465 21.0162 11.3718 20.8252L9.11251 16.2107L4.49797 13.9514C4.30697 13.8582 4.17881 13.6711 4.15975 13.4578C4.1407 13.2445 4.23387 13.0371 4.40889 12.9064L13.409 6.19306C13.6055 6.05886 13.8626 6.04451 14.0738 6.15583C14.285 6.26714 14.4158 6.48633 14.4158 6.72459V9.40738H12.007V4.3158C12.007 3.95849 11.8034 3.6382 11.4828 3.48897C11.1622 3.33973 10.783 3.38914 10.5126 3.61506L3.51259 9.36506C3.24214 9.591 3.116 9.94857 3.19155 10.2922C3.2671 10.6358 3.53037 10.8991 3.87398 10.9746L7.46366 11.7801L2.08579 17.158C1.79289 17.4509 1.79289 17.9257 2.08579 18.2186C2.37868 18.5115 2.85355 18.5115 3.14645 18.2186L8.64645 12.7186C8.77147 12.5936 8.84519 12.4264 8.85398 12.2492C8.86278 12.072 8.80588 11.8985 8.69455 11.7622L5.60022 7.97374L8.40704 7.34327L10.5344 11.6933C10.6276 11.8843 10.8147 12.0124 11.028 12.0315C11.2413 12.0506 11.4487 11.9573 11.5794 11.7824L14.4158 8.00959V11.1574C14.4158 11.5716 14.7516 11.9074 15.1658 11.9074H18.0136L12.8801 18.6959L14.6294 15.1574C14.7786 14.8555 14.7281 14.4923 14.5021 14.2406C14.2761 13.9889 13.9211 13.8975 13.6048 14.0101L11.6048 14.7101C11.2153 14.8487 11.0117 15.2782 11.1503 15.6677C11.2889 16.0572 11.7184 16.2608 12.1079 16.1222L12.2926 16.0561L10.3708 19.9488L11.665 3.74706Z" fill=""/>
            </svg>
        @endif
    </div>

    <div class="flex items-end justify-between mt-5">
        <div>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</span>
            <h4 class="mt-2 font-bold text-title-sm {{ $toneClasses }}">{{ $value }}</h4>
            @if ($hint)
                <p class="mt-1 text-theme-xs text-gray-400 dark:text-gray-500">{{ $hint }}</p>
            @endif
        </div>
        @if (isset($trend))
            <div>{{ $trend }}</div>
        @endif
    </div>
@if ($href)
    </a>
@else
    </div>
@endif
