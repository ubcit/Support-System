<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | The Space</title>
    <x-common.favicons />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' :
                        'light';
                    this.theme = savedTheme || systemTheme;
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-shell-canvas');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-shell-canvas');
                    }
                }
            });

            // Re-apply after Livewire morphs <body> on wire:navigate (strips client-added dark classes).
            document.addEventListener('livewire:navigated', () => {
                Alpine.store('theme')?.updateTheme();
            });

            // App-shell store: secondary sidebar collapse (persisted) + mobile drawer.
            // Primary sidebar is fixed/icon-only, so it has no expand/collapse state.
            Alpine.store('confirm', {
                open: false,
                heading: 'Are you sure?',
                message: '',
                confirmLabel: 'Confirm',
                cancelLabel: 'Cancel',
                onConfirm: null,

                ask({ heading, message, confirmLabel, cancelLabel, onConfirm }) {
                    this.heading = heading || 'Are you sure?';
                    this.message = message || '';
                    this.confirmLabel = confirmLabel || 'Confirm';
                    this.cancelLabel = cancelLabel || 'Cancel';
                    this.onConfirm = typeof onConfirm === 'function' ? onConfirm : null;
                    this.open = true;
                    document.body.classList.add('overflow-hidden');
                },

                close() {
                    this.open = false;
                    this.onConfirm = null;
                    document.body.classList.remove('overflow-hidden');
                },

                confirm() {
                    const fn = this.onConfirm;
                    this.close();
                    if (typeof fn === 'function') {
                        fn();
                    }
                },
            });

            Alpine.store('shell', {
                secondaryCollapsed: false,
                mobileOpen: false,
                hoveredPanelKey: null,
                hoverPanelTimer: null,

                init() {
                    this.secondaryCollapsed = localStorage.getItem('appShell.secondaryCollapsed') === '1';
                },

                toggleSecondary() {
                    this.secondaryCollapsed = !this.secondaryCollapsed;
                    localStorage.setItem('appShell.secondaryCollapsed', this.secondaryCollapsed ? '1' : '0');
                    this.hoveredPanelKey = null;
                    if (this.hoverPanelTimer) {
                        clearTimeout(this.hoverPanelTimer);
                        this.hoverPanelTimer = null;
                    }
                },

                setHoveredPanel(key) {
                    if (this.hoverPanelTimer) {
                        clearTimeout(this.hoverPanelTimer);
                        this.hoverPanelTimer = null;
                    }
                    this.hoveredPanelKey = key;
                },

                clearHoveredPanel(delayMs = 150) {
                    if (this.hoverPanelTimer) {
                        clearTimeout(this.hoverPanelTimer);
                    }
                    this.hoverPanelTimer = setTimeout(() => {
                        this.hoveredPanelKey = null;
                        this.hoverPanelTimer = null;
                    }, delayMs);
                },

                toggleMobile() {
                    this.mobileOpen = !this.mobileOpen;
                },

                closeMobile() {
                    this.mobileOpen = false;
                }
            });
        });
    </script>

    <!-- Apply dark mode immediately to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark', 'bg-shell-canvas');
            } else {
                document.documentElement.classList.remove('dark');
                document.body.classList.remove('dark', 'bg-shell-canvas');
            }
        })();
    </script>
    
</head>

<body>

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}

    @include('layouts.app-shell.index')

    @livewire('global-command-palette')

</body>

@stack('scripts')

</html>
