<div>
    <x-common.page-breadcrumb pageTitle="Rules Center">
        <x-slot:subtitle>Automation rules for work and messaging</x-slot:subtitle>
        <x-slot:actions>
            <x-ui.button wire:click="openCreateModal">
                <x-heroicon-m-plus class="h-4 w-4"/> New Rule
            </x-ui.button>
        </x-slot:actions>
    </x-common.page-breadcrumb>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200/80 dark:border-white/10 ring-1 ring-gray-950/5 dark:ring-white/10 shadow-sm flex justify-between items-center">
            <div>
                <h3 class="font-bold text-lg text-gray-900 dark:text-white">Business Rules & Automation Engine</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Automated event-driven rules, trigger conditions, and execution history.</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Active Automation Rules</h3>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse($rules as $rule)
                    <div class="py-3 flex justify-between items-center text-xs">
                        <div class="space-y-1">
                            <h4 class="font-bold text-gray-900 dark:text-white text-sm">{{ $rule->name }}</h4>
                            <div class="flex items-center gap-3 text-gray-500 dark:text-gray-400">
                                <span>Trigger: <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-[11px]">{{ $rule->event_name }}</code></span>
                                @if(!empty($rule->conditions['field']))
                                    <span>• If <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-[11px]">{{ $rule->conditions['field'] }}</code> {{ $rule->conditions['operator'] ?? '=' }} "{{ $rule->conditions['value'] ?? '' }}"</span>
                                @else
                                    <span>• Always matches</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="toggleRuleStatus({{ $rule->id }})" class="px-3 py-1 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {{ $rule->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40 hover:bg-emerald-100' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-200' }}" title="Click to toggle status">
                                <span class="w-2 h-2 rounded-full {{ $rule->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                {{ $rule->is_active ? 'Active' : 'Disabled' }}
                            </button>
                            <x-ui.confirm-button
                                heading="Delete this rule?"
                                message="The automation will stop running."
                                confirm-label="Delete"
                                method="deleteRule"
                                :params="[$rule->id]"
                                variant="danger"
                                size="icon"
                                title="Delete Rule"
                                aria-label="Delete Rule"
                            >
                                <x-heroicon-m-trash class="h-4 w-4"/>
                            </x-ui.confirm-button>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-xs text-gray-500 dark:text-gray-400">
                        No automation rules registered. Click "+ New Automation Rule" above to create one.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <x-ui.slide-form-modal :show="$showCreateModal" title="New Automation Rule" description="Create a trigger-based automation for workspace events." close-method="$set('showCreateModal', false)" size="md">
        <form id="modal-create-rule" wire:submit="createRule" class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                <input type="text" wire:model="name" placeholder="e.g. Auto-Assign Urgent Triage" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                @error('name') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Event Trigger</label>
                    <select wire:model="event_trigger" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="CommunicationCreated">Inbound Customer Message</option>
                        <option value="TaskCreated">Task Created</option>
                        <option value="TaskStateChanged">Task State Changed</option>
                        <option value="IssueStateChanged">Issue State Changed</option>
                        <option value="SystemHealthDegraded">System Health Degraded</option>
                    </select>
                    @error('event_trigger') <p class="mt-1 text-sm text-error-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Active Status</label>
                    <select wire:model="is_active" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="1">Active</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Match value (leave blank to always match)</label>
                <textarea wire:model="description" rows="3" placeholder="e.g. 'urgent' — the rule fires when this trigger's default field contains this text" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-create-rule" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Create Rule</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>
</div>
