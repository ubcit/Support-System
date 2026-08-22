{{-- Workspace profile / account panel — settings pages only (not top-rail destinations). --}}
<div class="h-full overflow-y-auto no-scrollbar p-3">
    <h4 class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Account</h4>
    <ul class="space-y-1">
        <li>
            <a href="{{ route('workspace.profile') }}" wire:navigate
                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('workspace.profile') ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5' }}">
                <span class="[&>svg]:h-4 [&>svg]:w-4 shrink-0">{!! \App\Helpers\MenuHelper::getIconSvg('user-profile') !!}</span>
                Edit profile
            </a>
        </li>
    </ul>
</div>
