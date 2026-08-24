<div wire:poll.5s="refreshFromServer">
    @if(!$task)
        <div class="p-8 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-800">
            <x-heroicon-o-exclamation-triangle class="w-12 h-12 text-amber-500 mx-auto mb-3"/>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Task Not Found</h3>
            <p class="text-sm text-gray-500 mt-1 mb-4">The task you requested may have been deleted or moved.</p>
            <a href="{{ \App\Helpers\TaskNav::dashboardUrl() }}" class="px-4 py-2 bg-brand-500 text-white rounded-xl font-bold text-xs hover:bg-brand-600 transition inline-flex items-center gap-2">
                <x-heroicon-m-arrow-left class="w-4 h-4"/> Back to Workspace
            </a>
        </div>
    @else
        <div class="space-y-6">

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- CLICKUP TOP BREADCRUMB & HEADER BAR                             --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs">
                <div class="flex items-center gap-3">
                    <a href="{{ \App\Helpers\TaskNav::dashboardUrl() }}" class="p-2 rounded-xl text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition" title="Back to My Tasks">
                        <x-heroicon-m-arrow-left class="w-5 h-5"/>
                    </a>

                    <div class="h-5 w-px bg-gray-200 dark:bg-gray-700"></div>

                    <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <span class="px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-mono">
                            #{{ $task->id }}
                        </span>
                        <x-heroicon-m-chevron-right class="w-3.5 h-3.5 opacity-50"/>
                        <span class="text-gray-900 dark:text-white font-bold">
                            {{ $task->project?->name ?? 'General Workspace' }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    {{-- Status Indicator --}}
                    @php
                        $statusBadge = match ($task->status->value) {
                            'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                            'review' => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                            'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                        };
                    @endphp
                    <span class="px-3 py-1 rounded-xl text-xs font-bold border flex items-center gap-1.5 shadow-xs {{ $statusBadge }}">
                        <span class="w-2 h-2 rounded-full bg-current"></span>
                        {{ $task->status->label() }}
                    </span>

                    {{-- Delete Task --}}
                    @can('delete', $task)
                        <x-ui.confirm-button
                            heading="Move to Trash?"
                            message="You can restore this task from Trash within 30 days."
                            confirm-label="Move to Trash"
                            method="deleteTask"
                            variant="danger"
                            size="icon"
                            title="Move to Trash"
                            aria-label="Move to Trash"
                        >
                            <x-heroicon-o-trash class="h-4 w-4"/>
                        </x-ui.confirm-button>
                    @endcan
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- 2-COLUMN CLICKUP WORKSPACE LAYOUT (70% Left / 30% Right)        --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                {{-- ───────────────────────────────────────────────────────────── --}}
                {{-- LEFT COLUMN: Title, Notes, Attachments, Checklists, Comments  --}}
                {{-- ───────────────────────────────────────────────────────────── --}}
                <div class="lg:col-span-8 space-y-6">

                    {{-- Task Title & Inline Editor --}}
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
                        <div class="flex items-start justify-between gap-4">
                            <input type="text"
                                   wire:model.lazy="taskTitle"
                                   wire:change="updateTaskTitle"
                                   class="w-full text-xl lg:text-2xl font-black text-gray-900 dark:text-white bg-transparent border-0 focus:ring-2 focus:ring-brand-500 rounded-xl p-2 -ml-1 transition"
                                   placeholder="Task title...">
                        </div>

                        {{-- Rich Description & Notes Area --}}
                        <div class="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                                    <x-heroicon-o-document-text class="w-4 h-4"/> Description & Notes
                                </label>
                                <span wire:loading.delay wire:target="updateTaskDescription" class="text-xs text-gray-400">Saving...</span>
                                <span wire:loading.remove wire:target="updateTaskDescription" class="text-xs text-gray-400">Auto-saved</span>
                            </div>
                            <textarea wire:model.live.debounce.1500ms="description"
                                      rows="5"
                                      placeholder="Add detailed task notes, instructions, background, or links..."
                                      class="w-full text-xs lg:text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3.5 text-gray-900 dark:text-gray-100 focus:ring-brand-500 focus:bg-white dark:focus:bg-gray-900 transition leading-relaxed"></textarea>
                        </div>

                        @if($messageSource && ($messageSource['customer_request'] || $messageSource['conversation_id']))
                            <div class="mt-4 rounded-xl border border-sky-200 dark:border-sky-800 bg-sky-50/80 dark:bg-sky-950/30 p-4 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300 flex items-center gap-1.5">
                                        <x-heroicon-o-chat-bubble-left-right class="w-4 h-4"/>
                                        Original WhatsApp request
                                        @if($messageSource['source'])
                                            <span class="normal-case font-medium px-1.5 py-0.5 rounded bg-sky-100 dark:bg-sky-900 text-[10px]">{{ $messageSource['source'] }}</span>
                                        @endif
                                    </h4>
                                    @if($messageSource['can_open_conversation'])
                                        <a href="{{ $messageSource['conversation_url'] }}" class="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline">
                                            View source conversation
                                        </a>
                                    @elseif($messageSource['conversation_session_id'])
                                        <span class="text-[11px] text-gray-500">Session #{{ $messageSource['conversation_session_id'] }} · ask a manager for the full thread</span>
                                    @endif
                                </div>
                                @if($messageSource['customer_request'])
                                    <p class="text-xs lg:text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">{{ $messageSource['customer_request'] }}</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- ═════════════════════════════════════════════════════════ --}}
                    {{-- ATTACHMENTS & MEDIA HUB (Voice Notes, Files, AI Transcript) --}}
                    {{-- ═════════════════════════════════════════════════════════ --}}
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                                <x-heroicon-o-paper-clip class="w-4 h-4"/> Attachments & Voice Notes ({{ $task->attachments->count() }})
                            </h3>

                            {{-- File Upload Trigger --}}
                            <div x-data="{ uploading: false }" class="relative">
                                <label class="cursor-pointer px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-brand-600 dark:text-brand-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-gray-200 dark:border-gray-700">
                                    <x-heroicon-m-plus class="w-4 h-4"/> Upload File / Audio
                                    <input type="file" wire:model="newAttachmentFile" wire:change="uploadAttachment" class="hidden">
                                </label>
                            </div>
                        </div>

                        {{-- Upload Progress Notification --}}
                        <div wire:loading wire:target="newAttachmentFile" class="text-xs font-medium text-brand-600 animate-pulse flex items-center gap-2">
                            <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin"/> Uploading attachment...
                        </div>

                        {{-- Attachments Grid --}}
                        @if($task->attachments->count() > 0)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($task->attachments as $att)
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-col gap-2">
                                        <div class="flex items-center gap-2.5">
                                            @if(str_contains($att->mime_type ?? '', 'audio'))
                                                <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-microphone class="w-4 h-4"/>
                                                </div>
                                            @elseif(str_contains($att->mime_type ?? '', 'image'))
                                                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-950 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-photo class="w-4 h-4"/>
                                                </div>
                                            @else
                                                <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                                    <x-heroicon-o-document class="w-4 h-4"/>
                                                </div>
                                            @endif

                                            <div class="flex-1 min-w-0">
                                                <span class="text-xs font-semibold text-gray-900 dark:text-white truncate block">
                                                    {{ $att->file_name ?? 'Attachment' }}
                                                </span>
                                                <span class="text-[10px] text-gray-400 font-mono">
                                                    {{ number_format(($att->file_size ?? 0) / 1024, 1) }} KB
                                                </span>
                                            </div>

                                            <a href="{{ route('attachments.download', ['uuid' => $att->uuid]) }}" target="_blank" class="p-1.5 text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 transition" title="Open File">
                                                <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4"/>
                                            </a>
                                        </div>

                                        {{-- Inline media preview (image/video) --}}
                                        @if(str_contains($att->mime_type ?? '', 'image'))
                                            <a href="{{ route('attachments.media', ['uuid' => $att->uuid]) }}" target="_blank" class="block">
                                                <img
                                                    src="{{ route('attachments.media', ['uuid' => $att->uuid]) }}"
                                                    alt="{{ $att->file_name ?? 'Image' }}"
                                                    loading="lazy"
                                                    class="w-full h-36 object-cover rounded-lg mt-1 bg-gray-100 dark:bg-gray-900"
                                                >
                                            </a>
                                        @elseif(str_contains($att->mime_type ?? '', 'video'))
                                            <a href="{{ route('attachments.media', ['uuid' => $att->uuid]) }}" target="_blank" class="block">
                                                <video
                                                    src="{{ route('attachments.media', ['uuid' => $att->uuid]) }}"
                                                    class="w-full h-36 object-cover rounded-lg mt-1 bg-black"
                                                    controls
                                                    preload="metadata"
                                                    muted
                                                    playsinline
                                                    controlslist="nodownload"
                                                ></video>
                                            </a>
                                        @endif

                                        {{-- Audio Player & AI Transcript --}}
                                        @if(str_contains($att->mime_type ?? '', 'audio'))
                                            <audio controls class="w-full h-8 mt-1 rounded-lg">
                                                <source src="{{ route('attachments.media', ['uuid' => $att->uuid]) }}" type="{{ $att->mime_type }}">
                                                Your browser does not support audio playback.
                                            </audio>
                                            @if($att->ai_transcript)
                                                <div class="p-2.5 bg-purple-50 dark:bg-purple-950 rounded-lg border border-purple-200 dark:border-purple-800 text-xs">
                                                    <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 dark:text-purple-400 block mb-0.5">AI Transcript:</span>
                                                    <p class="text-gray-700 dark:text-gray-300 italic text-[11px] leading-relaxed">{{ $att->ai_transcript }}</p>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-6 text-center text-xs text-gray-400 border border-dashed border-gray-300 dark:border-gray-700 rounded-xl italic">
                                No attachments uploaded yet. Drag & drop files or audio recordings above.
                            </div>
                        @endif
                    </div>

                    {{-- ═════════════════════════════════════════════════════════ --}}
                    {{-- CHECKLISTS & SUBTASKS TREE                                --}}
                    {{-- ═════════════════════════════════════════════════════════ --}}
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-6">

                        {{-- Subtasks Section --}}
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                                <x-heroicon-o-queue-list class="w-4 h-4"/> Subtasks ({{ $task->subtasks->count() }})
                            </h3>

                            @if($task->subtasks->count() > 0)
                                <div class="space-y-1.5 divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($task->subtasks as $sub)
                                        <div class="pt-1.5 flex items-center justify-between text-xs">
                                            <a href="{{ \App\Helpers\TaskNav::detailUrl($sub->id) }}" class="font-semibold text-gray-800 dark:text-gray-200 hover:text-brand-600 dark:hover:text-brand-400 flex items-center gap-2">
                                                <x-heroicon-m-document-text class="w-3.5 h-3.5 text-gray-400"/>
                                                {{ $sub->title }}
                                            </a>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                                {{ $sub->status->label() }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Inline Quick Subtask Creation --}}
                            <div class="flex gap-2 pt-1">
                                <input type="text"
                                       wire:model.defer="newSubtaskTitle"
                                       wire:keydown.enter="addSubtask"
                                       placeholder="+ Add subtask title and press Enter..."
                                       class="flex-1 text-xs bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 rounded-xl py-1.5 px-3 focus:ring-brand-500">
                                <x-ui.button size="xs" wire:click="addSubtask">Add</x-ui.button>
                            </div>
                        </div>

                        <div class="h-px bg-gray-100 dark:bg-gray-800"></div>

                        {{-- Checklists Section --}}
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                                    <x-heroicon-o-check-circle class="w-4 h-4"/> Checklists
                                </h3>
                                @php
                                    $progress = $task->calculateProgress();
                                @endphp
                                <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ $progress }}% Complete
                                </span>
                            </div>

                            {{-- Overall Progress Bar --}}
                            <div class="w-full h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 transition-all duration-300" style="width: {{ $progress }}%"></div>
                            </div>

                            @foreach($task->checklists as $chk)
                                <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                                        {{ $chk->title }}
                                    </h4>

                                    <div class="space-y-2">
                                        @foreach($chk->items as $item)
                                            <label class="flex items-center gap-2.5 text-xs cursor-pointer group">
                                                <input type="checkbox"
                                                       wire:click="toggleChecklistItem({{ $item->id }})"
                                                       {{ $item->is_completed ? 'checked' : '' }}
                                                       class="w-4 h-4 text-emerald-600 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-emerald-500">
                                                <span class="font-medium transition {{ $item->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-200 group-hover:text-brand-600' }}">
                                                    {{ $item->title }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    {{-- Add Item Input --}}
                                    <div x-data="{ itemTitle: '' }" class="flex gap-2 pt-1">
                                        <input x-model="itemTitle"
                                               @keydown.enter="$wire.addChecklistItem({{ $chk->id }}, itemTitle); itemTitle=''"
                                               placeholder="+ Add item..."
                                               class="flex-1 text-xs bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 rounded-lg py-1 px-2.5 focus:ring-brand-500">
                                        <x-ui.button size="xs" @click="$wire.addChecklistItem({{ $chk->id }}, itemTitle); itemTitle=''">Add</x-ui.button>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Add New Checklist --}}
                            <div class="flex gap-2">
                                <input type="text"
                                       wire:model.defer="newChecklistName"
                                       wire:keydown.enter="addChecklist"
                                       placeholder="New checklist name..."
                                       class="flex-1 text-xs bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 rounded-xl py-1.5 px-3 focus:ring-brand-500">
                                <x-ui.button size="xs" wire:click="addChecklist">+ Add Checklist</x-ui.button>
                            </div>
                        </div>

                    </div>

                    {{-- ═════════════════════════════════════════════════════════ --}}
                    {{-- ACTIVITY LOG & COMMENTS THREAD                            --}}
                    {{-- ═════════════════════════════════════════════════════════ --}}
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                            <x-heroicon-o-chat-bubble-left-right class="w-4 h-4"/> Activity & Comments Stream
                        </h3>

                        {{-- Add Comment Box --}}
                        <div class="space-y-2">
                            <div x-data="mentionInput(@js($mentionableEmployees))" class="relative">
                                <textarea x-ref="commentInput"
                                          wire:model="newCommentText"
                                          @input="handleInput($event)"
                                          @keydown.escape="closeMentions()"
                                          @keydown.arrow-down.prevent="nextMention()"
                                          @keydown.arrow-up.prevent="prevMention()"
                                          @keydown.enter="
                                              if (showMentions && selectedMentionIndex >= 0) {
                                                  $event.preventDefault();
                                                  insertMention();
                                              } else if ($event.metaKey || $event.ctrlKey) {
                                                  $event.preventDefault();
                                                  $wire.addComment();
                                              }
                                          "
                                          rows="3"
                                          placeholder="Write a comment... Use @ to mention someone"
                                          class="w-full text-xs bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 rounded-xl p-3 text-gray-900 dark:text-white focus:ring-brand-500"></textarea>

                                <div x-show="showMentions"
                                     x-cloak
                                     class="absolute bottom-full left-0 z-50 mb-1 w-64 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
                                    <template x-for="(emp, index) in filteredEmployees" :key="emp.id">
                                        <button type="button"
                                                @click="insertMentionAt(emp)"
                                                :class="index === selectedMentionIndex ? 'bg-indigo-50 dark:bg-indigo-900/30' : ''"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-medium text-indigo-700 dark:bg-indigo-800 dark:text-indigo-300"
                                                  x-text="emp.name.charAt(0).toUpperCase()"></span>
                                            <span x-text="emp.name" class="text-gray-700 dark:text-gray-300"></span>
                                        </button>
                                    </template>

                                    <p x-show="filteredEmployees.length === 0" class="px-3 py-2 text-xs text-gray-400">
                                        No matches
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-[10px] text-gray-400">Type @ to mention. Cmd/Ctrl+Enter to post.</p>
                                <button wire:click="addComment" class="px-4 py-1.5 bg-brand-500 text-white text-xs font-bold rounded-xl hover:bg-brand-600 transition flex items-center gap-1.5">
                                    <x-heroicon-m-paper-airplane class="w-3.5 h-3.5"/> Post Comment
                                </button>
                            </div>
                        </div>

                        {{-- Comment Stream --}}
                        <div class="space-y-3 pt-2 divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($task->comments as $comment)
                                <div class="pt-3 flex gap-3">
                                    <x-ui.person-avatar :person="$comment->employee" :name="$comment->employee?->name ?? 'User'" size="lg" />
                                    <div class="flex-1 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">
                                                {{ $comment->employee?->name ?? 'System User' }}
                                            </span>
                                            <span class="text-[10px] text-gray-400 font-mono">
                                                {{ $comment->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                                            {!! preg_replace('/@\[([^\]]+)\]/', '<span class="rounded bg-indigo-100 px-1 text-indigo-700 dark:bg-indigo-800 dark:text-indigo-300">@$1</span>', e($comment->content)) !!}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="py-4 text-center text-xs text-gray-400 italic">
                                    No comments yet. Start the conversation above!
                                </div>
                            @endforelse
                        </div>
                    </div>

                </div>

                {{-- ───────────────────────────────────────────────────────────── --}}
                {{-- RIGHT COLUMN: Control Sidebar (Metadata, Assignee, Dates)     --}}
                {{-- ───────────────────────────────────────────────────────────── --}}
                <div class="lg:col-span-4 space-y-6">

                    {{-- Task Quick Controls Card --}}
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            Task Attributes
                        </h3>

                        {{-- Status Control --}}
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Status</label>
                            <div class="relative">
                                <select wire:change="moveToWorkflowState($event.target.value)" class="w-full appearance-none text-xs font-bold text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl py-2 pl-3 pr-9 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:border-brand-500">
                                    @foreach($workflowStates as $ws)
                                        @continue(! $isManager && $task->mustPassReview() && in_array($ws->type, ['completed', 'closed'], true))
                                        <option value="{{ $ws->id }}" @selected((int) $task->current_state_id === (int) $ws->id)>{{ $ws->name }}</option>
                                    @endforeach
                                </select>
                                <x-heroicon-m-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"/>
                            </div>
                        </div>

                        @if($task && $task->status === \Modules\Tasks\Enums\TaskStatus::Review)
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                                <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-300">Review Required</h4>
                                @php
                                    $approvals = $task->reviewers->where('approval_status', 'approved');
                                    $changesRequested = $task->reviewers->where('approval_status', 'changes_requested');
                                @endphp
                                @if($approvals->isNotEmpty())
                                    <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                                        Approved by: {{ $approvals->map(fn($reviewer) => $reviewer->employee?->name)->filter()->implode(', ') }}
                                    </p>
                                @endif
                                @if($changesRequested->isNotEmpty())
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">
                                        Changes requested by: {{ $changesRequested->map(fn($reviewer) => $reviewer->employee?->name)->filter()->implode(', ') }}
                                        @if($changesRequested->last()?->approval_note)
                                            <br><span class="italic">"{{ $changesRequested->last()->approval_note }}"</span>
                                        @endif
                                    </p>
                                @endif
                                @if($isManager)
                                    <div class="mt-3">
                                        <textarea wire:model="approvalNote" rows="2" placeholder="Add a note (optional)..." class="w-full rounded border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"></textarea>
                                        <div class="mt-2 flex gap-2">
                                            <button wire:click="approveTask" class="rounded bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">
                                                Approve &amp; complete
                                            </button>
                                            <button wire:click="requestChanges" class="rounded bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700">
                                                Request Changes
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">Waiting for manager review. The customer is notified after approval.</p>
                                @endif
                            </div>
                        @elseif($task && ($feedback = $task->latestChangesRequest()))
                            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                <h4 class="text-sm font-semibold text-red-800 dark:text-red-300">Changes requested</h4>
                                <p class="mt-1 text-xs text-red-700 dark:text-red-400">
                                    {{ $feedback->employee?->name ?? 'A reviewer' }} sent this back to In Progress.
                                    @if($feedback->approval_note)
                                        <br><span class="italic">"{{ $feedback->approval_note }}"</span>
                                    @endif
                                </p>
                            </div>
                        @endif

                        {{-- Priority Control --}}
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Priority</label>
                            <div class="relative">
                                <select wire:model="priority" wire:change="updatePriority($event.target.value)" class="w-full appearance-none text-xs font-bold text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl py-2 pl-3 pr-9 focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:border-brand-500">
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                                <x-heroicon-m-chevron-down class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"/>
                            </div>
                        </div>

                        {{-- Assignee Control (Multiple) --}}
                        <div x-data="{ open: false }" class="space-y-1.5 relative">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Assignees ({{ count($assignedEmployeeIds) }})</label>
                            
                            <button @click="open = !open" type="button" class="w-full text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl py-2 px-3 flex items-center justify-between">
                                <div class="flex items-center gap-1.5 overflow-hidden">
                                    @forelse($task->assignees as $ass)
                                        <x-ui.person-avatar :person="$ass" size="sm" />
                                    @empty
                                        <span class="text-gray-400 italic">Unassigned</span>
                                    @endforelse
                                </div>
                                <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                            </button>

                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 right-0 mt-1 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-2 space-y-1 max-h-48 overflow-y-auto">
                                @foreach($allEmployees as $emp)
                                    @php $isAss = in_array($emp->id, $assignedEmployeeIds); @endphp
                                    <label class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-xs">
                                        <input type="checkbox"
                                               wire:click="toggleAssignee({{ $emp->id }})"
                                               {{ $isAss ? 'checked' : '' }}
                                               class="w-4 h-4 text-brand-600 rounded border-gray-300 dark:border-gray-700 focus:ring-brand-500">
                                        <x-ui.person-avatar :person="$emp" size="sm" />
                                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $emp->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Tags --}}
                        <div x-data="{ open: false }" class="space-y-1.5 relative">
                            <label class="text-xs font-semibold text-gray-500 dark:text-gray-400">Tags ({{ count($selectedTagIds) }})</label>

                            <div class="flex flex-wrap gap-1.5 min-h-[2rem]">
                                @forelse($task->tags as $tag)
                                    <x-tasks.tag-chip :tag="$tag" size="xs" removable remove-method="removeTag({{ $tag->id }})" />
                                @empty
                                    <span class="text-xs text-gray-400 italic">No tags yet — create one below</span>
                                @endforelse
                            </div>

                            <button @click="open = !open" type="button" class="w-full text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl py-2 px-3 flex items-center justify-between">
                                <span class="text-gray-500">Search or create tags</span>
                                <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                            </button>

                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 right-0 mt-1 z-50 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl p-2 space-y-2 max-h-64 overflow-y-auto">
                                @foreach($allTags as $tag)
                                    @php $isOn = in_array($tag->id, $selectedTagIds, true); @endphp
                                    <label class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-xs">
                                        <input type="checkbox"
                                               wire:click="toggleTag({{ $tag->id }})"
                                               {{ $isOn ? 'checked' : '' }}
                                               class="w-4 h-4 text-brand-600 rounded border-gray-300 dark:border-gray-700 focus:ring-brand-500">
                                        <x-tasks.tag-chip :tag="$tag" size="xs" />
                                    </label>
                                @endforeach

                                <div class="border-t border-gray-100 dark:border-gray-700 pt-2 space-y-2">
                                    <input
                                        wire:model="newTagName"
                                        wire:keydown.enter.prevent="createAndAttachTag"
                                        type="text"
                                        maxlength="50"
                                        placeholder="Search or create tag…"
                                        class="w-full h-8 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-2 text-xs"
                                    />
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1">
                                            @foreach(['#6B7280', '#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899'] as $color)
                                                <button
                                                    type="button"
                                                    wire:click="$set('newTagColor', '{{ $color }}')"
                                                    class="h-4 w-4 rounded-full border-2 {{ $newTagColor === $color ? 'border-gray-900 dark:border-white' : 'border-transparent' }}"
                                                    style="background-color: {{ $color }};"
                                                    aria-label="Pick color"
                                                ></button>
                                            @endforeach
                                        </div>
                                        <button type="button" wire:click="createAndAttachTag" class="text-[11px] font-semibold text-brand-600 hover:underline">Add</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Project Control --}}
                        <div class="space-y-1.5">
                            <x-form.select.searchable
                                wire:model.live="projectId"
                                label="Project"
                                :options="\Modules\Projects\Models\Project::pluck('name', 'id')->toArray()"
                                placeholder="No Project (General)"
                                empty-option="No Project (General)"
                                size="sm"
                                search-placeholder="Search projects..."
                            />
                        </div>

                        {{-- Due Date Control --}}
                        <div class="space-y-1.5">
                            <x-form.date-picker
                                wire:model="dueDate"
                                label="Due Date"
                                placeholder="Pick due date"
                                size="sm"
                                allow-clear
                                x-on:date-change="$wire.updateDueDate($event.detail.dateStr)"
                            />
                        </div>

                    </div>

                    {{-- Time Tracking Card --}}
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
                                <x-heroicon-o-clock class="w-4 h-4"/> Time Tracking
                            </h3>
                            <button wire:click="updateHours" class="text-xs font-bold text-brand-600 dark:text-brand-400 hover:underline">
                                Save Hours
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-gray-500">Estimated (h)</label>
                                <input type="number" step="0.5" wire:model.defer="estimatedHours" class="w-full text-xs font-mono bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 rounded-xl py-1.5 px-2.5">
                            </div>

                            <div class="space-y-1">
                                <label class="text-[11px] font-semibold text-gray-500">Logged (h)</label>
                                <input type="number" step="0.5" wire:model.defer="actualHours" class="w-full text-xs font-mono bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 rounded-xl py-1.5 px-2.5">
                            </div>
                        </div>
                    </div>

                    {{-- Status Timeline Card --}}
                    <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 text-xs space-y-2">
                        <x-tasks.status-timeline :task="$task" />
                    </div>

                </div>

            </div>

        </div>
    @endif

@script
<script>
    Alpine.data('mentionInput', (initialEmployees = []) => ({
        showMentions: false,
        mentionQuery: '',
        selectedMentionIndex: -1,
        employees: initialEmployees ?? [],
        get filteredEmployees() {
            if (!this.mentionQuery) {
                return this.employees.slice(0, 8);
            }

            const q = this.mentionQuery.toLowerCase();
            return this.employees.filter((employee) => employee.name.toLowerCase().includes(q)).slice(0, 8);
        },
        handleInput(event) {
            const textarea = event.target;
            const text = textarea.value;
            const cursor = textarea.selectionStart;
            const textBeforeCursor = text.substring(0, cursor);
            const atMatch = textBeforeCursor.match(/@(\w*)$/);

            if (atMatch) {
                this.mentionQuery = atMatch[1];
                this.showMentions = true;
                this.selectedMentionIndex = 0;
                return;
            }

            this.closeMentions();
        },
        closeMentions() {
            this.showMentions = false;
            this.selectedMentionIndex = -1;
        },
        nextMention() {
            if (!this.showMentions || this.selectedMentionIndex >= this.filteredEmployees.length - 1) {
                return;
            }

            this.selectedMentionIndex++;
        },
        prevMention() {
            if (!this.showMentions || this.selectedMentionIndex <= 0) {
                return;
            }

            this.selectedMentionIndex--;
        },
        insertMention() {
            if (this.selectedMentionIndex < 0 || !this.filteredEmployees[this.selectedMentionIndex]) {
                return;
            }

            this.insertMentionAt(this.filteredEmployees[this.selectedMentionIndex]);
        },
        insertMentionAt(employee) {
            const textarea = this.$refs.commentInput;
            const text = textarea.value;
            const cursor = textarea.selectionStart;
            const textBeforeCursor = text.substring(0, cursor);
            const atIndex = textBeforeCursor.lastIndexOf('@');

            if (atIndex >= 0) {
                const before = text.substring(0, atIndex);
                const after = text.substring(cursor);
                const newText = `${before}@[${employee.name}] ${after}`;

                textarea.value = newText;
                this.$wire.set('newCommentText', newText);

                const newCursor = atIndex + employee.name.length + 4;
                textarea.setSelectionRange(newCursor, newCursor);
                textarea.focus();
            }

            this.closeMentions();
        },
    }));
</script>
@endscript
</div>
