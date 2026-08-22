@php
    $user = auth()->user();
    $profileRoute = request()->is('workspace', 'workspace/*') ? route('workspace.profile') : route('profile');
@endphp

<div class="relative" x-data="{ dropdownOpen: false }" @click.outside="dropdownOpen = false">
    <button
        type="button"
        class="flex items-center text-gray-700 dark:text-gray-400"
        @click.prevent="dropdownOpen = !dropdownOpen"
        aria-label="Open profile menu"
        :aria-expanded="dropdownOpen"
    >
        <span class="overflow-hidden rounded-full h-10 w-10 lg:mr-3">
            <x-ui.person-avatar :person="$user" size="xl" class="h-full w-full" />
        </span>

        <span class="mr-1 hidden font-medium text-theme-sm lg:block">{{ $user?->name ?? 'Guest' }}</span>

        <svg :class="dropdownOpen && 'rotate-180'" class="hidden stroke-gray-500 transition-transform dark:stroke-gray-400 lg:block"
            width="18" height="20" viewBox="0 0 18 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4.3125 8.65625L9 13.3437L13.6875 8.65625" stroke="currentColor" stroke-width="1.5"
                stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    <div
        x-show="dropdownOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute right-0 z-50 mt-3 flex w-[280px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-800 dark:bg-gray-900"
    >
        <div class="flex items-start gap-3 rounded-xl bg-gray-50 p-3 dark:bg-white/5">
            <span class="h-11 w-11 shrink-0 overflow-hidden rounded-full">
                <x-ui.person-avatar :person="$user" size="xl" class="h-full w-full !h-11 !w-11" />
            </span>
            <div class="min-w-0">
                <span class="block truncate font-semibold text-gray-800 text-theme-sm dark:text-white">{{ $user?->name }}</span>
                <span class="mt-0.5 block truncate text-theme-xs text-gray-500 dark:text-gray-400">{{ $user?->email }}</span>
                @if ($user?->primaryRoleLabel())
                    <span class="mt-1 inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">{{ $user->primaryRoleLabel() }}</span>
                @endif
            </div>
        </div>

        <ul class="flex flex-col gap-1 border-b border-gray-200 pt-3 pb-3 dark:border-gray-800">
            <li>
                <a href="{{ $profileRoute }}" wire:navigate
                    class="group flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white">
                    <x-heroicon-o-user class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300" />
                    Edit profile
                </a>
            </li>
        </ul>

        <form method="POST" action="{{ route('logout') }}" class="mt-1">
            @csrf
            <button type="submit"
                class="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-theme-sm font-medium text-gray-700 hover:bg-red-50 hover:text-red-600 dark:text-gray-300 dark:hover:bg-red-950/30 dark:hover:text-red-400">
                <x-heroicon-o-arrow-right-on-rectangle class="h-4 w-4 shrink-0 text-gray-400 group-hover:text-red-600 dark:text-gray-500 dark:group-hover:text-red-400" />
                Sign out
            </button>
        </form>
    </div>
</div>
