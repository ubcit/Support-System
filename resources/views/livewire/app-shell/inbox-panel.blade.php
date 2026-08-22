{{-- Inbox panel: filters + flat customer list. Sessions open in the main pane. --}}
@php
    $navClass = function (bool $active): string {
        $base = 'flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors';

        return $active
            ? $base.' bg-brand-50 text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-400'
            : $base.' text-gray-600 hover:bg-gray-100 hover:text-gray-800 dark:text-gray-300 dark:hover:bg-white/5 dark:hover:text-white';
    };
@endphp

<div class="flex h-full flex-col" wire:poll.10s>
    <div class="flex-1 overflow-y-auto no-scrollbar p-3">
        <h4 class="mb-1 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-500">Inbox</h4>
        <ul class="space-y-0.5">
            @foreach ([
                ['unread', 'Unread', $inbox['unread']],
                ['review', 'Review', $inbox['review'] ?? 0],
                ['all', 'All', $inbox['all']],
                ['done', 'Done', $inbox['done'] ?? $inbox['closed'] ?? 0],
            ] as [$filterTab, $label, $count])
                @php $isActive = $inbox['tab'] === $filterTab; @endphp
                <li>
                    <a
                        href="{{ \App\Helpers\InboxNav::url($filterTab, null, $inbox['q']) }}"
                        wire:navigate
                        class="{{ $navClass($isActive) }}"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $isActive ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                        <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                        <span class="shrink-0 font-mono text-[11px] text-gray-500">{{ $count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <form method="GET" action="{{ route('conversation-center') }}" class="mt-3 px-1">
            @if ($inbox['tab'] !== 'all')
                <input type="hidden" name="tab" value="{{ $inbox['tab'] }}">
            @endif
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15a6 6 0 100-12 6 6 0 000 12zM17 17l-4-4" />
                </svg>
                <input
                    type="search"
                    name="q"
                    value="{{ $inbox['q'] }}"
                    placeholder="Search customers..."
                    class="h-8 w-full rounded-lg border border-gray-200 bg-gray-50 pl-8 pr-3 text-xs text-gray-700 placeholder:text-gray-500 focus:border-brand-300 focus:bg-white focus:ring-2 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                >
            </div>
        </form>

        <h4 class="mb-1 mt-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-500">Customers</h4>
        @forelse ($inbox['customers'] as $customer)
            @php
                $customerId = $customer['id'] ?? null;
                $isActive = $customerId !== null
                    && (int) ($inbox['selected_customer_id'] ?? 0) === (int) $customerId;
                $unreadCount = (int) ($customer['unread_count'] ?? 0);
            @endphp
            <a
                href="{{ \App\Helpers\InboxNav::url($inbox['tab'], null, $inbox['q'], null, $customerId ? (int) $customerId : null) }}"
                wire:navigate
                class="mt-1 flex items-start gap-2.5 rounded-lg px-2.5 py-2 transition-colors {{ $isActive ? 'bg-brand-50 dark:bg-brand-500/[0.12]' : 'hover:bg-gray-100 dark:hover:bg-white/5' }}"
                title="{{ $customer['name'] }}"
            >
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">
                    {{ strtoupper(substr($customer['name'] ?? 'C', 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-baseline justify-between gap-2">
                        <span class="truncate text-sm font-semibold {{ $unreadCount > 0 ? 'text-gray-900 dark:text-white' : 'text-gray-800 dark:text-gray-100' }}">{{ $customer['name'] }}</span>
                        <span class="shrink-0 font-mono text-[11px] text-gray-500">{{ $customer['time'] ?? '' }}</span>
                    </span>
                    <span class="mt-0.5 flex items-center justify-between gap-2">
                        <span class="min-w-0 truncate text-[11px] text-gray-500">{{ $customer['preview'] ?? '' }}</span>
                        <span class="flex shrink-0 items-center gap-1">
                            @if ($unreadCount > 0)
                                <span class="rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unreadCount }}</span>
                            @endif
                            <span class="font-mono text-[10px] text-gray-400">{{ $customer['session_count'] ?? 0 }}</span>
                        </span>
                    </span>
                </span>
            </a>
        @empty
            <p class="px-3 py-6 text-center text-xs text-gray-500">
                @if ($inbox['q'] !== '')
                    No matching customers
                @elseif ($inbox['tab'] === 'unread')
                    Nothing unread
                @elseif ($inbox['tab'] === 'review')
                    Nothing waiting for approval
                @elseif ($inbox['tab'] === 'done')
                    No done sessions
                @else
                    No customers here
                @endif
            </p>
            @if ($inbox['tab'] !== 'all' || $inbox['q'] !== '')
                <a href="{{ \App\Helpers\InboxNav::url('all') }}" class="block px-3 text-center text-xs font-medium text-brand-500 hover:underline">View all</a>
            @else
                <a href="{{ route('conversation-center', ['create' => 1]) }}" class="block px-3 text-center text-xs font-medium text-brand-500 hover:underline">Start a thread</a>
            @endif
        @endforelse
    </div>
</div>
