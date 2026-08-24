{{-- Overflow only: catalog pages that no rail section owns. --}}
@php $leftovers = $moreLeftoverItems ?? []; @endphp

<div class="h-full overflow-y-auto no-scrollbar p-3">
    <h4 class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Tools</h4>
    <ul class="space-y-1">
        @forelse ($leftovers as $item)
            @php
                $isItemActive = \App\Helpers\MenuHelper::isActive($item['path']);
                $isSignupRequests = str_contains($item['path'] ?? '', 'signup-requests');
            @endphp
            <li>
                <a
                    href="{{ $item['path'] }}"
                    wire:navigate
                    class="flex items-center gap-2 truncate rounded-lg px-3 py-2 text-sm font-medium {{ $isItemActive ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}"
                >
                    <span class="[&>svg]:h-4 [&>svg]:w-4 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg($item['icon'] ?? \App\Helpers\MenuHelper::resolveItemIcon($item['name'])) !!}</span>
                    <span class="min-w-0 flex-1 truncate">{{ $item['name'] }}</span>
                    @if ($isSignupRequests)
                        <livewire:app-shell.rail-badge kind="signup" placement="mobile" :key="'more-signup-badge-'.md5($item['path'])" />
                    @endif
                </a>
            </li>
        @empty
            <li class="px-3 py-4 text-xs text-gray-400">Nothing else to show.</li>
        @endforelse
    </ul>
</div>
