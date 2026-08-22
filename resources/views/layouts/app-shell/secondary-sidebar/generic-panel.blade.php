{{-- Generic sub-nav panel: lists the active destination's related MenuHelper items. --}}
@php $panelDestination = $flyoutDestination ?? $activeDestination; @endphp
<div class="h-full overflow-y-auto no-scrollbar p-3">
    <ul class="space-y-1">
        @forelse ($panelDestination['subItems'] ?? [] as $item)
            @php $isItemActive = \App\Helpers\MenuHelper::isActive($item['path']); @endphp
            <li>
                <a
                    href="{{ $item['path'] }}"
                    wire:navigate
                    class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $isItemActive ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white' }}"
                >
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="[&>svg]:h-4 [&>svg]:w-4 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg(\App\Helpers\MenuHelper::resolveItemIcon($item['name'])) !!}</span>
                        <span class="truncate">{{ $item['name'] }}</span>
                    </span>
                    @if (! empty($item['new']))
                        <span class="shrink-0 rounded bg-brand-500 px-1.5 py-0.5 text-[9px] font-semibold uppercase text-white">new</span>
                    @endif
                </a>
            </li>
        @empty
            <li class="px-3 py-4 text-xs text-gray-400">Nothing here yet.</li>
        @endforelse
    </ul>
</div>
