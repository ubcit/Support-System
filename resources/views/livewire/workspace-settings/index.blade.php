<div>
    <x-common.page-breadcrumb pageTitle="Workspace Settings">
        <x-slot:subtitle>Branding, roles, and permissions</x-slot:subtitle>
        <x-slot:actions>
            <button wire:click="saveSettings" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                <x-heroicon-m-check class="w-4 h-4"/> Save Settings
            </button>
        </x-slot:actions>
    </x-common.page-breadcrumb>
    
    <div class="space-y-6">
        <!-- Branding & Configuration -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Workspace Branding & Identity</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Workspace Name</label>
                        <input type="text" wire:model="workspaceName" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                        @error('workspaceName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp Cloud API Phone</label>
                        <input type="text" wire:model="whatsappPhone" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white font-mono" required>
                        @error('whatsappPhone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">System Integration Status</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">Read-only — these are configured via server environment variables, not this page.</p>
                <div class="space-y-4">
                    @foreach ([
                        ['OpenAI API Key', config('services.openai.key')],
                        ['Groq API Key', config('services.groq.key')],
                        ['Gemini API Key', config('services.gemini.key')],
                    ] as [$label, $key])
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
                            @if(!empty($key))
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-bold rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">Configured</span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-bold rounded bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400">Not configured</span>
                            @endif
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Storage Disk</span>
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-bold rounded bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300 font-mono">{{ config('filesystems.default') }}</span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 md:col-span-2">
                <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Advanced Settings</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AI Provider</label>
                        <select wire:model="aiProvider" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                            <option value="openai">OpenAI</option>
                            <option value="gemini">Gemini</option>
                            <option value="groq">Groq</option>
                            <option value="anthropic">Anthropic</option>
                            <option value="mock">Mock (Testing)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Which AI provider to use for conversation analysis. <a href="{{ route('ai.models.index') }}" class="text-brand-500 hover:underline">Manage models</a></p>
                        @error('aiProvider') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Customer Cooldown Minutes</label>
                        <input type="number" wire:model="customerCooldownMinutes" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required min="0">
                        @error('customerCooldownMinutes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Boss Cooldown Minutes</label>
                        <input type="number" wire:model="bossCooldownMinutes" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required min="0">
                        @error('bossCooldownMinutes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2 flex items-start justify-between gap-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 dark:text-white">Auto-create tasks from known customers</label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">After the customer cooldown, known-customer bursts become tasks when AI confidence is at least the value below. Employees still get the full customer messages and attachments.</p>
                        </div>
                        <input type="checkbox" wire:model="autoCreateTasks" class="mt-1 rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-800 w-5 h-5 cursor-pointer">
                    </div>
                    <div class="md:col-span-2 flex items-start justify-between gap-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 dark:text-white">Auto-reply on WhatsApp when work is done</label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">When every task in a customer session is completed, send a WhatsApp message telling them the work is ready.</p>
                        </div>
                        <input type="checkbox" wire:model="autoReplyOnCompletion" class="mt-1 rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-800 w-5 h-5 cursor-pointer">
                    </div>
                    <div class="md:col-span-2 flex items-start justify-between gap-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 dark:text-white">Auto-reply when a session starts processing</label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">After the customer cooldown finishes and AI runs, send a bilingual WhatsApp message that their request is being processed.</p>
                        </div>
                        <input type="checkbox" wire:model="autoReplyOnProcessing" class="mt-1 rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-800 w-5 h-5 cursor-pointer">
                    </div>
                    <div class="md:col-span-2 flex items-start justify-between gap-4 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-900 dark:text-white">Notify staff when a session is ready</label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Email project members first, then WhatsApp them as a backup. Falls back to all employees with a phone or email if the project has no members.</p>
                        </div>
                        <input type="checkbox" wire:model="staffNotifyOnSession" class="mt-1 rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-800 w-5 h-5 cursor-pointer">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Minimum AI confidence (0–1)</label>
                        <input type="number" step="0.05" min="0" max="1" wire:model="autoCreateMinConfidence" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                        @error('autoCreateMinConfidence') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Default daily AI cost limit (USD)</label>
                        <input type="number" step="0.01" min="0" wire:model="defaultDailyAiCostLimit" placeholder="Unlimited" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Used when a customer has no custom cap. Empty means unlimited. <a href="{{ route('customer-ai-limits') }}" class="text-brand-500 hover:underline">Per-customer limits</a></p>
                        @error('defaultDailyAiCostLimit') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Permissions Matrix -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Role-Based Access Control (RBAC) Matrix</h3>
            
            <div x-data="{
                rolePermissions: $wire.entangle('rolePermissions', true),
                permissionSlugs: {{ json_encode(array_keys($permissions)) }},
                toggleRole(roleId) {
                    if (!this.rolePermissions[roleId]) {
                        this.rolePermissions[roleId] = {};
                    }
                    let allChecked = this.permissionSlugs.every(slug => Boolean(this.rolePermissions[roleId][slug]));
                    let targetState = !allChecked;
                    this.permissionSlugs.forEach(slug => {
                        this.rolePermissions[roleId][slug] = targetState;
                    });
                },
                isRoleAllChecked(roleId) {
                    if (!this.rolePermissions[roleId]) return false;
                    return this.permissionSlugs.every(slug => Boolean(this.rolePermissions[roleId][slug]));
                }
            }" class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 uppercase text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 font-bold">
                        <tr>
                            <th class="p-4">Permission</th>
                            @foreach($roles as $role)
                                <th class="p-4 text-center border-l border-gray-200 dark:border-gray-700">
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="text-gray-900 dark:text-white">{{ $role->name }}</span>
                                        <label class="flex items-center gap-1.5 text-[10px] font-normal cursor-pointer text-brand-500 hover:text-brand-600 uppercase tracking-wider">
                                            <input type="checkbox" 
                                                :checked="isRoleAllChecked({{ $role->id }})" 
                                                @change="toggleRole({{ $role->id }})" 
                                                class="rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-800 w-4 h-4 cursor-pointer">
                                            <span>All</span>
                                        </label>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($permissions as $slug => $label)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/50 transition">
                                <td class="p-4 font-medium text-gray-900 dark:text-white">
                                    {{ $label }} 
                                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400 block sm:inline sm:ml-2 font-normal">({{ $slug }})</span>
                                </td>
                                @foreach($roles as $role)
                                    <td class="p-4 text-center border-l border-gray-200 dark:border-gray-700">
                                        <input type="checkbox" 
                                            x-model="rolePermissions[{{ $role->id }}]['{{ $slug }}']"
                                            class="rounded border-gray-300 dark:border-gray-600 text-brand-600 focus:ring-brand-500 dark:bg-gray-800 w-5 h-5 cursor-pointer">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
