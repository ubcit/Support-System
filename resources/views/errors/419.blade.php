@php
    $currentYear = date('Y');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired | The Space</title>
    <x-common.favicons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if ((savedTheme || systemTheme) === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="bg-white dark:bg-gray-900">
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden p-6">
        <x-common.common-grid-shape />
        <div class="mx-auto w-full max-w-md text-center">
            <p class="text-7xl font-bold text-brand-500">419</p>
            <h1 class="mt-6 text-title-md font-semibold text-gray-800 dark:text-white/90">Session expired</h1>
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Your session has expired. Please refresh the page and try again.</p>
            <a href="javascript:location.reload()"
               class="mt-8 inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">
                Refresh Page
            </a>
        </div>
        <p class="absolute bottom-6 text-sm text-gray-500 dark:text-gray-400">&copy; {{ $currentYear }} — The Space</p>
    </div>
</body>
</html>
