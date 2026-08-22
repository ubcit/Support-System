@php $isActive = (int) ($inbox['selected_session_id'] ?? 0) === (int) $session['id']; @endphp
<li>
    <a
        href="{{ \App\Helpers\InboxNav::url($inbox['tab'], $session['conversation_id'], $inbox['q'], $session['id']) }}"
        wire:navigate
        class="{{ $navClass($isActive) }} items-start"
        title="{{ $session['title'] }}"
    >
        <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ ($session['has_error'] ?? false) ? 'bg-red-500' : ($session['unread'] ? 'bg-emerald-500' : (($session['awaiting_review'] ?? false) ? 'bg-amber-500' : (($session['collecting'] ?? false) ? 'bg-sky-400 animate-pulse' : ($isActive ? 'bg-brand-500' : 'bg-gray-300 dark:bg-gray-600')))) }}"></span>
        <span class="min-w-0 flex-1">
            <span class="flex items-baseline justify-between gap-2">
                <span class="truncate {{ $session['unread'] ? 'font-semibold' : '' }}">{{ $session['title'] }}</span>
                <span class="flex shrink-0 items-center gap-1">
                    @if($session['has_error'] ?? false)
                        <span class="rounded-full bg-red-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-red-700 dark:bg-red-950/40 dark:text-red-400">Failed</span>
                    @elseif($session['collecting'] ?? false)
                        <span class="rounded-full bg-sky-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-950/40 dark:text-sky-400">AI</span>
                    @elseif($session['needs_review'] ?? false)
                        <span class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">Triage</span>
                    @elseif($session['awaiting_review'] ?? false)
                        <span class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">Review</span>
                    @endif
                    <span class="font-mono text-[11px] font-normal text-gray-500">{{ $session['time'] }}</span>
                </span>
            </span>
            <span class="mt-0.5 block min-w-0 truncate text-[11px] font-normal text-gray-500">{{ $session['preview'] }}</span>
        </span>
    </a>
</li>
