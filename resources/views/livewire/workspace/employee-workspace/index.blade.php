<div>
    <x-common.page-breadcrumb pageTitle="My Tasks" compact>
        <x-slot:subtitle>
            {{ match ($activeTab) {
                'overdue' => 'Overdue work',
                'today' => 'Due today',
                'completed' => 'Completed',
                default => 'Assigned to you',
            } }}
        </x-slot:subtitle>
    </x-common.page-breadcrumb>

    <div class="flex flex-col bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-white/10 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden" style="height: calc(100vh - 180px);">
        @if($selected_task)
            <div class="p-5 border-b border-gray-200/80 dark:border-white/10">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">{{ $selected_task->title }}</h2>
                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                            <span class="text-[10px] font-medium text-gray-500 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded-md">{{ $selected_task->project?->name ?? 'General' }}</span>
                            @php
                                $statusBadge = match($selected_task->statusKey()) {
                                    'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
                                    'code_review' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300',
                                    'done' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                };
                            @endphp
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $statusBadge }}">{{ $selected_task->status->label() }}</span>
                            <span class="text-[10px] font-medium uppercase text-gray-400">{{ $selected_task->priority?->value ?? 'medium' }}</span>
                            @if($selected_task->due_date)
                                <span class="text-[10px] font-mono {{ $selected_task->dueDateToneClasses('text') }}">
                                    Due {{ $selected_task->due_date->format('M d, Y') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <a href="{{ \App\Helpers\TaskNav::detailUrl($selected_task->id, []) }}"
                           class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:border-brand-400 hover:text-brand-600 dark:hover:text-brand-400 transition"
                           title="Open full task workspace">
                            Open full task
                        </a>
                        @if($selected_task->statusKey() === 'to_do')
                            <button type="button" wire:click="updateStatus({{ $selected_task->id }}, 'in_progress')" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-white hover:bg-amber-600">
                                Start</button>
                        @elseif($selected_task->statusKey() === 'in_progress')
                            <button type="button" wire:click="updateStatus({{ $selected_task->id }}, 'code_review')" title="Submit for review — a manager must approve and mark Done" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-blue-500 text-white hover:bg-blue-600">
                                Submit for Review</button>
                        @elseif($selected_task->statusKey() === 'code_review')
                            <span class="px-3 py-1.5 text-[10px] font-semibold rounded-lg bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                Waiting for manager review
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-1 mt-4">
                    @php
                        $steps = [
                            ['to_do', 'To Do', 'gray'],
                            ['in_progress', 'In Progress', 'blue'],
                            ['code_review', 'Review', 'purple'],
                            ['done', 'Done', 'emerald'],
                        ];
                        $currentStatusValue = $selected_task->statusKey();
                        $currentIdx = collect($steps)->search(fn ($s) => $s[0] === $currentStatusValue);
                        if ($currentIdx === false) $currentIdx = 0;
                    @endphp
                    @foreach($steps as $idx => [$stepVal, $stepLabel, $stepColor])
                        @php $isComplete = $idx <= $currentIdx; @endphp
                        <div class="flex items-center gap-1 {{ $idx > 0 ? 'flex-1' : '' }}">
                            @if($idx > 0)
                                <div class="flex-1 h-0.5 {{ $isComplete ? 'bg-' . $stepColor . '-500' : 'bg-gray-200 dark:bg-gray-700' }} rounded-full"></div>
                            @endif
                            <span class="w-5 h-5 rounded-full flex items-center justify-center text-[8px] font-bold shrink-0 {{ $isComplete ? 'bg-' . $stepColor . '-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-400' }}">
                                @if($isComplete && $idx < $currentIdx) ✓ @else {{ $idx + 1 }} @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex-1 overflow-y-auto">
                <div class="p-5 space-y-5">
                    @if($selected_task->description)
                        <div>
                            <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Description</h4>
                            <div class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 rounded-xl p-3 border border-gray-100 dark:border-white/5">
                                {{ $selected_task->description }}
                            </div>
                        </div>
                    @endif

                    @if($feedback = $selected_task->latestChangesRequest())
                        <div class="rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                            <h4 class="text-[10px] font-bold uppercase tracking-wider text-red-700 dark:text-red-300 mb-1">Changes requested</h4>
                            <p class="text-xs text-red-800 dark:text-red-300">
                                {{ $feedback->employee?->name ?? 'A reviewer' }} sent this back.
                                @if($feedback->approval_note)
                                    <span class="italic">"{{ $feedback->approval_note }}"</span>
                                @endif
                            </p>
                        </div>
                    @endif

                    @if($selected_task->checklists->isNotEmpty())
                        <div>
                            <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Checklist</h4>
                            @foreach($selected_task->checklists as $checklist)
                                <div class="space-y-1">
                                    @foreach($checklist->items as $item)
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="w-4 h-4 rounded border flex items-center justify-center shrink-0 {{ $item->is_completed ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 dark:border-gray-600' }}">
                                                @if($item->is_completed) <x-heroicon-m-check class="w-3 h-3"/> @endif
                                            </span>
                                            <span class="{{ $item->is_completed ? 'line-through text-gray-400' : 'text-gray-700 dark:text-gray-300' }}">{{ $item->title }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">Comments</h4>
                        <div class="space-y-2.5">
                            @forelse($selected_task->comments ?? collect() as $comment)
                                <div class="flex gap-2.5">
                                    <x-ui.person-avatar :person="$comment->employee" :name="$comment->employee?->name ?? '?'" size="md" />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $comment->employee?->name ?? 'System' }}</span>
                                            <span class="text-[10px] text-gray-400 font-mono">{{ $comment->created_at->diffForHumans() }}</span>
                                        </div>
                                        <p class="text-xs text-gray-700 dark:text-gray-300 mt-0.5">{!! preg_replace('/@\[([^\]]+)\]/', '<span class="rounded bg-indigo-100 px-1 text-indigo-700 dark:bg-indigo-800 dark:text-indigo-300">@$1</span>', e($comment->content)) !!}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic">No comments yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div>
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">Activity</h4>
                        <div class="space-y-1.5">
                            @forelse(($selected_task->activityLogs ?? collect())->take(10) as $log)
                                <div class="flex items-start gap-2 text-[10px]">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-gray-600 mt-1.5 shrink-0"></span>
                                    <div>
                                        <span class="font-medium text-gray-600 dark:text-gray-400">{{ $log->employee?->name ?? 'System' }}</span>
                                        <span class="text-gray-400"> {{ $log->summary() }}</span>
                                        <span class="text-gray-300 dark:text-gray-600 font-mono ml-1">{{ $log->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-[10px] text-gray-400 italic">No activity recorded.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-3 border-t border-gray-200/80 dark:border-white/10 bg-gray-50/50 dark:bg-gray-800/30">
                <form wire:submit="addComment" class="flex gap-2">
                    <input type="text" wire:model="newComment" placeholder="Write a comment... Use @Name to mention" class="flex-1 text-xs rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 py-2 px-3 focus:ring-brand-500 focus:border-brand-500">
                    <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-500 text-white text-xs font-bold rounded-xl transition shadow-sm">Post</button>
                </form>
            </div>
        @else
            <div class="flex-1 flex flex-col items-center justify-center text-gray-400 px-6 text-center">
                <x-heroicon-o-inbox class="w-16 h-16 mb-3 opacity-20"/>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Select a task</p>
                <p class="text-xs mt-1">Use Queue, Due today, Overdue, or Done in the sidebar.</p>
            </div>
        @endif
    </div>
</div>
