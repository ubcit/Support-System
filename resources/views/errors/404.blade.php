@php
    $currentYear = date('Y');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Not Found | The Space</title>
    <x-common.favicons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="bg-white dark:bg-gray-900">
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden p-6 z-1">
        <x-common.common-grid-shape />
        <div class="mx-auto w-full max-w-[242px] text-center sm:max-w-[472px]">
            <h1 class="mb-8 font-bold text-gray-800 text-title-md dark:text-white/90 xl:text-title-2xl">ERROR</h1>

            @if (file_exists(public_path('images/error/404.svg')))
                <img src="{{ asset('images/error/404.svg') }}" alt="404" class="mx-auto dark:hidden" />
                <img src="{{ asset('images/error/404-dark.svg') }}" alt="404" class="mx-auto hidden dark:block" />
            @else
                <p class="text-7xl font-bold text-brand-500">404</p>
            @endif

            <p class="mt-10 mb-6 text-base text-gray-700 dark:text-gray-400 sm:text-lg">
                We can't seem to find the page you are looking for.
            </p>

            <a href="{{ auth()->check() ? route(auth()->user()->preferredHomeRoute()) : route('login') }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-3.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03] dark:hover:text-gray-200">
                {{ auth()->check() ? 'Back to Dashboard' : 'Back to Sign In' }}
            </a>
        </div>
        <p class="absolute bottom-6 left-1/2 -translate-x-1/2 text-center text-sm text-gray-500 dark:text-gray-400">
            &copy; {{ $currentYear }} — The Space
        </p>
    </div>
</body>
</html>
