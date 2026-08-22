<?php

namespace App\Helpers;

class UiButton
{
    public static function classes(string $variant = 'primary', string $size = 'md'): string
    {
        $base = 'inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 disabled:cursor-not-allowed disabled:opacity-50';

        $sizeClass = match ($size) {
            'xs' => 'h-8 gap-1.5 px-2.5 text-xs',
            'sm' => 'h-9 gap-1.5 px-3 text-sm',
            'md' => 'h-10 gap-2 px-4 text-sm',
            'lg' => 'h-11 gap-2 px-5 text-sm',
            'icon' => 'h-9 w-9 p-0',
            'icon-sm' => 'h-8 w-8 p-0',
            default => 'h-10 gap-2 px-4 text-sm',
        };

        $variantClass = match ($variant) {
            'primary' => 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600',
            'outline' => 'border border-gray-200 bg-white text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/10',
            'ghost' => 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10',
            'danger' => 'border border-gray-200 bg-white text-red-600 shadow-theme-xs hover:bg-red-50 hover:border-red-200 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-500/10 dark:hover:border-red-500/30',
            'danger-ghost' => 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10',
            default => 'bg-brand-500 text-white shadow-theme-xs hover:bg-brand-600',
        };

        return trim("{$base} {$sizeClass} {$variantClass}");
    }
}
