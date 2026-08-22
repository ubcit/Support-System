{{-- Home / Dashboard: this section's pages, then shortcuts to other rail areas. --}}
@php
    $panelDestination = $flyoutDestination ?? $activeDestination;
@endphp

<div class="flex h-full flex-col">
    <div class="flex-1 overflow-y-auto no-scrollbar p-3">
        @if (! empty($panelDestination['subItems']))
            <h4 class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Insights</h4>
            <ul class="space-y-1">
                @foreach ($panelDestination['subItems'] as $item)
                    @php $isItemActive = \App\Helpers\MenuHelper::isActive($item['path']); @endphp
                    <li>
                        <a
                            href="{{ $item['path'] }}"
                            wire:navigate
                            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $isItemActive ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' }}"
                        >
                            <span class="[&>svg]:h-4 [&>svg]:w-4 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg(\App\Helpers\MenuHelper::resolveItemIcon($item['name'])) !!}</span>
                            <span class="truncate">{{ $item['name'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <h4 class="mb-1 {{ ! empty($panelDestination['subItems']) ? 'mt-4' : '' }} px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Quick launch</h4>
        <ul class="space-y-1">
            @forelse ($panelDestination['quickLaunch'] ?? [] as $item)
                @php $isItemActive = \App\Helpers\MenuHelper::isActive($item['path']); @endphp
                <li>
                    <a
                        href="{{ $item['path'] }}"
                        wire:navigate
                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $isItemActive ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' }}"
                    >
                        <span class="[&>svg]:h-4 [&>svg]:w-4 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg(\App\Helpers\MenuHelper::resolveItemIcon($item['name'])) !!}</span>
                        <span class="truncate">{{ $item['name'] }}</span>
                    </a>
                </li>
            @empty
                <li class="px-3 py-4 text-xs text-gray-400">Nothing here yet.</li>
            @endforelse
        </ul>
    </div>
</div>
