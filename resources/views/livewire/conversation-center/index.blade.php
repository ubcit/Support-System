<div wire:poll.{{ $copilot['state'] === 'pending' ? '2s' : '5s' }}="refreshOpenThread">
    <x-common.page-breadcrumb pageTitle="Inbox" compact>
        <x-slot:subtitle>
            {{ match ($filter_tab) {
                'unread' => 'Sessions waiting on a reply',
                'review' => 'Sessions waiting for manager approval',
                'done' => 'Sessions whose tasks are done',
                default => 'Session chat and customer context',
            } }}
        </x-slot:subtitle>
        <x-slot:actions>
            <x-ui.button wire:click="openStartModal">
                <x-heroicon-m-plus class="h-4 w-4"/> Start Conversation
            </x-ui.button>
        </x-slot:actions>
    </x-common.page-breadcrumb>

    @if (session('success'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800 dark:border-red-800/40 dark:bg-red-950/40 dark:text-red-300">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 h-[calc(100vh-140px)] min-h-[600px]">
        <!-- Chat Thread -->
        <div class="lg:col-span-8 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-white/10 flex flex-col overflow-hidden shadow-sm">
            @if($selected_session)
                <div class="p-4 border-b border-gray-100 dark:border-white/10 flex items-center justify-between bg-gray-50 dark:bg-white/5">
                    <div class="flex items-center gap-3 min-w-0">
                        <a
                            href="{{ \App\Helpers\InboxNav::url($filter_tab, null, $searchQuery, null, $selectedCustomerId ?? $selected_conversation?->customer_id) }}"
                            wire:navigate
                            class="shrink-0 rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-white hover:text-gray-800 dark:border-white/10 dark:hover:bg-white/5 dark:hover:text-white"
                            title="Back to sessions"
                            aria-label="Back to sessions"
                        >
                            <x-heroicon-m-arrow-left class="h-4 w-4"/>
                        </a>
                        <div class="w-9 h-9 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-xs shadow-sm shrink-0">
                            {{ substr($selected_conversation?->customer?->name ?? 'C', 0, 1) }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ $selected_conversation?->customer?->name ?? 'Customer' }}</h3>
                                @if($selected_session->isDone())
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">Done</span>
                                @elseif($selected_session->hasTasksAwaitingReview())
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">Waiting for approval</span>
                                @elseif($selected_session->status->value === 'needs_review')
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">Needs review</span>
                                @elseif($selected_session->status->value === 'awaiting_verification')
                                    <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-violet-700 dark:bg-violet-950/40 dark:text-violet-400">Awaiting code</span>
                                @endif
                            </div>
            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $selected_session->displayTitle() }} · WhatsApp: {{ $selected_conversation?->customer?->phone }}</span>
                        </div>
                    </div>
                    <x-ui.confirm-button
                        heading="Delete this conversation?"
                        message="The thread and its messages will be removed."
                        confirm-label="Delete"
                        method="deleteConversation"
                        :params="[$selected_conversation?->id]"
                        variant="danger"
                        size="icon"
                        title="Delete Conversation"
                        aria-label="Delete Conversation"
                    >
                        <x-heroicon-m-trash class="h-4 w-4"/>
                    </x-ui.confirm-button>
                </div>

                <div class="flex-1 p-4 overflow-y-auto space-y-3 bg-gray-50 dark:bg-gray-900">
                    @forelse($selected_session->messages as $m)
                        @php
                            $inbound = $m->direction === \Modules\Communication\Enums\MessageDirection::Inbound || $m->direction === 'inbound';
                            $isSelected = in_array((int) $m->id, array_map('intval', $selectedMessageIds), true);
                        @endphp
                        @if($inbound)
                            <div class="flex flex-col items-start max-w-[80%]">
                                <button
                                    type="button"
                                    wire:click="toggleMessageSelection({{ $m->id }})"
                                    class="relative  text-left bg-white dark:bg-gray-800 p-3 rounded-2xl rounded-tl-sm shadow-sm border text-xs text-gray-900 dark:text-gray-200 {{ $isSelected ? 'border-brand-400 ring-2 ring-brand-500/40' : 'border-gray-200 dark:border-white/10' }}"
                                >
                                    @if($isSelected)
                                        <span class="absolute -top-1.5 -right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-brand-500 text-white">
                                            <x-heroicon-s-check-circle class="h-3 w-3"/>
                                        </span>
                                    @endif
                                    {{ $m->body }}
                                    @if($m->attachments->isNotEmpty())
                                        <div class="mt-2 space-y-1.5">
                                            @foreach($m->attachments as $att)
                                                @php
                                                    $mimeType = (string) ($att->mime_type ?? '');
                                                    $mediaUrl = route('attachments.media', ['uuid' => $att->uuid]);
                                                    $downloadUrl = route('attachments.download', ['uuid' => $att->uuid]);
                                                    $isImage = str_contains($mimeType, 'image/');
                                                    $isVideo = str_contains($mimeType, 'video/');
                                                    $isAudio = str_contains($mimeType, 'audio/');
                                                @endphp

                                                <a
                                                    href="{{ ($isImage || $isVideo || $isAudio) ? $mediaUrl : $downloadUrl }}"
                                                    target="_blank"
                                                    @click.stop
                                                    class="flex w-full items-center gap-2 rounded border border-gray-100 bg-gray-50 p-2 text-left dark:border-gray-700 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition"
                                                    title="{{ $att->original_name ?? 'Attachment' }}"
                                                >
                                                    @if($isImage)
                                                        <img
                                                            src="{{ $mediaUrl }}"
                                                            alt="{{ $att->original_name ?? 'Image' }}"
                                                            loading="lazy"
                                                            class="w-14 h-14 rounded object-cover bg-gray-100 dark:bg-gray-800 shrink-0"
                                                        >
                                                    @elseif($isVideo)
                                                        <div class="w-14 h-14 rounded bg-black shrink-0 flex items-center justify-center">
                                                            <x-heroicon-s-play class="w-6 h-6 text-white/80"/>
                                                        </div>
                                                    @elseif($isAudio)
                                                        <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                                                            <x-heroicon-o-microphone class="w-4 h-4"/>
                                                        </div>
                                                    @else
                                                        <x-heroicon-o-paper-clip class="w-4 h-4 text-gray-500 shrink-0"/>
                                                    @endif

                                                    <span class="text-xs text-brand-600 dark:text-brand-400 truncate">
                                                        {{ $att->original_name ?? 'Attachment' }}
                                                    </span>
                                                </a>
                                                @if($att->ai_transcript)
                                                    <p class="text-[10px] italic text-gray-500 dark:text-gray-400 px-1">{{ $att->ai_transcript }}</p>
                                                @endif
                                            @endforeach
                                        </div>
                                    @elseif(isset($m->metadata['attachment_url']))
                                        <div class="mt-2 p-2 border border-gray-100 dark:border-gray-800 rounded flex items-center gap-2 bg-gray-50 dark:bg-gray-800" @click.stop>
                                            <x-heroicon-o-paper-clip class="w-4 h-4 text-gray-500"/>
                                            <a href="{{ $m->metadata['attachment_url'] }}" target="_blank" class="text-xs text-brand-600 hover:underline">View Attachment</a>
                                        </div>
                                    @endif
                                </button>
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 font-mono mt-1 ml-1">{{ $m->created_at->format('H:i:s') }}</span>
                            </div>
                        @else
                            <div class="flex flex-col items-end max-w-[80%] ml-auto">
                                <button
                                    type="button"
                                    wire:click="toggleMessageSelection({{ $m->id }})"
                                    class="relative w-full text-left bg-emerald-600 text-white p-3 rounded-2xl rounded-tr-sm shadow-sm text-xs {{ $isSelected ? 'ring-2 ring-brand-300' : '' }}"
                                >
                                    @if($isSelected)
                                        <span class="absolute -top-1.5 -left-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-brand-500 text-white">
                                            <x-heroicon-s-check-circle class="h-3 w-3"/>
                                        </span>
                                    @endif
                                    {{ $m->body }}
                                </button>
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 font-mono mt-1 mr-1">{{ $m->created_at->format('H:i:s') }}</span>
                                @if($m->status === \Modules\Communication\Enums\MessageStatus::Failed || $m->status === 'failed')
                                    <span class="text-[10px] text-red-500 mt-0.5 mr-1 max-w-full text-right">{{ $m->metadata['send_error'] ?? 'WhatsApp send failed' }}</span>
                                @endif
                            </div>
                        @endif
                    @empty
                        <div class="text-center text-xs text-gray-500 dark:text-gray-400 py-12">No messages in this session yet.{{ ($can_reply ?? false) ? ' Send the first reply below.' : '' }}</div>
                    @endforelse
                </div>

                @if(count($selectedMessageIds) > 0)
                    <div class="flex items-center justify-between gap-2 border-t border-brand-100 bg-brand-50 px-3 py-2 dark:border-brand-900/40 dark:bg-brand-950/30">
                        <p class="text-[11px] font-semibold text-brand-800 dark:text-brand-300">{{ count($selectedMessageIds) }} selected</p>
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="clearMessageSelection" class="rounded-lg px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-white/5">Clear</button>
                            <button type="button" wire:click="openApproveModalFromSelection" class="rounded-lg bg-brand-500 px-3 py-1 text-[11px] font-semibold text-white hover:bg-brand-600">Create task</button>
                        </div>
                    </div>
                @endif

                @if($can_reply ?? false)
                <div class="p-3 border-t border-gray-100 dark:border-white/10 bg-white dark:bg-gray-800 flex gap-2">
                    <input type="text" wire:model="replyMessage" wire:keydown.enter="sendReply" placeholder="Type WhatsApp reply..."
                        class="flex-1 p-1 rounded-xl border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 text-xs text-gray-900 dark:text-gray-200 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-brand-500 focus:border-brand-500">
                    <button wire:click="sendReply" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs rounded-xl shadow transition">
                        Send
                    </button>
                </div>
                @else
                <div class="p-3 border-t border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">This session is read-only. Open the latest session to reply on WhatsApp.</p>
                </div>
                @endif
            @elseif($selected_group)
                <div class="flex-1 flex flex-col overflow-hidden bg-gray-50 dark:bg-gray-900">
                    <div class="flex items-center gap-3 border-b border-gray-100 bg-white px-4 py-3 dark:border-white/10 dark:bg-gray-800">
                        <a
                            href="{{ \App\Helpers\InboxNav::url($filter_tab, null, $searchQuery) }}"
                            wire:navigate
                            class="shrink-0 rounded-lg border border-gray-200 p-1.5 text-gray-500 hover:bg-gray-50 hover:text-gray-800 dark:border-white/10 dark:hover:bg-white/5 dark:hover:text-white"
                            title="Back to customers"
                            aria-label="Back to customers"
                        >
                            <x-heroicon-m-arrow-left class="h-4 w-4"/>
                        </a>
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">
                            {{ strtoupper(substr($selected_group['name'] ?? 'C', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $selected_group['name'] }}</h3>
                            @if (! empty($selected_group['phone']))
                                <p class="truncate font-mono text-[11px] text-gray-500">{{ $selected_group['phone'] }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 font-mono text-[11px] text-gray-500">{{ $selected_group['session_count'] ?? 0 }} sessions</span>
                    </div>
                    <div
                        class="flex-1 overflow-y-auto p-4"
                        x-data="{
                            openExpanded: true,
                            doneExpanded: @js($filter_tab === 'done'),
                            showAllOpen: false,
                            openLimit: 5
                        }"
                    >
                        @php
                            $openSessions = $selected_group['open'] ?? [];
                            $doneSessions = $selected_group['done'] ?? [];
                            $openCount = count($openSessions);
                            $doneCount = count($doneSessions);
                        @endphp
                        @if ($openSessions !== [])
                            <div class="mb-4">
                                <button
                                    type="button"
                                    @click="openExpanded = !openExpanded"
                                    class="mb-2 flex w-full items-center gap-1.5 rounded-lg px-1 py-1 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 hover:bg-white/60 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
                                    :aria-expanded="openExpanded ? 'true' : 'false'"
                                >
                                    <x-heroicon-m-chevron-right class="h-3.5 w-3.5 shrink-0 transition-transform" x-bind:class="openExpanded && 'rotate-90'" />
                                    <span class="min-w-0 flex-1">In progress</span>
                                    <span class="shrink-0 font-mono font-normal normal-case tracking-normal">{{ $openCount }}</span>
                                </button>
                                <ul class="space-y-2" x-show="openExpanded" x-cloak>
                                    @foreach ($openSessions as $index => $session)
                                        <li @if ($index >= 5) x-show="showAllOpen" x-cloak @endif>
                                            <a
                                                href="{{ \App\Helpers\InboxNav::url($filter_tab, $session['conversation_id'], $searchQuery, $session['id'], $selected_group['id'] ?? null) }}"
                                                wire:navigate
                                                class="block rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-300 dark:border-white/10 dark:bg-gray-800 dark:hover:border-brand-500/40"
                                            >
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <span class="truncate text-sm {{ $session['unread'] ? 'font-semibold text-gray-900 dark:text-white' : 'font-medium text-gray-800 dark:text-gray-200' }}">{{ $session['title'] }}</span>
                                                    <span class="flex shrink-0 items-center gap-1">
                                                        @if ($session['has_error'] ?? false)
                                                            <span class="rounded-full bg-red-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-red-700 dark:bg-red-950/40 dark:text-red-400">Failed</span>
                                                        @elseif ($session['collecting'] ?? false)
                                                            <span class="rounded-full bg-sky-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-sky-700 dark:bg-sky-950/40 dark:text-sky-400">AI</span>
                                                        @elseif ($session['needs_review'] ?? false)
                                                            <span class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">Triage</span>
                                                        @elseif ($session['awaiting_review'] ?? false)
                                                            <span class="rounded-full bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">Review</span>
                                                        @endif
                                                        <span class="font-mono text-[11px] text-gray-500">{{ $session['time'] }}</span>
                                                    </span>
                                                </div>
                                                <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $session['preview'] }}</p>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($openCount > 5)
                                    <button
                                        type="button"
                                        x-show="openExpanded && !showAllOpen"
                                        x-cloak
                                        @click="showAllOpen = true"
                                        class="mt-2 w-full rounded-lg border border-dashed border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 hover:border-brand-300 hover:text-brand-600 dark:border-white/15 dark:text-gray-400 dark:hover:border-brand-500/40 dark:hover:text-brand-400"
                                    >
                                        Show {{ $openCount - 5 }} more
                                    </button>
                                    <button
                                        type="button"
                                        x-show="openExpanded && showAllOpen"
                                        x-cloak
                                        @click="showAllOpen = false"
                                        class="mt-2 w-full rounded-lg px-3 py-2 text-xs font-medium text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                    >
                                        Show less
                                    </button>
                                @endif
                            </div>
                        @endif
                        @if ($doneSessions !== [])
                            <div>
                                <button
                                    type="button"
                                    @click="doneExpanded = !doneExpanded"
                                    class="mb-2 flex w-full items-center gap-1.5 rounded-lg px-1 py-1 text-left text-[10px] font-bold uppercase tracking-wider text-gray-500 hover:bg-white/60 hover:text-gray-700 dark:hover:bg-white/5 dark:hover:text-gray-300"
                                    :aria-expanded="doneExpanded ? 'true' : 'false'"
                                >
                                    <x-heroicon-m-chevron-right class="h-3.5 w-3.5 shrink-0 transition-transform" x-bind:class="doneExpanded && 'rotate-90'" />
                                    <span class="min-w-0 flex-1">Done</span>
                                    <span class="shrink-0 font-mono font-normal normal-case tracking-normal">{{ $doneCount }}</span>
                                </button>
                                <ul class="space-y-2" x-show="doneExpanded" x-cloak>
                                    @foreach ($doneSessions as $session)
                                        <li>
                                            <a
                                                href="{{ \App\Helpers\InboxNav::url($filter_tab, $session['conversation_id'], $searchQuery, $session['id'], $selected_group['id'] ?? null) }}"
                                                wire:navigate
                                                class="block rounded-xl border border-gray-200 bg-white p-3 transition hover:border-brand-300 dark:border-white/10 dark:bg-gray-800 dark:hover:border-brand-500/40"
                                            >
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <span class="truncate text-sm font-medium text-gray-700 dark:text-gray-300">{{ $session['title'] }}</span>
                                                    <span class="flex shrink-0 items-center gap-1">
                                                        <span class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">Done</span>
                                                        <span class="font-mono text-[11px] text-gray-500">{{ $session['time'] }}</span>
                                                    </span>
                                                </div>
                                                <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $session['preview'] }}</p>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($openSessions === [] && $doneSessions === [])
                            <p class="py-12 text-center text-sm text-gray-500">No sessions for this customer in this filter.</p>
                        @endif
                    </div>
                </div>
            @elseif(($session_groups ?? []) !== [])
                <div class="flex-1 flex flex-col items-center justify-center px-6 py-16 text-center">
                    <x-heroicon-o-user-group class="h-10 w-10 text-gray-300 dark:text-gray-600"/>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">Select a customer</h3>
                    <p class="mt-1 max-w-sm text-sm text-gray-500">Pick someone from the inbox list to see their sessions, then open a chat.</p>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center px-6 py-16 text-center">
                    <x-heroicon-o-chat-bubble-oval-left-ellipsis class="h-10 w-10 text-gray-300 dark:text-gray-600"/>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                        @if ($searchQuery !== '')
                            No matching sessions
                        @elseif ($filter_tab === 'unread')
                            Nothing unread
                        @elseif ($filter_tab === 'review')
                            Nothing waiting for approval
                        @elseif ($filter_tab === 'done')
                            No done sessions
                        @else
                            No sessions yet
                        @endif
                    </h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if ($searchQuery !== '')
                            Try another search, or start a new thread.
                        @elseif ($filter_tab === 'unread')
                            Open All to see every session, or start a new one.
                        @elseif ($filter_tab === 'review')
                            Sessions show here when an employee sends the work to Review.
                        @elseif ($filter_tab === 'done')
                            Sessions show here when every linked task is done.
                        @else
                            Start a thread with a customer.
                        @endif
                    </p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        @if ($filter_tab !== 'all' || $searchQuery !== '')
                            <a href="{{ \App\Helpers\InboxNav::url('all') }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">View all</a>
                        @endif
                        <button type="button" wire:click="openStartModal" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            <x-heroicon-m-plus class="h-4 w-4"/> Start Conversation
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Sidebar -->
        <div class="lg:col-span-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-white/10 p-4 space-y-4 overflow-y-auto shadow-sm">
            @if($selected_session)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 pb-2.5 dark:border-white/10">
                    <div class="min-w-0">
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Customer Profile</h4>
                        <p class="mt-0.5 truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $selected_conversation?->customer?->name }}</p>
                        <p class="truncate font-mono text-[11px] text-gray-500 dark:text-gray-400">{{ $selected_conversation?->customer?->phone }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $selected_conversation?->channel }}</span>
                </div>

                <div class="rounded-xl border border-brand-100 bg-brand-50/50 p-4 text-xs space-y-3 shadow-sm dark:border-brand-900/40 dark:bg-brand-950/20">
                    <div class="flex items-center justify-between">
                        <strong class="flex items-center gap-1.5 text-xs font-bold text-brand-800 dark:text-brand-300">
                            <x-heroicon-s-sparkles class="w-4 h-4 text-brand-500"/> AI Thread Copilot
                        </strong>
                        @if($copilot['state'] === 'ready' && $copilot['urgency'])
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 border border-rose-200 dark:border-rose-800/40">
                                {{ $copilot['urgency'] }}
                            </span>
                        @elseif(! empty($copilot['done']))
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Done</span>
                        @elseif($copilot['state'] === 'pending')
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Pending</span>
                        @elseif($copilot['state'] === 'needs_review')
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">Needs review</span>
                        @elseif($copilot['state'] === 'awaiting_verification')
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300">Awaiting code</span>
                        @elseif($copilot['state'] === 'failed')
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300">Failed</span>
                        @elseif($copilot['state'] === 'skipped')
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Skipped</span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">None</span>
                        @endif
                    </div>

                    @if($copilot['state'] === 'pending')
                        <div
                            x-data="{
                                endsAt: @js($copilotCooldownEndsAt),
                                remaining: 0,
                                interval: null,
                                init() {
                                    this.tick();
                                    this.interval = setInterval(() => this.tick(), 1000);
                                },
                                tick() {
                                    if (!this.endsAt) { this.remaining = 0; return; }
                                    const diff = Math.max(0, Math.floor((new Date(this.endsAt).getTime() - Date.now()) / 1000));
                                    this.remaining = diff;
                                    if (diff <= 0 && this.interval) { clearInterval(this.interval); }
                                },
                                get minutes() { return Math.floor(this.remaining / 60); },
                                get seconds() { return this.remaining % 60; },
                                get display() {
                                    return this.minutes.toString().padStart(2, '0') + ':' + this.seconds.toString().padStart(2, '0');
                                },
                                destroy() { if (this.interval) clearInterval(this.interval); }
                            }"
                            class="bg-white/70 dark:bg-gray-900/60 p-3 rounded-lg border border-brand-100 dark:border-brand-900/40 space-y-2"
                        >
                            <template x-if="endsAt && remaining > 0">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[11px] text-gray-700 dark:text-gray-300">Cooldown timer — waiting for more messages…</p>
                                        <span class="font-mono text-sm font-bold text-brand-600 dark:text-brand-400 tabular-nums" x-text="display"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1 overflow-hidden">
                                        <div class="bg-brand-500 h-1 rounded-full transition-all duration-1000" :style="'width:' + Math.max(0, 100 - (remaining / {{ $cooldownTotalSeconds }}) * 100) + '%'"></div>
                                    </div>
                                    <button
                                        type="button"
                                        wire:click="bypassCooldown"
                                        wire:loading.attr="disabled"
                                        class="w-full text-center px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white font-semibold text-[11px] rounded-lg transition flex items-center justify-center gap-1"
                                    >
                                        <x-heroicon-s-forward class="w-3.5 h-3.5"/> Skip cooldown — Analyze now
                                    </button>
                                </div>
                            </template>
                            <template x-if="!endsAt || remaining <= 0">
                                <div class="flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4 text-brand-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <p class="text-[11px] text-gray-700 dark:text-gray-300">AI analysis is running…</p>
                                </div>
                            </template>
                        </div>
                    @elseif($copilot['state'] === 'skipped')
                        <p class="text-[11px] text-gray-700 dark:text-gray-300 leading-relaxed bg-white/70 dark:bg-gray-900/60 p-2.5 rounded-lg border border-brand-100 dark:border-brand-900/40">
                            {{ $copilot['summary'] ?? 'AI analysis was skipped for this sender.' }}
                        </p>
                    @elseif($copilot['state'] === 'failed')
                        <p class="text-[11px] text-red-700 dark:text-red-300 leading-relaxed bg-red-50/70 dark:bg-red-950/30 p-2.5 rounded-lg border border-red-200 dark:border-red-900/40">
                            AI analysis failed. Review the thread manually or re-run.
                        </p>
                        @if($selected_session?->metadata['error'] ?? null)
                            <p class="text-[10px] text-red-600/80 dark:text-red-400/80 font-mono bg-red-50/50 dark:bg-red-950/20 p-2 rounded-lg border border-red-100 dark:border-red-900/30 break-all">
                                {{ \Illuminate\Support\Str::limit($selected_session->metadata['error'], 200) }}
                            </p>
                        @endif
                    @elseif(in_array($copilot['state'], ['ready', 'needs_review'], true))
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">This session only — not the full customer history.</p>
                        <div class="space-y-1">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400 block">AI Summary</span>
                                @if($copilot['request_log_id'])
                                    <a href="{{ route('ai.request-logs.view', $copilot['request_log_id']) }}" class="text-[10px] font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 whitespace-nowrap">
                                        View full log →
                                    </a>
                                @else
                                    <a href="{{ route('ai.request-logs.index') }}" class="text-[10px] font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 whitespace-nowrap">
                                        AI logs →
                                    </a>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-800 dark:text-gray-200 leading-relaxed bg-white/70 dark:bg-gray-900/60 p-2.5 rounded-lg border border-brand-100 dark:border-brand-900/40">
                                {{ $copilot['summary'] ?? 'Analysis complete.' }}
                            </p>
                        </div>
                        @if($session_tasks->isNotEmpty())
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400 block">Tasks from this session</span>
                                @foreach($session_tasks as $st)
                                    @php
                                        $statusBadge = match ($st->statusKey()) {
                                            'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                                            'code_review' => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                            'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                                        };
                                    @endphp
                                    <div class="p-2.5 bg-white/70 dark:bg-gray-900/60 rounded-lg border border-brand-100 dark:border-brand-900/40">
                                        <a href="{{ route('task-detail', $st->id) }}" class="block">
                                            <span class="flex items-start justify-between gap-2">
                                                <strong class="text-xs text-gray-900 dark:text-white block font-bold">{{ $st->title }}</strong>
                                                <span class="shrink-0 rounded-full border px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide {{ $statusBadge }}">{{ $st->status->label() }}</span>
                                            </span>
                                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $st->project?->name ?? 'No project' }}</p>
                                        </a>
                                        @if($isManager && $st->statusKey() === 'code_review')
                                            <div class="mt-2 flex items-center gap-1">
                                                <button type="button" wire:click="approveSessionTask({{ $st->id }})" class="px-2 py-1 rounded-lg bg-emerald-600 text-[10px] font-bold text-white hover:bg-emerald-700">Approve &amp; done</button>
                                                <button type="button" wire:click="openSessionReviewModal({{ $st->id }})" class="px-2 py-1 rounded-lg bg-red-600 text-[10px] font-bold text-white hover:bg-red-700">Changes</button>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @elseif($copilot['title'])
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-500 dark:text-gray-400 block">Recommended Task</span>
                                <div class="p-2.5 bg-white/70 dark:bg-gray-900/60 rounded-lg border border-brand-100 dark:border-brand-900/40 space-y-1">
                                    <strong class="text-xs text-gray-900 dark:text-white block font-bold">{{ $copilot['title'] }}</strong>
                                    @if($copilot['project'] || $copilot['employee'])
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400">
                                            @if($copilot['project']) Project: {{ $copilot['project'] }} @endif
                                            @if($copilot['project'] && $copilot['employee']) | @endif
                                            @if($copilot['employee']) Assignee: {{ $copilot['employee'] }} @endif
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if($copilot['provider'] || $copilot['model'])
                            <p class="text-[11px] font-mono text-gray-500">{{ $copilot['provider'] }}{{ $copilot['model'] ? ' · '.$copilot['model'] : '' }}</p>
                        @endif
                    @else
                        <p class="text-[11px] text-gray-600 dark:text-gray-400 leading-relaxed bg-white/70 dark:bg-gray-900/60 p-2.5 rounded-lg border border-brand-100 dark:border-brand-900/40">
                            No AI analysis yet. Inbound messages are analyzed by the queue after they arrive, or you can re-run analysis.
                        </p>
                    @endif

                    <div class="pt-2 flex flex-col gap-2">
                        @if(! $copilot['auto_created'])
                            <button wire:click="openApproveModal" class="w-full bg-brand-500 hover:bg-brand-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center justify-center gap-1 shadow-sm">
                                <x-heroicon-s-check-circle class="w-4 h-4 shrink-0"/> Create task from this session
                            </button>
                        @endif
                        <button wire:click="reanalyzeWithAi" wire:loading.attr="disabled" class="w-full text-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold text-[11px] rounded-lg transition flex items-center justify-center gap-1">
                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-brand-500"/> Re-run AI Analysis
                        </button>
                        @if($copilot['request_log_id'])
                            <a href="{{ route('ai.request-logs.view', $copilot['request_log_id']) }}" class="flex w-full items-center justify-center gap-1 rounded-lg border border-gray-200 px-3 py-1.5 text-center text-[11px] font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                                <x-heroicon-o-document-text class="w-3.5 h-3.5"/> Open AI request log
                            </a>
                        @elseif(in_array($copilot['state'], ['ready', 'needs_review', 'failed', 'pending'], true))
                            <a href="{{ route('ai.request-logs.index') }}" class="w-full text-center px-3 py-1.5 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 font-semibold text-[11px] rounded-lg transition hover:bg-gray-50 dark:hover:bg-white/5 flex items-center justify-center gap-1">
                                <x-heroicon-o-document-text class="w-3.5 h-3.5"/> Browse AI request logs
                            </a>
                        @endif
                    </div>
                </div>

                @if($selected_conversation?->issues?->isNotEmpty())
                    <div>
                        <h4 class="font-bold text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/10 pb-2 mb-2">Linked Issues</h4>
                        <div class="space-y-2">
                            @foreach($selected_conversation->issues as $issue)
                                <div class="p-2.5 rounded-lg border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                    <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ $issue->title }}</p>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $issue->status?->value ?? $issue->status }} · {{ $issue->priority?->value ?? $issue->priority }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <h4 class="font-bold text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/10 pb-2 mb-2">Projects ({{ $customer_projects->count() }})</h4>
                    @if($customer_projects->isEmpty())
                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">This customer has no projects yet.</p>
                    @else
                        <div class="space-y-1.5">
                            @foreach($customer_projects as $project)
                                <p class="text-xs text-gray-800 dark:text-gray-200">{{ $project->name }}</p>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div>
                    <h4 class="font-bold text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-white/10 pb-2 mb-2">Customer Tasks</h4>
                    @if($session_tasks->isEmpty())
                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">No tasks from this session.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($session_tasks as $t)
                                @php
                                    $statusBadge = match ($t->statusKey()) {
                                        'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                                        'code_review' => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                        'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                                    };
                                @endphp
                                <div class="p-2.5 rounded-lg border border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                    <a href="{{ route('task-detail', $t->id) }}" class="block hover:border-brand-400">
                                        <span class="flex items-start justify-between gap-2">
                                            <p class="text-xs font-semibold text-gray-900 dark:text-white">{{ $t->title }}</p>
                                            <span class="shrink-0 rounded-full border px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide {{ $statusBadge }}">{{ $t->status->label() }}</span>
                                        </span>
                                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $t->project?->name ?? 'No project' }}</p>
                                    </a>
                                    @if($isManager && $t->statusKey() === 'code_review')
                                        <div class="mt-2 flex items-center gap-1">
                                            <button type="button" wire:click="approveSessionTask({{ $t->id }})" class="px-2 py-1 rounded-lg bg-emerald-600 text-[10px] font-bold text-white hover:bg-emerald-700">Approve &amp; done</button>
                                            <button type="button" wire:click="openSessionReviewModal({{ $t->id }})" class="px-2 py-1 rounded-lg bg-red-600 text-[10px] font-bold text-white hover:bg-red-700">Changes</button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="h-full flex flex-col items-center justify-center p-4 text-center text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-user class="w-10 h-10 mb-3 text-gray-300 dark:text-gray-600"/>
                    <p class="text-sm text-gray-500">
                    @if($selected_group ?? null)
                        Choose a session to open the chat and customer context.
                    @else
                        Select a customer, then a session, to see context here.
                    @endif
                </p>
                </div>
            @endif
        </div>
    </div>

    <x-ui.slide-form-modal :show="$showStartModal" title="Start Conversation" description="Open a new thread with an existing customer." close-method="$set('showStartModal', false)" size="sm">
        <form id="modal-start-conversation" wire:submit="startConversation" class="space-y-4">
            <x-form.select.searchable wire:model="startCustomerId" label="Customer" :options="$customers" placeholder="Select customer" empty-option="Select customer" search-placeholder="Search customers..." />
            @error('startCustomerId') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Platform</label>
                <input wire:model="startPlatform" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Phone Number</label>
                <input wire:model="startContactIdentifier" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                @error('startContactIdentifier') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showStartModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-start-conversation" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Start</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

        <x-ui.slide-form-modal
            :show="$showApproveModal"
            :title="$creatingFromSelection ? 'Create task from selected messages' : 'Create task from this session'"
            :description="$creatingFromSelection ? 'Build a task from the messages you picked. Use this when Copilot missed or misread a request.' : 'Turn this cooldown burst into a tracked task. The employee still gets the customer messages.'"
            close-method="$set('showApproveModal', false)"
            size="lg"
        >
        <form id="modal-approve-task" wire:submit="approveAndCreateTask" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Task Title</label>
                <input wire:model="approveTitle" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                @error('approveTitle') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.select.searchable wire:model="approveProjectId" wire:key="approve-projects-{{ implode('-', array_keys($projects)) }}" label="Project" :options="$projects" placeholder="Select project" empty-option="Select project" search-placeholder="Search projects..." />
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Priority</label>
                    <select wire:model="approvePriority" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <x-form.select.searchable wire:model="approveAssigneeId" label="Assign Employee" :options="$employees" placeholder="Unassigned" empty-option="Unassigned" search-placeholder="Search employees..." />
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                <textarea wire:model="approveDescription" rows="3" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showApproveModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-approve-task" class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">Create Task</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

    <x-ui.slide-form-modal :show="$showTaskReviewModal" title="Request changes" description="The assignee will see this note in My Tasks and by email." close-method="closeSessionReviewModal" size="sm">
        <form id="modal-session-review" wire:submit="submitSessionReview" class="space-y-3">
            @if($reviewingTaskTitle !== '')
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $reviewingTaskTitle }}</p>
            @endif
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">What should they change?</label>
                <textarea wire:model="reviewNote" rows="4" placeholder="Add a note the assignee will see…" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="closeSessionReviewModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-session-review" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700">Send back</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

</div>
