<div>
    <x-common.page-breadcrumb pageTitle="Customers">
        <x-slot:subtitle>Directory, conversations, and account context</x-slot:subtitle>
        <x-slot:actions>
            <x-ui.button @click="$wire.showCreateModal = true; $wire.openCreateModal()">
                <x-heroicon-m-plus class="h-4 w-4"/> New Customer
            </x-ui.button>
        </x-slot:actions>
    </x-common.page-breadcrumb>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <div class="space-y-4 lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 flex justify-between items-center shadow-xs">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">CRM Customers</h3>
                <span class="text-xs px-2.5 py-1 bg-gray-100 dark:bg-gray-700/80 rounded-lg font-mono font-medium text-gray-700 dark:text-gray-300">{{ $customers->count() }} Accounts</span>
            </div>

            <div>
                <input type="text" wire:model.live.debounce.300ms="searchQuery" placeholder="Search customers..." class="w-full p-1 text-sm rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white focus:ring-brand-500 focus:border-brand-500 shadow-xs">
            </div>

            <div class="space-y-2 max-h-[calc(100vh-240px)] overflow-y-auto pr-1">
                @forelse($customers as $c)
                    <button wire:click="selectCustomer({{ $c->id }})"
                        class="w-full text-left p-3 rounded-xl border transition flex items-center justify-between {{ $selected_customer?->id === $c->id ? 'bg-indigo-50/80 border-indigo-300 dark:bg-indigo-950/40 dark:border-indigo-800 shadow-xs' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}">
                        <div class="min-w-0 pr-2">
                            <h4 class="font-bold text-sm text-gray-900 dark:text-white truncate">{{ $c->name }}</h4>
                            <span class="text-xs text-gray-500 dark:text-gray-400 truncate block mt-0.5">{{ $c->company ?: ($c->phone ?: 'No phone') }}</span>
                        </div>
                        <span class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 shrink-0 bg-indigo-100/60 dark:bg-indigo-950/80 px-2 py-0.5 rounded">
                            {{ $c->conversations_count }} chats
                        </span>
                    </button>
                @empty
                    <div class="text-center p-6 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 text-xs text-gray-500">
                        No customers found.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6 max-h-[calc(100vh-240px)] overflow-y-auto pr-1">
            @if($selected_customer)
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 space-y-4 shadow-xs">
                    <div class="flex justify-between items-start flex-wrap gap-3">
                        <div>
                            <span class="text-xs uppercase text-indigo-600 dark:text-indigo-400 font-semibold tracking-wider">Customer Account #{{ $selected_customer->id }}</span>
                            <h2 class="text-xl font-black text-gray-900 dark:text-white mt-0.5">{{ $selected_customer->name }}</h2>
                            @if($selected_customer->company)
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-0.5">{{ $selected_customer->company }}</p>
                            @endif
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono mt-1">Phone: {{ $selected_customer->phone ?: 'N/A' }} • Email: {{ $selected_customer->email ?: 'N/A' }} • WhatsApp: {{ $selected_customer->whatsapp_id ?? $selected_customer->phone ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                Last message {{ $stats['last_message_at']?->diffForHumans() ?? 'never' }}
                                • Customer since {{ $selected_customer->created_at?->format('M j, Y') }}
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                @if(!$selected_customer->is_active)
                                    <span class="inline-block text-[10px] uppercase font-bold tracking-wider bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300 px-2 py-0.5 rounded">Inactive</span>
                                @else
                                    <span class="inline-block text-[10px] uppercase font-bold tracking-wider bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300 px-2 py-0.5 rounded">Active</span>
                                @endif
                            </div>
                            @if(filled($selected_customer->notes))
                                <p class="mt-3 text-xs text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-white/5 rounded-lg px-3 py-2">{{ $selected_customer->notes }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('customer-ai-limits') }}" class="{{ \App\Helpers\UiButton::classes('outline', 'sm') }}">AI Limits</a>
                            <x-ui.button variant="outline" size="icon" @click="$wire.showEditModal = true; $wire.openEditModal()" title="Edit customer" aria-label="Edit customer">
                                <x-heroicon-m-pencil-square class="h-4 w-4"/>
                            </x-ui.button>
                            <x-ui.confirm-button
                                heading="Delete this customer?"
                                message="Related records may be affected. This cannot be undone."
                                confirm-label="Delete"
                                method="deleteCustomer"
                                :params="[$selected_customer->id]"
                                variant="danger"
                                size="icon"
                                title="Delete Customer"
                                aria-label="Delete Customer"
                            >
                                <x-heroicon-m-trash class="h-4 w-4"/>
                            </x-ui.confirm-button>
                        </div>
                    </div>

                    @if(isset($selected_customer->metadata['ai_summary']))
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-950/30 rounded-xl border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-900 dark:text-indigo-200">
                        <strong class="block mb-1 font-bold flex items-center gap-1">
                            <x-heroicon-s-sparkles class="w-4 h-4 text-amber-500"/> AI Account Summary & Sentiment
                        </strong>
                        {{ $selected_customer->metadata['ai_summary'] }}
                    </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <x-ui.metric-card label="Open Tasks" :value="$stats['open_tasks']" href="{{ route('task-dashboard') }}" tone="{{ $stats['open_tasks'] ? 'info' : 'default' }}" hint="{{ $stats['task_total'] }} total" />
                    <x-ui.metric-card label="Overdue" :value="$stats['overdue_tasks']" href="{{ route('task-dashboard', ['due' => 'overdue']) }}" tone="{{ $stats['overdue_tasks'] ? 'danger' : 'default' }}" hint="Past due" />
                    <x-ui.metric-card label="Issues" :value="$stats['open_issues']" tone="{{ $stats['open_issues'] ? 'warning' : 'default' }}" hint="{{ $stats['issue_total'] }} linked" />
                    <x-ui.metric-card label="Sessions" :value="$stats['sessions']" href="{{ route('conversation-center') }}" tone="info" hint="{{ $stats['needs_review'] }} need review" />
                    <x-ui.metric-card label="Messages" :value="$stats['inbound'] + $stats['outbound']" hint="{{ $stats['inbound'] }} in · {{ $stats['outbound'] }} out" />
                    <x-ui.metric-card
                        label="AI spend today"
                        :value="'$'.number_format($stats['ai_spent_today'], 2)"
                        href="{{ route('customer-ai-limits') }}"
                        :tone="$stats['ai_limit'] !== null && $stats['ai_spent_today'] >= $stats['ai_limit'] ? 'danger' : 'default'"
                        :hint="$stats['ai_limit'] === null ? 'No daily cap' : 'of $'.number_format($stats['ai_limit'], 2).' cap'"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Conversations & Threads</h3>
                            <a href="{{ route('conversation-center') }}" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline">Inbox</a>
                        </div>
                        <div class="p-6">
                            @if($conversations->isEmpty())
                                <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No active conversation history.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($conversations as $conv)
                                        <a href="{{ route('conversation-center', ['conversation' => $conv->id]) }}" wire:navigate class="block p-3 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-brand-300 dark:hover:border-brand-500/40">
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="font-bold text-gray-900 dark:text-white">{{ ucfirst($conv->channel) }} thread</span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $conv->last_message_at?->diffForHumans() ?? $conv->updated_at->diffForHumans() }}</span>
                                            </div>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 line-clamp-2">
                                                {{ $conv->latestMessage?->body ?? $conv->latestSession?->displayTitle() ?? 'No message body' }}
                                            </p>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Usage & Sessions</h3>
                        </div>
                        <div class="p-6 space-y-3">
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-gray-50 dark:bg-white/5 px-2 py-2">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $stats['inbound'] }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-400">Inbound</div>
                                </div>
                                <div class="rounded-lg bg-gray-50 dark:bg-white/5 px-2 py-2">
                                    <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $stats['outbound'] }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-400">Outbound</div>
                                </div>
                                <div class="rounded-lg bg-gray-50 dark:bg-white/5 px-2 py-2">
                                    <div class="text-sm font-bold {{ $stats['needs_review'] ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ $stats['needs_review'] }}</div>
                                    <div class="text-[10px] uppercase tracking-wide text-gray-400">Review</div>
                                </div>
                            </div>
                            @if($sessions->isEmpty())
                                <p class="text-xs text-gray-500 dark:text-gray-400 py-2">No sessions yet.</p>
                            @else
                                <div class="space-y-2">
                                    @foreach($sessions as $session)
                                        <a href="{{ route('conversation-center', ['session' => $session->id]) }}" wire:navigate class="block p-2.5 rounded-lg border border-gray-200 dark:border-white/10 hover:border-brand-300 dark:hover:border-brand-500/40">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ $session->displayTitle() }}</span>
                                                <span class="shrink-0 text-[10px] uppercase font-bold {{ $session->needs_review ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">{{ $session->status?->value ?? 'open' }}</span>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Projects</h3>
                            <a href="{{ route('project-hub') }}" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline">Hub</a>
                        </div>
                        <div class="p-6">
                            @forelse($projects as $project)
                                <a href="{{ route('project-hub', ['project' => $project->id]) }}" wire:navigate class="flex items-center justify-between p-3 mb-2 last:mb-0 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-brand-300 dark:hover:border-brand-500/40">
                                    <div>
                                        <h4 class="font-bold text-xs text-gray-900 dark:text-white">{{ $project->name }}</h4>
                                        <span class="text-[10px] text-gray-400 capitalize">{{ $project->status?->label() ?? $project->status }}</span>
                                    </div>
                                </a>
                            @empty
                                <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No projects for this customer.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="font-semibold text-gray-900 dark:text-white">Linked Issues</h3>
                        </div>
                        <div class="p-6">
                            @if($issues->isEmpty())
                                <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No linked issues.</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($issues as $iss)
                                        <div class="p-3 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700">
                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="font-bold text-xs text-gray-900 dark:text-white">{{ $iss->title }}</h4>
                                                <span class="shrink-0 text-[10px] bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-300 px-2 py-0.5 rounded font-medium">{{ $iss->status?->label() ?? $iss->status }}</span>
                                            </div>
                                            @if($iss->description)
                                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ $iss->description }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($stats['issue_total'] > $issues->count())
                                        <p class="text-[11px] text-gray-400">Showing {{ $issues->count() }} of {{ $stats['issue_total'] }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Linked Tasks</h3>
                        @if($stats['task_total'] > 0)
                            <a href="{{ route('task-dashboard') }}" class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline">View all</a>
                        @endif
                    </div>
                    <div class="p-6">
                        @if($tasks->isEmpty())
                            <p class="text-xs text-gray-500 dark:text-gray-400 py-3">No tasks generated for this customer.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($tasks as $task)
                                    <a href="{{ route('task-detail', $task->id) }}" wire:navigate class="p-3 bg-gray-50 dark:bg-gray-800/60 rounded-xl border {{ $task->isOverdue() ? 'border-red-200 dark:border-red-800/60' : 'border-gray-200 dark:border-gray-700' }} flex justify-between items-center hover:border-brand-300 dark:hover:border-brand-500/40">
                                        <div class="min-w-0 pr-3">
                                            <h4 class="font-bold text-xs text-gray-900 dark:text-white truncate">{{ $task->title }}</h4>
                                            <span class="text-[10px] {{ $task->dueDateToneClasses('text') }}">
                                                {{ $task->project?->name ? $task->project->name.' · ' : '' }}
                                                {{ $task->due_date ? (match ($task->dueUrgency()) {
                                                    'overdue' => 'Overdue '.$task->due_date->format('M d'),
                                                    'today' => 'Today '.$task->due_date->format('M d'),
                                                    'tomorrow' => 'Tomorrow '.$task->due_date->format('M d'),
                                                    default => 'Due '.$task->due_date->format('M d'),
                                                }) : 'No due date' }}
                                            </span>
                                        </div>
                                        <span class="shrink-0 text-[10px] bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300 px-2 py-1 rounded font-bold">{{ $task->status?->label() ?? 'To Do' }}</span>
                                    </a>
                                @endforeach
                                @if($stats['task_total'] > $tasks->count())
                                    <p class="text-[11px] text-gray-400">Showing {{ $tasks->count() }} of {{ $stats['task_total'] }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-800 p-12 rounded-2xl border border-gray-200 dark:border-gray-700 text-center text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-user-group class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                    <h3 class="font-bold text-base text-gray-900 dark:text-white mb-1">No Customer Selected</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mx-auto">Select an account from the sidebar or click "New Customer" to create an account.</p>
                </div>
            @endif
        </div>
    </div>

    <x-ui.slide-form-modal entangle="showCreateModal" loading-target="openCreateModal" title="New Customer" description="Add a customer account to CRM." close-method="$set('showCreateModal', false)" size="sm">
        <form id="modal-create-customer" wire:submit="newCustomer" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                <input type="text" wire:model="formName" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                @error('formName')<p class="mt-1 text-sm text-error-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                <input type="email" wire:model="formEmail" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Phone</label>
                <input type="text" wire:model="formPhone" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Daily AI limit (USD)</label>
                <input type="number" step="0.01" min="0" wire:model="formDailyAiCostLimit" placeholder="Unlimited" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                <p class="mt-1 text-xs text-gray-500">Leave blank for unlimited (or the workspace default).</p>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-create-customer" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Create</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

    <x-ui.slide-form-modal entangle="showEditModal" loading-target="openEditModal" title="Edit Account" description="Update customer contact details." close-method="$set('showEditModal', false)" size="sm">
        <form id="modal-edit-customer" wire:submit="editCustomer" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                <input type="text" wire:model="formName" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                <input type="email" wire:model="formEmail" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Phone</label>
                <input type="text" wire:model="formPhone" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Daily AI limit (USD)</label>
                <input type="number" step="0.01" min="0" wire:model="formDailyAiCostLimit" placeholder="Unlimited" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                <p class="mt-1 text-xs text-gray-500">Leave blank for unlimited (or the workspace default).</p>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showEditModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-edit-customer" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Save</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>
</div>
