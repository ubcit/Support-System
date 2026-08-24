<div>
    <x-common.page-breadcrumb pageTitle="My Tasks" compact>
        <x-slot:subtitle>{{ $filterSubtitle }}</x-slot:subtitle>
        <x-slot:actions>
            @if($canCreate)
            <button type="button" @click="$wire.showCreateModal = true; $wire.openCreateModal()" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                <x-heroicon-m-plus class="h-4 w-4"/> New Task
            </button>
            @endif
        </x-slot:actions>
    </x-common.page-breadcrumb>

    <div class="space-y-4 relative" x-data="{ quickTitle: '' }">

        {{-- TOOLBAR --}}
        <div class="space-y-3 rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03] sm:p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-[200px] flex-1 lg:max-w-sm">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400"/>
                    <input type="text"
                           wire:model.live.debounce.300ms="searchQuery"
                           placeholder="Search tasks by title..."
                           class="h-10 w-full rounded-xl border border-gray-200 bg-white py-2 pr-4 pl-10 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                </div>

                <div class="flex min-w-[140px] items-center gap-1.5 rounded-xl border border-gray-200 bg-gray-50 px-3 py-1.5 dark:border-gray-700 dark:bg-gray-800">
                    <span class="shrink-0 text-[10px] font-bold uppercase text-gray-400">Group</span>
                    <select wire:model.live="groupBy" class="cursor-pointer border-0 bg-transparent p-0 pr-6 text-xs font-bold text-gray-800 focus:ring-0 dark:text-gray-200">
                        <option value="status">Status</option>
                        <option value="due_date">Due date</option>
                        <option value="priority">Priority</option>
                        <option value="project">Project</option>
                        <option value="assignee">Assignee</option>
                    </select>
                </div>

                @if($isManager)
                <div class="flex items-center gap-1 rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-800">
                    <button wire:click="$set('filterReview', '')" class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold {{ $filterReview === '' ? 'bg-white shadow-sm text-gray-900 dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">All</button>
                    <button wire:click="$set('filterReview', 'review')" class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold {{ $filterReview === 'review' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">Review Queue</button>
                    <button wire:click="$set('filterReview', 'done_recent')" class="rounded-lg px-2.5 py-1.5 text-[11px] font-bold {{ $filterReview === 'done_recent' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }}">Recently Done</button>
                </div>
                @endif

                <div class="ml-auto rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-xs font-bold text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    {{ $tasks->count() }} tasks
                </div>
            </div>

            @if (count($activeFilters) > 0)
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach ($activeFilters as $chip)
                        <button type="button" wire:click="clearFilter('{{ $chip['key'] }}')"
                                class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-semibold text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            {{ $chip['label'] }}
                            <x-heroicon-m-x-mark class="h-3 w-3 text-gray-400"/>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- VIEWS                                                          --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}

        @if($currentView === 'list')
            {{-- ─────────── CLICKUP GROUPED LIST VIEW ─────────── --}}
            @if($groupedTasks->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                    <x-heroicon-o-check-circle class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"/>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No tasks here</h3>
                    <p class="mt-1 text-sm text-gray-500">Create a task or pick another project from the sidebar.</p>
                    <button type="button" @click="$wire.showCreateModal = true; $wire.openCreateModal()" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                        <x-heroicon-m-plus class="h-4 w-4"/> New Task
                    </button>
                </div>
            @else
            <div class="space-y-4">
                @foreach($groupedTasks as $groupKey => $group)
                    @php
                        $groupTaskIds = $group['tasks']->pluck('id')->toArray();
                        $isGroupAllSelected = count(array_intersect($groupTaskIds, $selectedTasks)) === count($groupTaskIds) && count($groupTaskIds) > 0;
                        $groupColorClass = match ($group['color'] ?? 'gray') {
                            'red' => 'bg-red-500 text-red-500',
                            'amber' => 'bg-amber-500 text-amber-500',
                            'yellow' => 'bg-yellow-500 text-yellow-500',
                            'cyan' => 'bg-cyan-500 text-cyan-500',
                            'blue' => 'bg-blue-500 text-blue-500',
                            'emerald' => 'bg-emerald-500 text-emerald-500',
                            'indigo' => 'bg-indigo-500 text-indigo-500',
                            'purple' => 'bg-purple-500 text-purple-500',
                            default => 'bg-gray-400 text-gray-400',
                        };
                        // Decreasing z-index so earlier groups float OVER later groups while remaining under topbar
                        $groupZIndex = max(1, 10 - $loop->index);
                    @endphp
                    <div x-data="{ collapsed: false, isOver: false }"
                         x-on:dragover.prevent="isOver = true; $event.dataTransfer.dropEffect = 'move'"
                         x-on:dragleave.prevent="isOver = false"
                         x-on:drop.prevent="
                            isOver = false;
                            const payload = $event.dataTransfer.getData('text/plain');
                            const taskId = parseInt(payload);
                            if (!taskId) return;
                            const alreadyHere = {{ \Illuminate\Support\Js::from($groupTaskIds) }}.map(Number).includes(taskId);
                            if (alreadyHere) return;
                            @if(($group['type'] ?? '') === 'status')
                                @if(! $isManager && in_array($group['state_type'] ?? '', ['completed', 'closed'], true))
                                    if (String(payload).split('|')[1] === '1') return;
                                @endif
                                $wire.moveTaskToState(taskId, {{ $group['state_id'] ?? 0 }})
                            @elseif(($group['type'] ?? '') === 'priority')
                                $wire.updateTaskPriority(taskId, '{{ $groupKey }}')
                            @elseif(($group['type'] ?? '') === 'project')
                                $wire.updateTaskProject(taskId, {{ $groupKey === 'none' ? 'null' : (is_numeric($groupKey) ? $groupKey : 'null') }})
                            @elseif(($group['type'] ?? '') === 'assignee')
                                $wire.updateTaskAssignee(taskId, {{ $groupKey === 'unassigned' ? 'null' : (is_numeric($groupKey) ? $groupKey : 'null') }})
                            @elseif(($group['type'] ?? '') === 'due_date')
                                $wire.moveTaskToDueGroup(taskId, '{{ $groupKey }}')
                            @endif
                         "
                         style="z-index: {{ $groupZIndex }}; position: relative;"
                         class="space-y-1 transition-all duration-150 rounded-xl p-1"
                         x-bind:class="isOver ? 'ring-2 ring-brand-500 bg-brand-50/40 dark:bg-brand-950/40 scale-[1.002]' : ''">

                        {{-- Group Header Bar --}}
                        <div class="flex items-center justify-between px-3 py-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200/80 dark:border-white/10 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-2xs group">
                            <div class="flex items-center gap-2.5">
                                {{-- Checkbox to Select All in Group --}}
                                <input type="checkbox"
                                       @change="$wire.selectAllTasks({{ json_encode($groupTaskIds) }})"
                                       {{ $isGroupAllSelected ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-600 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-brand-500 cursor-pointer">

                                <button @click="collapsed = !collapsed" class="flex items-center gap-2 text-left">
                                    <x-heroicon-m-chevron-right x-show="collapsed" class="w-4 h-4 text-gray-400 shrink-0"/>
                                    <x-heroicon-m-chevron-down x-show="!collapsed" class="w-4 h-4 text-gray-400 shrink-0"/>
                                    <span class="w-2.5 h-2.5 rounded-full {{ explode(' ', $groupColorClass)[0] }}"></span>
                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-900 dark:text-white">{{ $group['title'] }}</span>
                                    <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                        {{ $group['tasks']->count() }}
                                    </span>
                                </button>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="text-[10px] font-medium text-brand-600 dark:text-brand-400 opacity-0 group-hover:opacity-100 transition-opacity">
                                    Drop task to move
                                </span>
                                @php
                                    $totalEstHours = $group['tasks']->sum('estimated_hours');
                                @endphp
                                @if($totalEstHours > 0)
                                    <span class="text-[11px] font-mono font-medium text-gray-400 dark:text-gray-500">
                                        Est: {{ number_format($totalEstHours, 1) }}h
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Task Rows Container --}}
                        <div x-show="!collapsed" class="py-2.5">
                            @forelse($group['tasks'] as $task)
                                @php
                                    $isSelected = in_array($task->id, $selectedTasks);
                                    $statusBadge = match ($task->statusKey()) {
                                        'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                                        'code_review' => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                        'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                        default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                                    };
                                    $dueUrgency = $task->dueUrgency();
                                    $progress = $task->calculateProgress();
                                    $childTasks = $task->relationLoaded('directSubtasks') ? $task->directSubtasks : collect();
                                    $hasChildren = $childTasks->isNotEmpty();
                                @endphp
                                <div x-data="{ expanded: false }" class="space-y-0.5">
                                <div draggable="true"
                                     x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $task->id }}|{{ $task->mustPassReview() ? '1' : '0' }}'); $event.dataTransfer.effectAllowed = 'move'"
                                     class="px-3.5 py-2 flex items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition relative {{ $isSelected ? 'bg-brand-50 dark:bg-brand-950' : '' }} group cursor-grab active:cursor-grabbing select-none rounded-lg border border-transparent hover:border-gray-200 dark:hover:border-gray-800">

                                    {{-- Left Section: Drag Handle, Checkbox, Priority & Title --}}
                                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                        {{-- Expand subtasks --}}
                                        @if($hasChildren)
                                            <button type="button"
                                                    @click.stop="expanded = !expanded"
                                                    @mousedown.stop
                                                    draggable="false"
                                                    class="p-0.5 rounded text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 shrink-0"
                                                    title="Show subtasks"
                                                    aria-label="Toggle subtasks">
                                                <x-heroicon-m-chevron-right x-show="!expanded" class="w-3.5 h-3.5"/>
                                                <x-heroicon-m-chevron-down x-show="expanded" class="w-3.5 h-3.5"/>
                                            </button>
                                        @else
                                            <span class="w-4 shrink-0" aria-hidden="true"></span>
                                        @endif

                                        {{-- Drag Handle Icon --}}
                                        <x-heroicon-m-ellipsis-vertical class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0 group-hover:text-gray-300 transition cursor-grab" title="Drag task to move between groups"/>

                                        {{-- Checkbox --}}
                                        <input type="checkbox"
                                               wire:click.stop="toggleSelectTask({{ $task->id }})"
                                               {{ $isSelected ? 'checked' : '' }}
                                               class="w-4 h-4 text-brand-600 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-brand-500 cursor-pointer shrink-0">

                                        {{-- Fast Priority Selector (Inline Pill) --}}
                                        <div x-data="{ open: false }" class="relative shrink-0" @mousedown.stop @click.stop draggable="false">
                                            <button @click.stop="open = !open" @mousedown.stop class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition flex items-center gap-1 text-xs" title="Change priority" aria-label="Change priority">
                                                <x-ui.priority-dot :priority="$task->priority?->value" />
                                            </button>
                                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 mt-1 z-50 w-32 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-1 text-xs">
                                                <button type="button" wire:click="updateTaskPriority({{ $task->id }}, 'urgent')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="urgent" with-label /></button>
                                                <button type="button" wire:click="updateTaskPriority({{ $task->id }}, 'high')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="high" with-label /></button>
                                                <button type="button" wire:click="updateTaskPriority({{ $task->id }}, 'medium')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="medium" with-label /></button>
                                                <button type="button" wire:click="updateTaskPriority({{ $task->id }}, 'low')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="low" with-label /></button>
                                            </div>
                                        </div>

                                        {{-- Title --}}
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <a href="{{ \App\Helpers\TaskNav::detailUrl($task->id) }}" wire:navigate @click.stop
                                               class="text-xs font-semibold text-gray-900 dark:text-white truncate hover:text-brand-600 dark:hover:text-brand-400 transition text-left">
                                                {{ $task->title }}
                                            </a>
                                        </div>

                                        {{-- Project Badge --}}
                                        <span class="text-[10px] font-semibold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-lg shrink-0 hidden md:inline-flex border border-gray-200/60 dark:border-gray-700">
                                            {{ $task->project?->name ?? 'General' }}
                                        </span>

                                        <x-tasks.meta-chips :task="$task" class="hidden sm:inline-flex" />

                                        {{-- Checklist Progress Bar --}}
                                        @if($progress > 0 || $task->checklists->count() > 0)
                                            <div class="w-16 items-center gap-1 shrink-0 hidden lg:flex" title="Progress: {{ $progress }}%">
                                                <div class="flex-1 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $progress }}%"></div>
                                                </div>
                                                <span class="text-[9px] font-mono text-gray-400">{{ $progress }}%</span>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Right Section: Status, Assignee, Due Date, Actions --}}
                                    <div class="flex items-center gap-3 shrink-0 ml-auto">
                                        {{-- Fast Inline Status Selector Pill --}}
                                        <div x-data="{ open: false }" class="relative shrink-0" @mousedown.stop @click.stop draggable="false">
                                            <button @click.stop="open = !open" @mousedown.stop class="px-2.5 py-1 rounded-lg text-[11px] font-bold border flex items-center gap-1 transition shadow-2xs {{ $statusBadge }}">
                                                <span>{{ $task->status->label() }}</span>
                                                <x-heroicon-m-chevron-down class="w-3 h-3 opacity-70"/>
                                            </button>
                                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 z-50 w-36 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-1 text-xs divide-y divide-gray-100 dark:divide-gray-700">
                                                @foreach($workflowStates as $ws)
                                                @continue(! $isManager && $task->mustPassReview() && in_array($ws->type, ['completed', 'closed'], true))
                                                @php
                                                    $wsColor = match($ws->type) {
                                                        'initial' => 'text-gray-700 dark:text-gray-200',
                                                        'active' => 'text-blue-600 dark:text-blue-400',
                                                        'completed' => 'text-emerald-600 dark:text-emerald-400',
                                                        default => 'text-purple-600 dark:text-purple-400',
                                                    };
                                                    $wsDot = match($ws->type) {
                                                        'initial' => 'bg-gray-400',
                                                        'active' => 'bg-blue-500',
                                                        'completed' => 'bg-emerald-500',
                                                        default => 'bg-purple-500',
                                                    };
                                                @endphp
                                                <button wire:click="moveTaskToState({{ $task->id }}, {{ $ws->id }})" @click="open = false" class="w-full px-3 py-1.5 text-left font-semibold {{ $wsColor }} hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                                    <span class="w-2 h-2 rounded-full {{ $wsDot }}"></span> {{ $ws->name }}
                                                </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        @if($isManager && $task->statusKey() === 'code_review')
                                            <div class="flex items-center gap-1 shrink-0" @mousedown.stop @click.stop draggable="false">
                                                <button type="button" wire:click.stop="approveTask({{ $task->id }})" class="px-2 py-1 rounded-lg bg-emerald-600 text-[10px] font-bold text-white hover:bg-emerald-700">
                                                    Approve &amp; done
                                                </button>
                                                <button type="button" @click.stop="$wire.showReviewModal = true; $wire.openReviewModal({{ $task->id }})" class="px-2 py-1 rounded-lg bg-red-600 text-[10px] font-bold text-white hover:bg-red-700">
                                                    Changes
                                                </button>
                                            </div>
                                        @endif

                                        {{-- Assignee Avatar Stack --}}
                                        <div x-data="{ open: false }" class="relative shrink-0 flex items-center" @mousedown.stop @click.stop draggable="false">
                                            <button @click.stop="open = !open" @mousedown.stop class="focus:outline-none flex items-center -space-x-1.5 transition transform hover:scale-105" title="Manage assignees">
                                                @forelse($task->assignees->take(3) as $ass)
                                                    <x-ui.person-avatar :person="$ass" size="md" ring class="shadow-xs" />
                                                @empty
                                                    <span class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 flex items-center justify-center text-[10px] border border-dashed border-gray-300 dark:border-gray-700 hover:border-brand-500 transition">
                                                        +
                                                    </span>
                                                @endforelse
                                                @if($task->assignees->count() > 3)
                                                    <span class="w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 flex items-center justify-center text-[9px] font-mono font-bold ring-2 ring-white dark:ring-gray-900">
                                                        +{{ $task->assignees->count() - 3 }}
                                                    </span>
                                                @endif
                                            </button>
                                            <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-1 z-50 w-44 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-1 text-xs max-h-48 overflow-y-auto">
                                                <button wire:click="updateTaskAssignee({{ $task->id }}, null)" @click="open = false" class="w-full px-3 py-1.5 text-left font-medium text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                                    Unassigned
                                                </button>
                                                @foreach($employeeRoster as $emp)
                                                    <button wire:click="updateTaskAssignee({{ $task->id }}, {{ $emp->id }})" @click="open = false" class="w-full px-3 py-1.5 text-left font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 truncate flex items-center gap-2">
                                                        <x-ui.person-avatar :person="$emp" size="xs" />
                                                        {{ $emp->name }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        {{-- Due Date Picker Pill --}}
                                        <x-tasks.due-date-popover
                                            :value="$task->due_date?->format('Y-m-d')"
                                            :urgency="$dueUrgency"
                                            wire-action="updateTaskDueDate"
                                            :wire-params="[$task->id]"
                                        />

                                        {{-- Cycle time chip (created -> done / now) --}}
                                        <div class="shrink-0">
                                            <x-tasks.lifecycle-chip :task="$task" />
                                        </div>

                                        {{-- Actions --}}
                                        <div class="flex items-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0" @mousedown.stop @click.stop draggable="false" x-on:dragstart.prevent.stop>
                                            <a href="{{ \App\Helpers\TaskNav::detailUrl($task->id) }}" class="p-1 rounded text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 transition" title="Open full task workspace" aria-label="Open full task workspace">
                                                <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5"/>
                                            </a>
                                            @if($canDelete)
                                            <x-ui.confirm-button
                                                type="button"
                                                heading="Delete this task?"
                                                message="This cannot be undone."
                                                confirm-label="Delete"
                                                method="deleteTask"
                                                :params="[$task->id]"
                                                variant="danger-ghost"
                                                size="icon-sm"
                                                title="Delete task"
                                                aria-label="Delete task"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                            </x-ui.confirm-button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Nested subtasks (list view only) --}}
                                <div x-show="expanded" x-cloak class="ml-8 space-y-0.5 border-l border-gray-200 dark:border-gray-700 pl-2">
                                    @foreach($childTasks as $sub)
                                        @php
                                            $subStatusBadge = match ($sub->statusKey()) {
                                                'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 border-blue-200 dark:border-blue-800',
                                                'code_review' => 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 border-purple-200 dark:border-purple-800',
                                                'done' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700',
                                            };
                                        @endphp
                                        <div class="px-3 py-1.5 flex items-center gap-2.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/80 group/sub">
                                            <x-heroicon-m-document-text class="w-3.5 h-3.5 text-gray-400 shrink-0"/>
                                            <a href="{{ \App\Helpers\TaskNav::detailUrl($sub->id) }}" wire:navigate
                                               class="min-w-0 flex-1 text-left text-xs font-medium text-gray-700 dark:text-gray-200 truncate hover:text-brand-600 dark:hover:text-brand-400">
                                                {{ $sub->title }}
                                            </a>
                                            <span class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-md border {{ $subStatusBadge }}">
                                                {{ $sub->status->label() }}
                                            </span>
                                            <div class="flex items-center -space-x-1 shrink-0">
                                                @foreach($sub->assignees->take(2) as $subAss)
                                                    <x-ui.person-avatar :person="$subAss" size="xs" ring />
                                                @endforeach
                                            </div>
                                            @if($sub->due_date)
                                                <span class="shrink-0 text-[10px] font-mono {{ $sub->dueDateToneClasses('text') }}">
                                                    {{ $sub->due_date->format('M d') }}
                                                </span>
                                            @endif
                                            <a href="{{ \App\Helpers\TaskNav::detailUrl($sub->id) }}"
                                               class="p-0.5 rounded text-gray-400 opacity-0 group-hover/sub:opacity-100 hover:text-brand-600 transition shrink-0"
                                               title="Open subtask"
                                               aria-label="Open subtask">
                                                <x-heroicon-m-arrow-top-right-on-square class="w-3 h-3"/>
                                            </a>
                                        </div>
                                    @endforeach

                                    @if($canCreate)
                                        <div x-data="{ open: false, title: '' }" class="px-3 py-1" @mousedown.stop @click.stop>
                                            <button x-show="!open" @click="open = true" type="button"
                                                    class="w-full rounded-lg py-1 text-left text-[11px] font-medium text-gray-400 hover:text-brand-600 dark:hover:text-brand-400">
                                                + Add subtask
                                            </button>
                                            <div x-show="open" x-cloak class="flex gap-1.5">
                                                <input x-model="title"
                                                       @keydown.enter="$wire.quickCreateSubtask({{ $task->id }}, title); title=''; open=false"
                                                       @keydown.escape="open=false"
                                                       x-ref="subInput"
                                                       x-init="$watch('open', v => { if(v) $nextTick(() => $refs.subInput.focus()) })"
                                                       placeholder="Subtask name..."
                                                       class="flex-1 rounded-xl border-gray-200 py-1 px-2 text-[11px] focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                                                <button type="button"
                                                        @click="$wire.quickCreateSubtask({{ $task->id }}, title); title=''; open=false"
                                                        class="rounded-xl bg-brand-500 px-2 py-1 text-[11px] font-bold text-white hover:bg-brand-600">
                                                    Add
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                </div>
                            @empty
                                <p class="px-4 py-3 text-xs text-gray-400 italic">No tasks in this group.</p>
                            @endforelse

                            <div x-data="{ open: false, title: '' }" class="px-3 pt-1" @mousedown.stop @click.stop>
                                <button x-show="!open" @click="open = true" type="button" class="w-full rounded-lg py-1.5 text-left text-xs font-medium text-gray-400 hover:bg-gray-50 hover:text-brand-600 dark:hover:bg-gray-800 dark:hover:text-brand-400">
                                    + Add task
                                </button>
                                <div x-show="open" x-cloak class="flex gap-1.5">
                                    <input x-model="title"
                                           @keydown.enter="$wire.quickCreateTask(title, {{ ($group['state_id'] ?? 'null') }}); title=''; open=false"
                                           @keydown.escape="open=false"
                                           x-ref="quickInput"
                                           x-init="$watch('open', v => { if(v) $nextTick(() => $refs.quickInput.focus()) })"
                                           placeholder="Task name..."
                                           class="flex-1 rounded-xl border-gray-200 py-1.5 px-2.5 text-xs focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800">
                                    <button type="button" @click="$wire.quickCreateTask(title, {{ ($group['state_id'] ?? 'null') }}); title=''; open=false" class="rounded-xl bg-brand-500 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-brand-600">Add</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            @endif

        @elseif($currentView === 'board')
            {{-- ─────────── CLICKUP KANBAN BOARD VIEW ─────────── --}}
            <div class="flex gap-4 p-2 overflow-x-auto" style="min-height: 550px;">
                @foreach($board['columns'] as $col)
                    @php
                        $stateColors = match ($col['state_type']) {
                            'initial' => ['dot' => 'bg-gray-400', 'bg' => 'bg-gray-50 dark:bg-gray-800/40', 'border' => 'border-gray-200 dark:border-gray-700'],
                            'active' => ['dot' => 'bg-blue-500', 'bg' => 'bg-blue-50/30 dark:bg-blue-950/20', 'border' => 'border-blue-200/50 dark:border-blue-900/30'],
                            'completed' => ['dot' => 'bg-emerald-500', 'bg' => 'bg-emerald-50/30 dark:bg-emerald-950/20', 'border' => 'border-emerald-200/50 dark:border-emerald-900/30'],
                            default => ['dot' => 'bg-gray-400', 'bg' => 'bg-gray-50 dark:bg-gray-800/40', 'border' => 'border-gray-200 dark:border-gray-700'],
                        };
                    @endphp
                    <div x-data="{ isOver: false }"
                         x-on:dragover.prevent="isOver = true; $event.dataTransfer.dropEffect = 'move'"
                         x-on:dragleave.prevent="isOver = false"
                         x-on:drop.prevent="
                            isOver = false;
                            const payload = $event.dataTransfer.getData('text/plain');
                            const taskId = parseInt(payload);
                            if (!taskId) return;
                            const alreadyHere = {{ \Illuminate\Support\Js::from($col['tasks']->pluck('id')->all()) }}.map(Number).includes(taskId);
                            if (alreadyHere) return;
                            @if(! $isManager && in_array($col['state_type'], ['completed', 'closed'], true))
                                if (String(payload).split('|')[1] === '1') return;
                            @endif
                            $wire.moveTaskToState(taskId, {{ $col['state_id'] }})
                         "
                         class="{{ $stateColors['bg'] }} rounded-2xl border {{ $stateColors['border'] }} ring-1 ring-gray-950/5 dark:ring-white/10 flex flex-col flex-shrink-0 transition-all duration-150"
                         x-bind:class="isOver ? 'ring-2 ring-brand-500 bg-brand-50/40 dark:bg-brand-950/40 scale-[1.01]' : ''"
                         style="width: 320px;">

                        {{-- Column Header --}}
                        <div class="p-3 flex justify-between items-center border-b border-gray-200/50 dark:border-white/5">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full {{ $stateColors['dot'] }}"></span>
                                <span class="font-bold text-xs uppercase tracking-wider text-gray-900 dark:text-white">{{ $col['state_name'] }}</span>
                                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 shadow-2xs border border-gray-200 dark:border-gray-700">
                                    {{ $col['stats']['count'] }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($isManager)
                                    <label class="flex items-center gap-1 text-[10px] font-mono text-gray-400" title="WIP limit (0 = none)">
                                        WIP {{ $col['stats']['count'] }}/
                                        <input type="number" min="0"
                                               value="{{ $col['wip_limit'] }}"
                                               wire:change="updateWipLimit({{ $col['state_id'] }}, $event.target.value)"
                                               class="w-12 rounded border border-gray-200 bg-white px-1 py-0.5 text-[10px] font-mono text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 {{ $col['is_wip_exceeded'] ? 'border-red-400 text-red-600 dark:text-red-400' : '' }}">
                                    </label>
                                @elseif($col['wip_limit'] > 0)
                                    <span class="text-[10px] font-mono {{ $col['is_wip_exceeded'] ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-400' }}">
                                        WIP: {{ $col['stats']['count'] }}/{{ $col['wip_limit'] }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Drag Cards Container --}}
                        <div class="flex-1 overflow-y-auto p-2.5 space-y-2.5" style="max-height: calc(100vh - 320px);">
                            @forelse($col['tasks'] as $task)
                                @php
                                    $isSelected = in_array($task->id, $selectedTasks);
                                    $priBorder = match ($task->priority?->value) {
                                        'urgent' => 'border-l-red-500',
                                        'high' => 'border-l-amber-500',
                                        'medium' => 'border-l-blue-400',
                                        default => 'border-l-gray-300 dark:border-l-gray-600',
                                    };
                                    $dueUrgency = $task->dueUrgency();
                                    $progress = $task->calculateProgress();
                                @endphp
                                <div draggable="true"
                                     x-on:dragstart="$event.dataTransfer.setData('text/plain', '{{ $task->id }}|{{ $task->mustPassReview() ? '1' : '0' }}'); $event.dataTransfer.effectAllowed = 'move'"
                                     class="bg-white dark:bg-gray-800 rounded-xl border-l-[3.5px] {{ $priBorder }} border border-gray-200/80 dark:border-white/10 shadow-2xs hover:shadow-md hover:ring-1 hover:ring-brand-500/30 transition-all p-3 space-y-2 group cursor-grab active:cursor-grabbing select-none {{ $isSelected ? 'ring-2 ring-brand-500 bg-brand-50/20 dark:bg-brand-950/20' : '' }}">

                                    {{-- Card Top: Checkbox + Title + Menu --}}
                                    <div class="flex justify-between items-start gap-2">
                                        <div class="flex items-start gap-2 flex-1 min-w-0">
                                            <input type="checkbox"
                                                   wire:click.stop="toggleSelectTask({{ $task->id }})"
                                                   {{ $isSelected ? 'checked' : '' }}
                                                   class="w-4 h-4 text-brand-600 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-brand-500 cursor-pointer shrink-0 mt-0.5">
                                            <h4 class="text-xs font-semibold text-gray-900 dark:text-white leading-snug line-clamp-2 break-words">
                                                <a href="{{ \App\Helpers\TaskNav::detailUrl($task->id) }}" wire:navigate @click.stop class="hover:text-brand-600 dark:hover:text-brand-400 text-left">{{ $task->title }}</a>
                                            </h4>
                                        </div>
                                        <a href="{{ \App\Helpers\TaskNav::detailUrl($task->id) }}" wire:navigate @click.stop class="p-1 rounded text-gray-400 opacity-0 group-hover:opacity-100 hover:text-brand-500 transition" title="Open task">
                                            <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5"/>
                                        </a>
                                    </div>

                                    {{-- Cycle time chip --}}
                                    <div class="mt-1">
                                        <x-tasks.lifecycle-chip :task="$task" />
                                    </div>

                                    {{-- Project Tag --}}
                                    @if($task->project)
                                        <span class="inline-flex text-[10px] font-semibold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded-md">
                                            {{ $task->project->name }}
                                        </span>
                                    @endif

                                    <x-tasks.meta-chips :task="$task" />

                                    {{-- Checklist Progress Bar --}}
                                    @if($progress > 0 || $task->checklists->count() > 0)
                                        <div class="flex items-center gap-2">
                                            <div class="flex-1 h-1 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                            </div>
                                            <span class="text-[9px] font-mono text-gray-400">{{ $progress }}%</span>
                                        </div>
                                    @endif

                                    {{-- Card Footer: Assignee + Due Date + Move Arrow --}}
                                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800 text-[10px]">
                                        <div class="flex items-center gap-2">
                                            <div class="flex items-center -space-x-1.5">
                                                @forelse($task->assignees->take(3) as $ass)
                                                    <x-ui.person-avatar :person="$ass" size="sm" ring />
                                                @empty
                                                    <span class="w-5 h-5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 flex items-center justify-center text-[9px]">?</span>
                                                @endforelse
                                                @if($task->assignees->count() > 3)
                                                    <span class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 flex items-center justify-center text-[8px] font-mono font-bold ring-2 ring-white dark:ring-gray-900">
                                                        +{{ $task->assignees->count() - 3 }}
                                                    </span>
                                                @endif
                                            </div>
                                            <x-tasks.due-date-popover
                                                :value="$task->due_date?->format('Y-m-d')"
                                                :urgency="$dueUrgency"
                                                wire-action="updateTaskDueDate"
                                                :wire-params="[$task->id]"
                                                align="left"
                                            />
                                        </div>

                                        @if($isManager && $task->statusKey() === 'code_review')
                                            <div class="flex items-center gap-1" @mousedown.stop @click.stop draggable="false">
                                                <button type="button" wire:click.stop="approveTask({{ $task->id }})" class="px-1.5 py-0.5 rounded bg-emerald-600 text-[9px] font-bold text-white hover:bg-emerald-700">Approve &amp; done</button>
                                                <button type="button" @click.stop="$wire.showReviewModal = true; $wire.openReviewModal({{ $task->id }})" class="px-1.5 py-0.5 rounded bg-red-600 text-[9px] font-bold text-white hover:bg-red-700">Changes</button>
                                            </div>
                                        @endif

                                        {{-- Quick Move Arrows --}}
                                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            @php
                                                $currentIdx = collect($board['columns'])->search(fn($c) => $c['state_id'] === $col['state_id']);
                                                $nextCol = $board['columns'][$currentIdx + 1] ?? null;
                                                $prevCol = $board['columns'][$currentIdx - 1] ?? null;
                                            @endphp
                                            @if($prevCol)
                                                <button wire:click="moveTaskToState({{ $task->id }}, {{ $prevCol['state_id'] }})" class="p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-gray-700 transition" title="Move back to {{ $prevCol['state_name'] }}" aria-label="Move back to {{ $prevCol['state_name'] }}">
                                                    <x-heroicon-m-chevron-left class="w-3.5 h-3.5"/>
                                                </button>
                                            @endif
                                            @if($nextCol && ($isManager || ! $task->mustPassReview() || ! in_array($nextCol['state_type'] ?? '', ['completed', 'closed'], true)))
                                                <button wire:click="moveTaskToState({{ $task->id }}, {{ $nextCol['state_id'] }})" class="p-0.5 rounded hover:bg-brand-100 dark:hover:bg-brand-900/30 text-gray-400 hover:text-brand-600 transition" title="Move forward to {{ $nextCol['state_name'] }}" aria-label="Move forward to {{ $nextCol['state_name'] }}">
                                                    <x-heroicon-m-chevron-right class="w-3.5 h-3.5"/>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="py-10 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 border border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                                    <x-heroicon-o-plus-circle class="w-5 h-5 mb-1 opacity-40"/>
                                    <span class="text-[10px] font-medium">No tasks</span>
                                </div>
                            @endforelse
                        </div>

                        {{-- Quick Add Input at Column Bottom --}}
                        <div class="p-2.5 pt-0">
                            <div x-data="{ open: false, title: '' }" class="mt-1">
                                <button x-show="!open" @click="open = true" class="w-full py-1.5 text-xs text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 font-medium flex items-center justify-center gap-1 rounded-lg hover:bg-white dark:hover:bg-gray-800 transition">
                                    <x-heroicon-o-plus class="w-3.5 h-3.5"/> Add Task
                                </button>
                                <div x-show="open" x-cloak class="flex gap-1.5">
                                    <input x-model="title" @keydown.enter="$wire.quickCreateTask(title, {{ $col['state_id'] }}); title=''; open=false" @keydown.escape="open=false" x-ref="input" x-init="$watch('open', v => { if(v) $nextTick(() => $refs.input.focus()) })" placeholder="Task name..." class="flex-1 text-xs rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-800 py-1.5 px-2.5 focus:ring-brand-500">
                                    <button @click="$wire.quickCreateTask(title, {{ $col['state_id'] }}); title=''; open=false" class="px-2.5 py-1.5 bg-brand-500 text-white text-xs rounded-xl font-bold hover:bg-brand-600 transition">Add</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        @elseif($currentView === 'table')
            {{-- ─────────── CLICKUP TABLE VIEW ─────────── --}}
            @php
                $allTaskIds = $tasks->pluck('id')->toArray();
                $isMasterAllSelected = count(array_intersect($allTaskIds, $selectedTasks)) === count($allTaskIds) && count($allTaskIds) > 0;
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-white/10 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-white/10">
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-3 py-2.5 w-8 text-center text-gray-500 dark:text-gray-400">
                                <input type="checkbox"
                                       @change="$wire.selectAllTasks({{ json_encode($allTaskIds) }})"
                                       {{ $isMasterAllSelected ? 'checked' : '' }}
                                       class="w-4 h-4 text-brand-600 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-brand-500 cursor-pointer">
                            </th>
                            @foreach([
                                    ['id', 'ID'],
                                    ['title', 'Title'],
                                    [null, 'Status'],
                                    ['priority', 'Priority'],
                                    [null, 'Project'],
                                    [null, 'Assignee'],
                                    ['due_date', 'Due Date'],
                                    [null, 'Progress'],
                                    ['estimated_hours', 'Est. Hrs'],
                                    ['created_at', 'Created'],
                                    [null, 'Cycle'],
                                    [null, 'Actions'],
                                ] as [$sortField, $label])
                                                                                                    <th class="px-3 py-2.5 font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider text-[10px] {{ $sortField ? 'cursor-pointer hover:text-gray-900 dark:hover:text-white select-none' : '' }}" @if($sortField) wire:click="setSortBy('{{ $sortField }}')" @endif>
                                                                                                        <span class="flex items-center gap-1">
                                                                                                            {{ $label }}
                                                                                                            @if($sortField && $sortBy === $sortField)
                                                                                                                @if($sortDirection === 'asc')
                                                                                                                    <x-heroicon-m-chevron-up class="w-3 h-3"/>
                                                                                                                @else
                                                                                                                    <x-heroicon-m-chevron-down class="w-3 h-3"/>
                                                                                                                @endif
                                                                                                            @endif
                                                                                                        </span>
                                                                                                    </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach($tasks as $t)
                            @php
                                $isSelected = in_array($t->id, $selectedTasks);
                                $statusBadge = match ($t->statusKey()) {
                                    'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
                                    'code_review' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300',
                                    'done' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                };
                                $progress = $t->calculateProgress();
                            @endphp
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-white/[0.03] transition {{ $isSelected ? 'bg-brand-50/40 dark:bg-brand-950/20' : '' }} group">
                                <td class="px-3 py-2.5 text-center">
                                    <input type="checkbox"
                                           wire:click="toggleSelectTask({{ $t->id }})"
                                           {{ $isSelected ? 'checked' : '' }}
                                           class="w-4 h-4 text-brand-600 rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:ring-brand-500 cursor-pointer">
                                </td>
                                <td class="px-3 py-2.5 font-mono text-gray-400 text-[11px]">#{{ $t->id }}</td>
                                <td class="px-3 py-2.5 font-semibold text-gray-900 dark:text-white max-w-[220px]">
                                    <div class="flex flex-col gap-1 min-w-0">
                                        <a href="{{ \App\Helpers\TaskNav::detailUrl($t->id) }}" wire:navigate class="hover:text-brand-600 dark:hover:text-brand-400 text-left truncate max-w-full">{{ $t->title }}</a>
                                        <x-tasks.meta-chips :task="$t" />
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    {{-- Inline Status Pill --}}
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click.stop="open = !open" class="px-2 py-0.5 rounded-lg text-[10px] font-bold flex items-center gap-1 transition {{ $statusBadge }}">
                                            <span>{{ $t->status->label() }}</span>
                                            <x-heroicon-m-chevron-down class="w-3 h-3 opacity-60"/>
                                        </button>
                                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 mt-1 z-50 w-36 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-1 text-xs divide-y divide-gray-100 dark:divide-gray-700">
                                            @foreach($workflowStates as $ws)
                                            @continue(! $isManager && $t->mustPassReview() && in_array($ws->type, ['completed', 'closed'], true))
                                            <button wire:click="moveTaskToState({{ $t->id }}, {{ $ws->id }})" @click="open = false" class="w-full px-3 py-1.5 text-left font-semibold {{ match($ws->type) { 'initial' => 'text-gray-700 dark:text-gray-200', 'active' => 'text-blue-600 dark:text-blue-400', 'completed' => 'text-emerald-600 dark:text-emerald-400', default => 'text-purple-600 dark:text-purple-400' } }} hover:bg-gray-100 dark:hover:bg-gray-700">{{ $ws->name }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    {{-- Inline Priority Selector --}}
                                    <div x-data="{ open: false }" class="relative">
                                        <button @click.stop="open = !open" class="px-1.5 py-0.5 rounded text-xs font-semibold flex items-center gap-1 hover:bg-gray-100 dark:hover:bg-gray-800 transition" title="Change priority" aria-label="Change priority">
                                            <x-ui.priority-dot :priority="$t->priority?->value" with-label />
                                        </button>
                                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute left-0 mt-1 z-50 w-32 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 py-1 text-xs">
                                            <button type="button" wire:click="updateTaskPriority({{ $t->id }}, 'urgent')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="urgent" with-label /></button>
                                            <button type="button" wire:click="updateTaskPriority({{ $t->id }}, 'high')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="high" with-label /></button>
                                            <button type="button" wire:click="updateTaskPriority({{ $t->id }}, 'medium')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="medium" with-label /></button>
                                            <button type="button" wire:click="updateTaskPriority({{ $t->id }}, 'low')" @click="open = false" class="flex w-full items-center px-3 py-1.5 text-left font-semibold hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="low" with-label /></button>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-gray-600 dark:text-gray-400 font-medium">{{ $t->project?->name ?? 'General' }}</td>
                                <td class="px-3 py-2.5">
                                    @if($t->assignees->count() > 0)
                                        <div class="flex items-center -space-x-1.5">
                                            @foreach($t->assignees->take(3) as $ass)
                                                <x-ui.person-avatar :person="$ass" size="sm" ring />
                                            @endforeach
                                            @if($t->assignees->count() > 3)
                                                <span class="w-4 h-4 rounded-full bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 flex items-center justify-center text-[8px] font-mono font-bold ring-2 ring-white dark:ring-gray-900">
                                                    +{{ $t->assignees->count() - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-400 italic">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 font-mono {{ $t->dueDateToneClasses('text') }}">
                                    {{ $t->due_date ? $t->due_date->format('M d, Y') : '—' }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="w-16 h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $progress }}%"></div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 font-mono text-gray-500">{{ $t->estimated_hours ?? 0 }}h</td>
                                <td class="px-3 py-2.5 text-gray-400 font-mono text-[11px]">{{ $t->created_at->format('M d') }}</td>
                                <td class="px-3 py-2.5">
                                    <x-tasks.lifecycle-chip :task="$t" />
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        @if($isManager && $t->statusKey() === 'code_review')
                                            <button type="button" wire:click="approveTask({{ $t->id }})" class="px-2 py-1 rounded-lg bg-emerald-600 text-[10px] font-bold text-white hover:bg-emerald-700">Approve &amp; done</button>
                                            <button type="button" @click="$wire.showReviewModal = true; $wire.openReviewModal({{ $t->id }})" class="px-2 py-1 rounded-lg bg-red-600 text-[10px] font-bold text-white hover:bg-red-700">Changes</button>
                                        @endif
                                        <a href="{{ \App\Helpers\TaskNav::detailUrl($t->id) }}" class="p-1 rounded text-gray-400 hover:text-brand-600 dark:hover:text-brand-400 transition {{ $isManager && $t->statusKey() === 'code_review' ? '' : 'opacity-0 group-hover:opacity-100' }}" title="Open full task workspace" aria-label="Open full task workspace">
                                            <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5"/>
                                        </a>
                                        @if($canDelete)
                                        <x-ui.confirm-button
                                            type="button"
                                            heading="Delete this task?"
                                            message="This cannot be undone."
                                            confirm-label="Delete"
                                            method="deleteTask"
                                            :params="[$t->id]"
                                            variant="danger-ghost"
                                            size="icon-sm"
                                            title="Delete task"
                                            aria-label="Delete task"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                        </x-ui.confirm-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        @elseif($currentView === 'calendar')
            {{-- ─────────── CALENDAR VIEW ─────────── --}}
            @php
                $calDate = \Carbon\Carbon::create($calendarYear, $calendarMonth, 1);
                $daysInMonth = $calDate->daysInMonth;
                $firstDayOfWeek = $calDate->dayOfWeekIso;
                $monthLabel = $calDate->format('F Y');
                $monthHasDatedTasks = $tasks->contains(
                    fn ($t) => $t->due_date
                        && $t->due_date->year === $calendarYear
                        && $t->due_date->month === $calendarMonth
                );
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-white/10 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200/80 dark:border-white/10">
                    <button wire:click="prevMonth" class="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition" title="Previous month" aria-label="Previous month">
                        <x-heroicon-m-chevron-left class="w-4 h-4 text-gray-500"/>
                    </button>
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-calendar class="w-4 h-4 text-brand-500"/> {{ $monthLabel }}
                    </h3>
                    <button wire:click="nextMonth" class="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition" title="Next month" aria-label="Next month">
                        <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-500"/>
                    </button>
                </div>

                @unless ($monthHasDatedTasks)
                    <div class="border-b border-dashed border-gray-200 px-6 py-10 text-center dark:border-gray-700">
                        <x-heroicon-o-calendar class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"/>
                        <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">Nothing scheduled this month</h3>
                        <p class="mt-1 text-sm text-gray-500">Add a due date to a task, or create one for today.</p>
                        <button type="button" @click="$wire.showCreateModal = true; $wire.openCreateModal()" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                            <x-heroicon-m-plus class="h-4 w-4"/> New Task
                        </button>
                    </div>
                @endunless

                <div class="grid grid-cols-7 text-center text-[10px] font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-800">
                    @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                        <div class="py-2.5">{{ $d }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7">
                    @for($i = 1; $i < $firstDayOfWeek; $i++)
                        <div class="min-h-[95px] p-1.5 bg-gray-50/50 dark:bg-gray-800 border-b border-r border-gray-100 dark:border-white/5"></div>
                    @endfor

                    @for($day = 1; $day <= $daysInMonth; $day++)
                        @php
                            $currentDate = $calDate->copy()->addDays($day - 1)->format('Y-m-d');
                            $dayTasks = $tasks->filter(fn($t) => $t->due_date && $t->due_date->format('Y-m-d') === $currentDate);
                            $isToday = $currentDate === now()->format('Y-m-d');
                        @endphp
                        <div @click="$wire.showCreateModal = true; $wire.openCreateModal('{{ $currentDate }}')" class="min-h-[95px] p-1.5 border-b border-r border-gray-100 dark:border-white/5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition {{ $isToday ? 'bg-brand-50/40 dark:bg-brand-950/20' : '' }}">
                            <span class="text-[10px] font-bold {{ $isToday ? 'bg-brand-500 text-white w-5 h-5 rounded-full inline-flex items-center justify-center' : 'text-gray-400 dark:text-gray-500' }}">{{ $day }}</span>
                            <div class="space-y-1 mt-1">
                                @foreach($dayTasks->take(3) as $dt)
                                    @php
                                        $dtColor = match ($dt->priority?->value) {
                                            'urgent' => 'border-l-red-500 bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300',
                                            'high' => 'border-l-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300',
                                            'medium' => 'border-l-blue-400 bg-blue-50 text-blue-700 dark:bg-blue-950/30 dark:text-blue-300',
                                            default => 'border-l-gray-300 bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                        };
                                    @endphp
                                    <a href="{{ \App\Helpers\TaskNav::detailUrl($dt->id) }}" wire:navigate @click.stop class="block w-full text-left text-[9px] font-semibold truncate px-1.5 py-0.5 rounded border-l-2 {{ $dtColor }} hover:opacity-80 transition" title="{{ $dt->title }}">
                                        {{ $dt->title }}
                                    </a>
                                @endforeach
                                @if($dayTasks->count() > 3)
                                    <span class="text-[9px] font-bold text-gray-400 pl-1">+{{ $dayTasks->count() - 3 }} more</span>
                                @elseif($dayTasks->isEmpty() && $isToday)
                                    <p class="text-[9px] text-gray-400">Nothing due</p>
                                @endif
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

        @elseif($currentView === 'timeline')
            {{-- ─────────── GANTT TIMELINE VIEW ─────────── --}}
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200/80 dark:border-white/10 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-chart-bar class="w-4 h-4 text-brand-500"/> Project Timeline
                    </h3>
                    <span class="text-[10px] text-gray-400 font-mono">{{ count($timeline['tasks']) }} tasks mapped</span>
                </div>

                @forelse($timeline['tasks'] as $gt)
                    @php
                        $gtTask = $tasks->firstWhere('id', $gt['id']);
                        $priColor = match ($gtTask?->priority?->value ?? 'medium') {
                            'urgent' => 'from-red-500 to-red-400',
                            'high' => 'from-amber-500 to-amber-400',
                            'medium' => 'from-blue-500 to-blue-400',
                            default => 'from-gray-400 to-gray-300',
                        };
                        $priBg = match ($gtTask?->priority?->value ?? 'medium') {
                            'urgent' => 'bg-red-100 dark:bg-red-950/30',
                            'high' => 'bg-amber-100 dark:bg-amber-950/30',
                            'medium' => 'bg-blue-100 dark:bg-blue-950/30',
                            default => 'bg-gray-100 dark:bg-gray-800',
                        };
                    @endphp
                    <div class="flex items-center gap-4 group py-2 border-b border-gray-100 dark:border-white/5 last:border-0">
                        <div class="w-48 shrink-0">
                            <div class="flex items-center gap-1 min-w-0">
                                <a href="{{ \App\Helpers\TaskNav::detailUrl($gt['id']) }}" wire:navigate class="text-xs font-semibold text-gray-900 dark:text-white truncate hover:text-brand-600 text-left">{{ $gt['title'] }}</a>
                            </div>
                            <span class="text-[10px] text-gray-400 font-mono">{{ $gt['start_date'] }} → {{ $gt['due_date'] }}</span>
                        </div>
                        <div class="flex-1 {{ $priBg }} h-6 rounded-lg overflow-hidden relative">
                            <div class="h-full bg-gradient-to-r {{ $priColor }} rounded-lg flex items-center justify-end px-2 transition-all shadow-2xs" style="width: {{ max($gt['progress'], 10) }}%">
                                <span class="text-[9px] font-bold text-white drop-shadow-sm">{{ $gt['progress'] }}%</span>
                            </div>
                        </div>
                        <div class="shrink-0 flex -space-x-1">
                            @foreach(($gt['assignees'] ?? []) as $a)
                                <x-ui.person-avatar :name="$a['name']" :src="$a['src'] ?? null" :seed="$a['seed'] ?? $a['id'] ?? null" size="sm" ring />
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-gray-400 italic">No tasks with scheduled dates to display on timeline.</div>
                @endforelse
            </div>
        @endif

        @if(!empty($hasMoreTasks) && in_array($currentView, ['list', 'table', 'timeline'], true))
            <div class="flex justify-center pt-1">
                <button type="button" wire:click="loadMore" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-bold text-gray-600 shadow-theme-xs hover:border-gray-300 hover:text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-white">
                    Load more tasks
                </button>
            </div>
        @endif

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- FLOATING CLICKUP MULTITASK TOOLBAR                             --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        @if(count($selectedTasks) > 0)
            <template x-teleport="body">
                <div x-data="{ openStatus: false, openPriority: false, openAssignee: false, openDueDate: false }"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-6 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     class="fixed bottom-6 inset-x-0 mx-auto w-max max-w-[calc(100vw-2rem)] z-[9999] flex items-center justify-center gap-2 p-2 bg-white/95 dark:bg-gray-900/95 text-gray-900 dark:text-white backdrop-blur-md rounded-2xl border border-gray-200 dark:border-gray-700 shadow-2xl ring-1 ring-gray-950/5 dark:ring-white/10 text-xs whitespace-nowrap">

                    {{-- Selection Count Badge --}}
                    <div class="flex items-center gap-1.5 px-3 py-1.5 bg-brand-500 text-white rounded-xl font-bold shadow-2xs">
                        <span>{{ count($selectedTasks) }}</span>
                        <span>Selected</span>
                    </div>

                    <div class="h-4 w-px bg-gray-200 dark:bg-gray-700"></div>

                    {{-- Bulk Status Dropdown --}}
                    <div class="relative">
                        <button @click="openStatus = !openStatus; openPriority = false; openAssignee = false; openDueDate = false"
                                class="px-2.5 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-white/10 flex items-center gap-1.5 font-semibold transition text-gray-700 dark:text-gray-200">
                            <x-heroicon-o-arrow-path class="w-3.5 h-3.5 text-blue-500 dark:text-blue-400"/>
                            <span>Status</span>
                            <x-heroicon-m-chevron-down class="w-3 h-3 text-gray-400"/>
                        </button>
                        <div x-show="openStatus" @click.outside="openStatus = false" x-cloak
                             class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-36 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl py-1 text-xs divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($workflowStates as $ws)
                            @php $slugStatus = \Illuminate\Support\Str::slug($ws->name, '_'); @endphp
                            <button wire:click="bulkUpdateStatus('{{ $slugStatus }}')" @click="openStatus = false" class="w-full px-3 py-1.5 text-left font-medium {{ match($ws->type) { 'initial' => 'text-gray-700 dark:text-gray-200', 'active' => 'text-blue-600 dark:text-blue-400', 'completed' => 'text-emerald-600 dark:text-emerald-400', default => 'text-purple-600 dark:text-purple-400' } }} hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ match($ws->type) { 'initial' => 'bg-gray-400', 'active' => 'bg-blue-500', 'completed' => 'bg-emerald-500', default => 'bg-purple-500' } }}"></span> {{ $ws->name }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Bulk Priority Dropdown --}}
                    <div class="relative">
                        <button @click="openPriority = !openPriority; openStatus = false; openAssignee = false; openDueDate = false"
                                class="px-2.5 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-white/10 flex items-center gap-1.5 font-semibold transition text-gray-700 dark:text-gray-200">
                            <x-heroicon-o-flag class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400"/>
                            <span>Priority</span>
                            <x-heroicon-m-chevron-down class="w-3 h-3 text-gray-400"/>
                        </button>
                        <div x-show="openPriority" @click.outside="openPriority = false" x-cloak
                             class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-32 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl py-1 text-xs">
                            <button type="button" wire:click="bulkUpdatePriority('urgent')" @click="openPriority = false" class="flex w-full items-center px-3 py-1.5 text-left font-medium hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="urgent" with-label /></button>
                            <button type="button" wire:click="bulkUpdatePriority('high')" @click="openPriority = false" class="flex w-full items-center px-3 py-1.5 text-left font-medium hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="high" with-label /></button>
                            <button type="button" wire:click="bulkUpdatePriority('medium')" @click="openPriority = false" class="flex w-full items-center px-3 py-1.5 text-left font-medium hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="medium" with-label /></button>
                            <button type="button" wire:click="bulkUpdatePriority('low')" @click="openPriority = false" class="flex w-full items-center px-3 py-1.5 text-left font-medium hover:bg-gray-100 dark:hover:bg-gray-700"><x-ui.priority-dot priority="low" with-label /></button>
                        </div>
                    </div>

                    {{-- Bulk Assign Dropdown --}}
                    <div class="relative">
                        <button @click="openAssignee = !openAssignee; openStatus = false; openPriority = false; openDueDate = false"
                                class="px-2.5 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-white/10 flex items-center gap-1.5 font-semibold transition text-gray-700 dark:text-gray-200">
                            <x-heroicon-o-user-plus class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400"/>
                            <span>Assign</span>
                            <x-heroicon-m-chevron-down class="w-3 h-3 text-gray-400"/>
                        </button>
                        <div x-show="openAssignee" @click.outside="openAssignee = false" x-cloak
                             class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 w-44 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xl py-1 text-xs max-h-48 overflow-y-auto">
                            <button wire:click="bulkAssign(null)" @click="openAssignee = false" class="w-full px-3 py-1.5 text-left font-medium text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                Unassign All
                            </button>
                            @foreach($employeeRoster as $emp)
                                <button wire:click="bulkAssign({{ $emp->id }})" @click="openAssignee = false" class="w-full px-3 py-1.5 text-left font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 truncate flex items-center gap-1.5">
                                    <x-ui.person-avatar :person="$emp" size="xs" />
                                    {{ $emp->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Bulk Due Date Picker --}}
                    <div class="relative">
                        <button @click="openDueDate = !openDueDate; openStatus = false; openPriority = false; openAssignee = false"
                                class="px-2.5 py-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-white/10 flex items-center gap-1.5 font-semibold transition text-gray-700 dark:text-gray-200">
                            <x-heroicon-o-calendar class="w-3.5 h-3.5 text-purple-500 dark:text-purple-400"/>
                            <span>Due Date</span>
                        </button>
                        <div x-show="openDueDate" @click.outside="openDueDate = false" x-cloak
                             class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 z-50">
                            <x-tasks.due-date-popover
                                embedded
                                wire-action="bulkUpdateDueDate"
                                :wire-params="[]"
                                x-on:due-date-selected="openDueDate = false"
                            />
                        </div>
                    </div>

                    <div class="h-4 w-px bg-gray-200 dark:bg-gray-700"></div>

                    {{-- Bulk Delete Button --}}
                    @if($canDelete)
                    <x-ui.confirm-button
                        heading="Delete selected tasks?"
                        message="{{ count($selectedTasks) }} {{ count($selectedTasks) === 1 ? 'task' : 'tasks' }} will be permanently deleted."
                        confirm-label="Delete"
                        method="bulkDelete"
                        variant="danger-ghost"
                        size="xs"
                    >
                        <x-heroicon-o-trash class="w-3.5 h-3.5"/>
                        <span>Delete</span>
                    </x-ui.confirm-button>
                    @endif

                    {{-- Deselect All Button --}}
                    <button wire:click="clearSelection" class="p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-white/10 text-gray-400 hover:text-gray-900 dark:hover:text-white transition" title="Clear selection" aria-label="Clear selection">
                        <x-heroicon-m-x-mark class="w-4 h-4"/>
                    </button>
                </div>
            </template>
        @endif

    </div>

    <x-ui.slide-form-modal entangle="showCreateModal" loading-target="openCreateModal" title="New Task" description="Create a task and optionally assign it to a project and teammate." close-method="$set('showCreateModal', false)" size="lg">
        <form id="modal-create-task" wire:submit="createTask" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Title</label>
                <input wire:model="formTitle" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                @error('formTitle')<p class="mt-1 text-sm text-error-500">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.select.searchable wire:model="formProjectId" label="Project" :options="$projects" placeholder="Select project" empty-option="No project" search-placeholder="Search projects..." />
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Priority</label>
                    <select wire:model="formPriority" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <x-form.select.searchable wire:model="formAssigneeIds" label="Assign To" :options="$employees" placeholder="Select employees" :multiple="true" search-placeholder="Search employees..." />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.date-picker wire:model="formDueDate" label="Due Date" placeholder="Pick due date" allow-clear />
                <x-form.date-picker wire:model="formStartDate" label="Start Date" placeholder="Pick start date" allow-clear />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                <textarea wire:model="formDescription" rows="3" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-create-task" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Create</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

    <x-ui.slide-form-modal entangle="showEditModal" loading-target="openEditModal" title="Edit Task" description="The list stays open. Expand for comments, checklists, and files." close-method="closeEditModal" size="xl" variant="drawer">
        <form id="modal-edit-task" wire:submit="editTask" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Title</label>
                <input wire:model="formTitle" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.select.searchable wire:model="formProjectId" label="Project" :options="$projects" placeholder="Select project" empty-option="No project" search-placeholder="Search projects..." />
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
                    <select wire:model="formStatus" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        @foreach($workflowStates as $ws)
                        @continue(! $isManager && $editingTaskMustPassReview && in_array($ws->type, ['completed', 'closed'], true))
                        <option value="{{ \Illuminate\Support\Str::slug($ws->name, '_') }}">{{ $ws->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Priority</label>
                    <select wire:model="formPriority" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <x-form.select.searchable wire:model="formAssigneeIds" label="Assign To" :options="$employees" placeholder="Select employees" :multiple="true" search-placeholder="Search employees..." />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-form.date-picker wire:model="formDueDate" label="Due Date" placeholder="Pick due date" allow-clear />
                <x-form.date-picker wire:model="formStartDate" label="Start Date" placeholder="Pick start date" allow-clear />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                <textarea wire:model="formDescription" rows="3" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
        </form>
        <x-slot:footer>
            @if($editingTaskId)
                <a href="{{ \App\Helpers\TaskNav::detailUrl($editingTaskId) }}" class="mr-auto text-sm font-medium text-brand-500 hover:underline">Open full task</a>
            @endif
            <button type="button" wire:click="closeEditModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-edit-task" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Save</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

    <x-ui.slide-form-modal entangle="showReviewModal" loading-target="openReviewModal" title="Request changes" description="The assignee will see this note in My Tasks and by email." close-method="closeReviewModal" size="sm">
        <form id="modal-review-task" wire:submit="submitReview" class="space-y-3">
            @if($reviewingTaskTitle !== '')
                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $reviewingTaskTitle }}</p>
            @endif
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">What should they change?</label>
                <textarea wire:model="reviewNote" rows="4" placeholder="Add a note the assignee will see…" class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="closeReviewModal" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-review-task" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-red-700">Send back</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>
</div>