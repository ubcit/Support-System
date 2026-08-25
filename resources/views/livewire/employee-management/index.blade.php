<div>
    <x-common.page-breadcrumb pageTitle="Team">
        <x-slot:subtitle>Directory, roles, and active workload</x-slot:subtitle>
        <x-slot:actions>
            @if($canManage)
            <x-ui.button @click="$wire.showCreateModal = true; $wire.openCreateModal()">
                <x-heroicon-m-plus class="h-4 w-4"/> Hire Employee
            </x-ui.button>
            @endif
        </x-slot:actions>
    </x-common.page-breadcrumb>
    <div class="relative grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-ui.content-loading />
        <!-- Employee Directory List -->
        <div class="space-y-3 lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="font-bold text-sm text-gray-900 dark:text-white">Employee Directory</h3>
                <span class="text-xs px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded font-mono">{{ $employees->count() }} Team Members</span>
            </div>

            <div class="space-y-2">
                @foreach($employees as $emp)
                    <button wire:click="selectEmployee({{ $emp->id }})" 
                        class="w-full text-left p-4 rounded-xl border transition flex items-center justify-between {{ $selected_employee?->id === $emp->id ? 'bg-indigo-50 border-indigo-300 dark:bg-indigo-950/40 dark:border-indigo-800' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                        <div class="flex items-center gap-3">
                            <x-ui.person-avatar :person="$emp" size="xl" />
                            <div>
                                <h4 class="font-bold text-sm text-gray-900 dark:text-white">{{ $emp->name }}</h4>
                                <span class="text-xs text-gray-500">{{ $emp->role }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-mono font-bold text-indigo-600 dark:text-indigo-400 block">{{ $emp->active_tasks_count }} Tasks</span>
                            <span class="inline-block w-2 h-2 rounded-full {{ $emp->is_available ? 'bg-emerald-500' : 'bg-gray-400 dark:bg-gray-600' }} mt-1" title="{{ $emp->is_available ? 'Available' : 'Unavailable' }}"></span>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Selected Employee Detail Management Panel -->
        <div class="lg:col-span-2 space-y-6">
            @if($selected_employee)
                <!-- Header Banner -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 space-y-4">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-4">
                            <x-ui.person-avatar :person="$selected_employee" size="2xl" class="rounded-2xl" />
                            <div>
                                <h2 class="text-xl font-black text-gray-900 dark:text-white">{{ $selected_employee->name }}</h2>
                                <p class="text-xs text-gray-500 font-mono">{{ $selected_employee->email }} • {{ $selected_employee->role }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    @if($selected_employee->is_available)
                                        <span class="px-2 py-0.5 text-[10px] uppercase font-bold rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">● Available</span>
                                    @else
                                        <span class="px-2 py-0.5 text-[10px] uppercase font-bold rounded bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400">● Unavailable</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-2">
                                    <span class="text-xs font-semibold text-gray-500 mr-1">Skills:</span>
                                    @forelse($selected_employee->skills as $sk)
                                        <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-medium">
                                            {{ $sk->skill }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-gray-400 italic">No skills added yet.</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if($canManage)
                            <x-ui.button variant="outline" size="icon" @click="$wire.showEditModal = true; $wire.openEditModal()" title="Edit profile" aria-label="Edit profile">
                                <x-heroicon-m-pencil-square class="h-4 w-4"/>
                            </x-ui.button>
                            <x-ui.confirm-button
                                heading="Offboard this employee?"
                                message="They will be removed from the workspace roster."
                                confirm-label="Offboard"
                                method="deleteEmployee"
                                :params="[$selected_employee->id]"
                                variant="danger"
                                size="icon"
                                title="Offboard Employee"
                                aria-label="Offboard Employee"
                            >
                                <x-heroicon-m-user-minus class="h-4 w-4"/>
                            </x-ui.confirm-button>
                            @endif
                        </div>
                    </div>

                    <!-- Metrics bar -->
                    <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-100 dark:border-gray-700 text-center">
                        <div>
                            <span class="text-xs text-gray-500 uppercase font-semibold">Active Tasks</span>
                            <div class="text-lg font-bold text-gray-900 dark:text-white mt-0.5">{{ $active_tasks->count() }}</div>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500 uppercase font-semibold">Completed</span>
                            <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ $completed_tasks->count() }}</div>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500 uppercase font-semibold">Workload Capacity</span>
                            <div class="text-sm font-bold text-indigo-600 dark:text-indigo-400 mt-0.5 font-mono">
                                {{ $active_tasks->count() }} / {{ $selected_employee->max_workload ?? 5 }} Tasks
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs & Task Management -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Current Active Workload</h3>
                    </div>
                    <div class="p-6">
                        @if($active_tasks->isEmpty())
                            <p class="text-xs text-gray-500 py-3">No active tasks assigned.</p>
                        @else
                            <div class="space-y-3">
                                @foreach($active_tasks as $t)
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex justify-between items-center">
                                        <div>
                                            <h4 class="font-bold text-xs text-gray-900 dark:text-white">{{ $t->title }}</h4>
                                            <span class="text-[11px] text-gray-500">Project: {{ $t->project?->name ?? 'General' }}</span>
                                        </div>
                                        <span class="text-xs font-mono font-semibold px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded">
                                            {{ $t->priority?->value ?? 'medium' }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Recent Time Tracking & AI Summary</h3>
                    </div>
                    <div class="p-6">
                        <div class="p-3 bg-indigo-50 dark:bg-indigo-950/30 rounded-xl border border-indigo-200 dark:border-indigo-800 text-xs text-indigo-900 dark:text-indigo-200">
                            <strong class="block mb-1 font-bold">AI Employee Performance Summary</strong>
                            {{ $selected_employee->name }} — {{ $selected_employee->role }} in {{ $selected_employee->department ?: 'General' }}. Active workload: {{ $active_tasks->count() }} tasks.
                        </div>
                    </div>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center p-6 text-center text-gray-500 dark:text-gray-400 h-full">
                    <x-heroicon-o-users class="w-16 h-16 mb-4 text-gray-300 dark:text-gray-500"/>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">No Employee Selected</h3>
                    <p class="text-sm mt-1">Select an employee from the directory to manage their profile and workload.</p>
                </div>
            @endif
        </div>
    </div>

    <x-ui.slide-form-modal entangle="showCreateModal" loading-target="openCreateModal" title="Hire Employee" description="Create an employee profile and availability settings." close-method="$set('showCreateModal', false)" size="md">
        <form id="modal-hire-employee" wire:submit="hireEmployee" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                    <input wire:model="formName" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                    <input type="email" wire:model="formEmail" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">System Role</label>
                    <select wire:model="formSystemRole" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        @foreach ($systemRoles as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Job Title <span class="font-normal text-gray-400">(optional)</span></label>
                    <input wire:model="formJobTitle" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Department</label>
                    <input wire:model="formDepartment" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Max Workload</label>
                    <input type="number" wire:model="formMaxWorkload" min="1" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Skills</label>
                <input wire:model="formSkills" placeholder="comma-separated" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" wire:model="formIsAvailable" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" /> Available for Work</label>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-hire-employee" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Hire</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

    <x-ui.slide-form-modal entangle="showEditModal" loading-target="openEditModal" title="Edit Profile" description="Update employee details and workload capacity." close-method="$set('showEditModal', false)" size="md">
        <form id="modal-edit-employee" wire:submit="editEmployee" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name</label>
                    <input wire:model="formName" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Email</label>
                    <input type="email" wire:model="formEmail" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">System Role</label>
                    <select wire:model="formSystemRole" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        @foreach ($systemRoles as $slug => $label)
                            <option value="{{ $slug }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Job Title <span class="font-normal text-gray-400">(optional)</span></label>
                    <input wire:model="formJobTitle" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Department</label>
                    <input wire:model="formDepartment" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Max Workload</label>
                    <input type="number" wire:model="formMaxWorkload" min="1" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Skills</label>
                <input wire:model="formSkills" placeholder="comma-separated" class="h-11 w-full rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"><input type="checkbox" wire:model="formIsAvailable" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" /> Available for Work</label>
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showEditModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-edit-employee" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Save</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>
</div>