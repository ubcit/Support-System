<div>
    <x-common.page-breadcrumb pageTitle="Projects" compact>
        <x-slot:subtitle>{{ $selected_project ? $selected_project->name : 'All projects' }}</x-slot:subtitle>
        <x-slot:actions>
            @if($selected_project)
            <x-ui.button variant="outline" wire:click="clearSelection">
                <x-heroicon-m-squares-2x2 class="h-4 w-4"/> All projects
            </x-ui.button>
            @endif
            @if($canManage)
            <x-ui.button @click="$wire.showCreateModal = true; $wire.openCreateModal()">
                <x-heroicon-m-plus class="h-4 w-4"/> New Project
            </x-ui.button>
            @endif
        </x-slot:actions>
    </x-common.page-breadcrumb>

    <div class="relative space-y-6">
        <x-ui.content-loading />
        @if($selected_project)
            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl ring-1 ring-gray-950/5 dark:ring-white/10 space-y-4 shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs uppercase text-brand-600 dark:text-brand-400 font-semibold">Project #{{ $selected_project->id }}</span>
                            @if($selected_project->code)
                                <span
                                    x-data="{ copied: false }"
                                    class="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-white/10 px-2 py-0.5 text-[11px] font-mono font-semibold text-gray-700 dark:text-gray-200"
                                >
                                    <span>{{ $selected_project->code }}</span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded p-0.5 text-gray-500 hover:text-brand-600 dark:hover:text-brand-400"
                                        title="Copy project code"
                                        aria-label="Copy project code"
                                        @click="navigator.clipboard.writeText(@js($selected_project->code)); copied = true; setTimeout(() => copied = false, 1500)"
                                    >
                                        <x-heroicon-m-clipboard-document class="h-3.5 w-3.5" x-show="!copied"/>
                                        <x-heroicon-m-check class="h-3.5 w-3.5 text-emerald-500" x-cloak x-show="copied"/>
                                    </button>
                                </span>
                            @endif
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mt-0.5">{{ $selected_project->name }}</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            <span class="font-semibold text-gray-700 dark:text-gray-300">Customer:</span>
                            @if($selected_project->customer)
                                <a href="{{ route('customer-crm') }}" class="hover:text-brand-600 dark:hover:text-brand-400">{{ $selected_project->customer->name }}</a>
                            @elseif(($selected_project->customers ?? collect())->isNotEmpty())
                                {{ $selected_project->customers->pluck('name')->join(', ') }}
                            @else
                                Not linked yet
                            @endif
                        </p>
                        @if(($selected_project->customers ?? collect())->count() > 1)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">Linked customers:</span>
                                {{ $selected_project->customers->pluck('name')->join(', ') }}
                            </p>
                        @endif
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $selected_project->description }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                            <span class="uppercase font-bold tracking-wide rounded px-2 py-0.5 {{ $health_status === 'Healthy' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : ($health_status === 'At Risk' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300') }}">{{ $health_status }}</span>
                            <span class="uppercase font-semibold">{{ $selected_project->status?->label() ?? 'Active' }}</span>
                            @if($selected_project->started_at)
                                <span>Started {{ $selected_project->started_at->format('M j, Y') }}</span>
                            @endif
                            @if($selected_project->deadline_at)
                                <span class="{{ $selected_project->deadline_at->isPast() && $selected_project->status?->value !== 'completed' ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">Deadline {{ $selected_project->deadline_at->format('M j, Y') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        @if($canManage)
                        <x-ui.button variant="outline" size="icon" @click="$wire.showEditModal = true; $wire.openEditModal({{ $selected_project->id }})" title="Edit Project" aria-label="Edit Project">
                            <x-heroicon-m-pencil-square class="h-4 w-4"/>
                        </x-ui.button>
                        <x-ui.confirm-button
                            heading="Archive this project?"
                            message="It will be archived and hidden from the project hub."
                            confirm-label="Archive"
                            method="archiveProject"
                            :params="[$selected_project->id]"
                            variant="danger"
                            size="icon"
                            title="Archive Project"
                            aria-label="Archive Project"
                        >
                            <x-heroicon-m-archive-box class="h-4 w-4"/>
                        </x-ui.confirm-button>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-ui.metric-card label="Completion" :value="$progress.'%'" :tone="$progress >= 50 ? 'success' : 'default'" :hint="$completed_count.' of '.($total_tasks ?? $tasks->count()).' done'" />
                    <x-ui.metric-card label="Overdue" :value="$overdue_count" href="{{ route('task-dashboard', ['project' => $selected_project->id, 'due' => 'overdue']) }}" :tone="$overdue_count ? 'danger' : 'default'" hint="Past due" />
                    <x-ui.metric-card label="Unassigned" :value="$unassigned_open" href="{{ route('task-dashboard', ['project' => $selected_project->id, 'scope' => 'unassigned']) }}" :tone="$unassigned_open ? 'warning' : 'default'" hint="Open without owner" />
                    <x-ui.metric-card label="Open Issues" :value="$open_issue_count" :tone="$open_issue_count ? 'warning' : 'default'" :hint="$issues->count().' total'" />
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ route('task-dashboard', ['view' => 'board', 'project' => $selected_project->id]) }}" class="text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 px-3 py-1.5 rounded-lg border border-indigo-200 dark:border-indigo-800 flex items-center gap-1 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition">
                        <x-heroicon-s-view-columns class="w-4 h-4"/> Board
                    </a>
                    <a href="{{ route('task-dashboard', ['view' => 'list', 'project' => $selected_project->id]) }}" class="text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 px-3 py-1.5 rounded-lg border border-blue-200 dark:border-blue-800 flex items-center gap-1 hover:bg-blue-100 dark:hover:bg-blue-900/60 transition">
                        <x-heroicon-s-bars-4 class="w-4 h-4"/> Task list
                    </a>
                    <a href="{{ route('task-dashboard', ['view' => 'timeline', 'project' => $selected_project->id]) }}" class="text-xs font-bold bg-gray-50 text-gray-700 dark:bg-white/5 dark:text-gray-300 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-white/10 flex items-center gap-1 hover:bg-gray-100 dark:hover:bg-white/10 transition">
                        Timeline
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Project Members</h3>
                    </div>
                    <div class="p-5">
                        @if($member_stats->isEmpty())
                            <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No members assigned.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($member_stats as $row)
                                    @php $member = $row['employee']; @endphp
                                    <div class="p-2 bg-gray-50 dark:bg-gray-800 rounded-lg flex items-center justify-between gap-3 border border-transparent dark:border-white/10">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <x-ui.person-avatar :person="$member" size="lg" />
                                            <div class="min-w-0">
                                                <h4 class="font-bold text-xs text-gray-900 dark:text-white truncate">{{ $member->name ?? $member->user?->name ?? 'Unknown' }}</h4>
                                                <span class="text-[10px] text-gray-500 dark:text-gray-400 font-mono">{{ $member->pivot->role ?? $member->role ?? 'Member' }}</span>
                                            </div>
                                        </div>
                                        <div class="shrink-0 text-[10px] text-gray-500 dark:text-gray-400 text-right">
                                            <div>{{ $row['active'] }} open</div>
                                            <div class="{{ $row['overdue'] ? 'text-red-600 dark:text-red-400 font-semibold' : '' }}">{{ $row['overdue'] }} overdue · {{ $row['done'] }} done</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Sprints & Milestones</h3>
                    </div>
                    <div class="p-5">
                        @if($sprints->isEmpty() && $milestones->isEmpty())
                            <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No active sprints or milestones.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($sprints as $sprint)
                                    @php
                                        $sprintTasks = $sprint->tasks;
                                        $sprintDone = $sprintTasks->filter(fn ($task) => $task->isCompleted())->count();
                                        $sprintTotal = $sprintTasks->count();
                                    @endphp
                                    <div class="p-3 bg-blue-50/50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-xl">
                                        <h4 class="font-bold text-xs text-gray-900 dark:text-white">{{ $sprint->name }}</h4>
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400">Sprint {{ $sprint->status ? '· '.ucfirst($sprint->status) : '' }} • Ends {{ $sprint->end_date?->format('M d') ?? 'TBD' }} • {{ $sprintDone }}/{{ $sprintTotal }} done</span>
                                        @if($sprint->goal)
                                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ $sprint->goal }}</p>
                                        @endif
                                    </div>
                                @endforeach
                                @foreach($milestones as $milestone)
                                    @php
                                        $msTasks = $milestone->tasks;
                                        $msDone = $msTasks->filter(fn ($task) => $task->isCompleted())->count();
                                        $msTotal = $msTasks->count();
                                    @endphp
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-white/10 rounded-xl">
                                        <h4 class="font-bold text-xs text-gray-900 dark:text-white">{{ $milestone->name }}</h4>
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400">Milestone • {{ $milestone->due_date?->format('M d') ?? 'Flexible' }} • {{ $msDone }}/{{ $msTotal }} done</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Open Issues</h3>
                    </div>
                    <div class="p-5">
                        @forelse($open_issues as $issue)
                            <div class="p-3 mb-2 last:mb-0 rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="font-bold text-xs text-gray-900 dark:text-white">{{ $issue->title }}</h4>
                                    <span class="shrink-0 text-[10px] uppercase font-bold text-gray-500">{{ $issue->status?->label() ?? $issue->status }}</span>
                                </div>
                                @if($issue->due_date)
                                    <p class="text-[10px] text-gray-400 mt-1">Due {{ $issue->due_date->format('M d') }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No open issues.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Project Tasks & Deliverables</h3>
                        @if($tasks->count() > $visible_tasks->count())
                            <a href="{{ route('task-dashboard', ['project' => $selected_project->id, 'view' => 'list']) }}" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline">View all</a>
                        @endif
                    </div>
                    <div class="p-5">
                        @if($visible_tasks->isEmpty())
                            <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No tasks in this project.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($visible_tasks as $t)
                                    <a href="{{ route('task-detail', $t->id) }}" wire:navigate class="p-3 bg-gray-50 dark:bg-white/5 rounded-xl ring-1 {{ $t->isOverdue() ? 'ring-red-200 dark:ring-red-800/50' : 'ring-gray-950/5 dark:ring-white/10' }} flex justify-between items-center hover:ring-brand-300 dark:hover:ring-brand-500/40">
                                        <div>
                                            <h4 class="font-bold text-xs text-gray-900 dark:text-white">{{ $t->title }}</h4>
                                            <span class="text-[11px] {{ $t->dueDateToneClasses('text') }}">{{ match ($t->dueUrgency()) {
                                                'overdue' => 'Overdue',
                                                'today' => 'Today',
                                                'tomorrow' => 'Tomorrow',
                                                default => 'Due',
                                            } }}: {{ $t->due_date ? $t->due_date->format('M d') : 'Flexible' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-gray-200 dark:bg-white/10 text-gray-800 dark:text-gray-200 uppercase">{{ $t->statusKey() }}</span>
                                            <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded {{ ($t->priority?->value ?? '') === 'urgent' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-gray-200 dark:bg-white/10 text-gray-800 dark:text-gray-200' }}">
                                                {{ $t->priority?->value ?? 'medium' }}
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            @if($projects->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-6 py-16 text-center dark:border-gray-800 dark:bg-white/[0.03]">
                    <x-heroicon-o-briefcase class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600"/>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">No projects yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Create a project to get started. You can link customers later with the project code.</p>
                    @if($canManage)
                    <x-ui.button class="mt-4" @click="$wire.showCreateModal = true; $wire.openCreateModal()">
                        <x-heroicon-m-plus class="h-4 w-4"/> New Project
                    </x-ui.button>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($projects as $project)
                        @php
                            $total = (int) ($project->total_tasks_count ?? 0);
                            $active = (int) ($project->active_tasks_count ?? 0);
                            $completed = (int) ($project->completed_tasks_count ?? 0);
                            $overdue = (int) ($project->overdue_tasks_count ?? 0);
                            $pct = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
                            $customerLabel = $project->customer?->name
                                ?? ($project->customers->isNotEmpty() ? $project->customers->pluck('name')->join(', ') : null);
                            $statusValue = $project->status instanceof \BackedEnum
                                ? $project->status->value
                                : (string) ($project->status ?? 'active');
                            $statusLabel = $project->status?->label() ?? ucfirst(str_replace('_', ' ', $statusValue));
                            $statusTone = match ($statusValue) {
                                'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
                                'paused' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
                                'completed' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
                                default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
                            };
                        @endphp
                        <a
                            href="{{ route('project-hub', ['project' => $project->id]) }}"
                            wire:navigate
                            class="group flex flex-col rounded-2xl border border-gray-200 bg-white p-5 transition hover:border-brand-300 hover:shadow-sm dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-500/40"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $statusTone }}">{{ $statusLabel }}</span>
                                        @if($project->code)
                                            <span class="font-mono text-[11px] font-semibold text-gray-500 dark:text-gray-400">{{ $project->code }}</span>
                                        @endif
                                    </div>
                                    <h3 class="mt-2 truncate text-base font-semibold text-gray-900 group-hover:text-brand-600 dark:text-white dark:group-hover:text-brand-400">{{ $project->name }}</h3>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $customerLabel ?? 'Customer not linked yet' }}
                                    </p>
                                </div>
                                <x-heroicon-m-chevron-right class="mt-1 h-4 w-4 shrink-0 text-gray-300 transition group-hover:text-brand-500 dark:text-gray-600"/>
                            </div>

                            @if($project->description)
                                <p class="mt-3 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ $project->description }}</p>
                            @endif

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <div class="rounded-xl bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Active</p>
                                    <p class="mt-0.5 text-lg font-bold text-gray-900 dark:text-white">{{ $active }}</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Done</p>
                                    <p class="mt-0.5 text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $completed }}</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Overdue</p>
                                    <p class="mt-0.5 text-lg font-bold {{ $overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ $overdue }}</p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="mb-1.5 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                                    <span>Progress</span>
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $pct }}% · {{ $completed }}/{{ $total }}</span>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-brand-500 transition-all" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-[11px] text-gray-500 dark:border-white/5 dark:text-gray-400">
                                <span>{{ (int) ($project->members_count ?? 0) }} {{ \Illuminate\Support\Str::plural('member', (int) ($project->members_count ?? 0)) }}</span>
                                @if($project->deadline_at)
                                    <span class="{{ $project->deadline_at->isPast() && $statusValue !== 'completed' ? 'font-semibold text-red-600 dark:text-red-400' : '' }}">
                                        Due {{ $project->deadline_at->format('M j, Y') }}
                                    </span>
                                @else
                                    <span>No deadline</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    <x-ui.slide-form-modal entangle="showCreateModal" loading-target="openCreateModal" title="New Project" description="Set the team now; link a customer later with the project code if needed." close-method="$set('showCreateModal', false)" size="lg">
        <form id="modal-create-project" wire:submit="newProject" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                <input type="text" wire:model="name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                @error('name') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-form.select.searchable wire:model="customer_id" label="Customer" :options="$customerOptions" placeholder="Select Customer" empty-option="Select Customer" search-placeholder="Search customers..." />
                @error('customer_id') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                <textarea wire:model="description" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
                    <select wire:model="status" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <x-form.select.searchable wire:model="employees" label="Team Members" :options="$employeeOptions" placeholder="Select team members" :multiple="true" search-placeholder="Search employees..." />
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-create-project" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Create</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

    <x-ui.slide-form-modal entangle="showEditModal" loading-target="openEditModal" title="Edit Project" description="Update project details and team assignment." close-method="$set('showEditModal', false)" size="lg">
        <form id="modal-edit-project" wire:submit="editProject" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                <input type="text" wire:model="name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                @error('name') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-form.select.searchable wire:model="customer_id" label="Customer" :options="$customerOptions" placeholder="Select Customer" empty-option="Select Customer" search-placeholder="Search customers..." />
                @error('customer_id') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                <textarea wire:model="description" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Status</label>
                    <select wire:model="status" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <x-form.select.searchable wire:model="employees" label="Team Members" :options="$employeeOptions" placeholder="Select team members" :multiple="true" search-placeholder="Search employees..." />
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showEditModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-edit-project" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Save</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>
</div>
