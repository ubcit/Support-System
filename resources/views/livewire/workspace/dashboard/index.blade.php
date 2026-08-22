<div>
    <x-common.page-breadcrumb pageTitle="Workspace" compact>
        <x-slot:subtitle>Your assigned work at a glance</x-slot:subtitle>
    </x-common.page-breadcrumb>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @foreach([
            ['waiting', 'Waiting', $stats['waiting'], 'text-gray-900 dark:text-white'],
            ['active', 'Active', $stats['active'], 'text-blue-600'],
            ['review', 'Review', $stats['review'], 'text-purple-600'],
            ['completed', 'Done', $stats['completed'], 'text-emerald-600'],
        ] as [$tab, $label, $count, $color])
            <button wire:click="setTab('{{ $tab }}')" type="button" class="bg-white dark:bg-gray-800 rounded-2xl p-4 border border-gray-200/80 dark:border-white/10 shadow-sm flex flex-col items-center cursor-pointer transition hover:shadow-md {{ $activeTab === $tab ? 'ring-2 ring-brand-500 border-brand-200 dark:border-brand-800' : '' }}">
                <span class="text-2xl font-black {{ $color }}">{{ $count }}</span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mt-0.5">{{ $label }}</span>
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-1 space-y-2.5">
            @forelse($tasks as $task)
                @php
                    $priBorder = match($task->priority?->value) {
                        'urgent' => 'border-l-red-500',
                        'high' => 'border-l-amber-500',
                        'medium' => 'border-l-blue-400',
                        default => 'border-l-gray-300 dark:border-l-gray-600',
                    };
                    $isActive = $activeTask?->id === $task->id;
                @endphp
                <div wire:click="selectTask({{ $task->id }})" class="bg-white dark:bg-gray-800 rounded-xl p-3 border-l-[3px] {{ $priBorder }} border border-gray-200/80 dark:border-white/10 shadow-sm cursor-pointer hover:shadow-md transition {{ $isActive ? 'ring-2 ring-brand-500/50 bg-brand-50/30 dark:bg-brand-950/10' : '' }}">
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <span class="text-xs font-bold uppercase text-gray-400">{{ $task->priority?->value ?? 'medium' }}</span>
                        @if($task->due_date)
                            <span class="text-[10px] font-mono {{ $task->dueDateToneClasses('text') }}">{{ $task->due_date->diffForHumans() }}</span>
                        @endif
                    </div>
                    <h3 class="font-bold text-sm leading-tight text-gray-900 dark:text-white">{{ $task->title }}</h3>
                    <p class="text-[10px] text-gray-400 mt-1">{{ $task->project?->name ?? 'General' }}</p>
                </div>
            @empty
                <div class="text-center py-12 text-gray-400">
                    <span class="text-xs font-medium">No tasks in this queue</span>
                </div>
            @endforelse
        </div>

        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-white/10 shadow-sm flex flex-col" style="height: calc(100vh - 280px);">
            @if($activeTask)
                <div class="p-5 border-b border-gray-200/80 dark:border-white/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-black text-gray-900 dark:text-white leading-tight">{{ $activeTask->title }}</h2>
                            <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                <span class="text-[10px] font-medium text-gray-500 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded-md">{{ $activeTask->project?->name ?? 'General' }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if(($activeTask->status?->value ?? 'todo') === 'todo' || ($activeTask->status?->value ?? '') === 'to_do')
                                <button type="button" wire:click="updateTaskStatus({{ $activeTask->id }}, 'in_progress')" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-amber-500 text-white hover:bg-amber-600">Start</button>
                            @elseif(($activeTask->status?->value ?? '') === 'in_progress')
                                <button type="button" wire:click="updateTaskStatus({{ $activeTask->id }}, 'review')" title="Submit for review — a manager must approve and mark Done" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-blue-500 text-white hover:bg-blue-600">Submit for Review</button>
                            @elseif(in_array($activeTask->status?->value ?? '', ['review', 'code_review']))
                                <span class="px-3 py-1.5 text-[10px] font-semibold rounded-lg bg-amber-50 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Waiting for manager review</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-5 bg-gray-50/30 dark:bg-gray-900/20">
                    @if($activeTask->issue)
                        <div>
                            <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Original Request</h4>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300">
                                {{ $activeTask->issue->description }}
                            </div>
                        </div>
                    @endif

                    <div>
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">Description</h4>
                        <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-300">
                            {{ $activeTask->description ?: 'No description.' }}
                        </div>
                    </div>

                    @if($feedback = $activeTask->latestChangesRequest())
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

                    <div>
                        <h4 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">Comments</h4>
                        @if(method_exists($activeTask, 'comments') && $activeTask->comments->isNotEmpty())
                            @foreach($activeTask->comments as $comment)
                                <div class="flex gap-2.5 mb-2">
                                    <x-ui.person-avatar :person="$comment->employee" :name="$comment->employee?->name ?? '?'" size="md" />
                                    <div>
                                        <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $comment->employee?->name ?? 'System' }}</span>
                                        <span class="text-[10px] text-gray-400 font-mono ml-1">{{ $comment->created_at->diffForHumans() }}</span>
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{!! preg_replace('/@\[([^\]]+)\]/', '<span class="rounded bg-indigo-100 px-1 text-indigo-700 dark:bg-indigo-800 dark:text-indigo-300">@$1</span>', e($comment->content)) !!}</p>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p class="text-xs text-gray-400 italic">No comments yet.</p>
                        @endif
                    </div>
                </div>

                <div class="p-3 border-t border-gray-200/80 dark:border-white/10">
                    <form wire:submit="postNote" class="flex gap-2">
                        <input type="text" wire:model="newNote" placeholder="Add a comment... Use @Name to mention" class="flex-1 text-xs rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 py-2 px-3 focus:ring-brand-500">
                        <button type="submit" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold rounded-xl transition">Post</button>
                    </form>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-gray-400">
                    <p class="text-sm font-medium">Select a task from the queue</p>
                </div>
            @endif
        </div>
    </div>
</div>
