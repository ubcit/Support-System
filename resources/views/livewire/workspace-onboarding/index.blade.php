<div>
    <x-common.page-breadcrumb pageTitle="Workspace Onboarding" />

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8 max-w-3xl mx-auto">
        <!-- Progress Bar -->
        <div class="mb-8">
            <div class="flex justify-between mb-2">
                <span class="text-xs font-semibold text-brand-500">Step {{ $currentStep }} of 9</span>
                <span class="text-xs font-semibold text-gray-500">{{ round(($currentStep / 9) * 100) }}% Complete</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div class="bg-brand-500 h-2 rounded-full" style="width: {{ ($currentStep / 9) * 100 }}%"></div>
            </div>
        </div>

        <form wire:submit.prevent="nextStep">
            
            @if($currentStep === 1)
                <!-- Step 1: Create Workspace -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Create Workspace</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Identity, Branding, Time zone</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Name</label>
                        <input type="text" wire:model="company_name" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                        @error('company_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Time Zone</label>
                        <select wire:model="timezone" class="w-full rounded-lg border border-stroke bg-transparent py-2 pl-4 pr-10 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                            <option value="UTC">UTC</option>
                            <option value="Asia/Baghdad">Baghdad (AST)</option>
                            <option value="America/New_York">New York (EST)</option>
                        </select>
                        @error('timezone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Logo</label>
                        <input type="file" wire:model="logo" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                    </div>
                </div>
            @endif

            @if($currentStep === 2)
                <!-- Step 2: Team Setup -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Team Setup</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Owners, Admins, Employees</p>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 p-4 rounded-lg text-sm">
                        You will be set as the Owner. You can invite other team members later.
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Phone Number</label>
                        <input type="tel" wire:model="owner_phone" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                    </div>
                </div>
            @endif

            @if($currentStep === 3)
                <!-- Step 3: Projects -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Projects</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Initial scopes (Clinic ERP, Mobile App)</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Project Name</label>
                        <input type="text" wire:model="initial_project" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                    </div>
                </div>
            @endif

            @if($currentStep === 4)
                <!-- Step 4: Communication Channels -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Communication Channels</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">WhatsApp Cloud API, SMTP Email</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp Cloud Token</label>
                        <input type="password" wire:model="whatsapp_token" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">WhatsApp Phone Number ID</label>
                        <input type="text" wire:model="whatsapp_phone_id" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                    </div>
                </div>
            @endif

            @if($currentStep === 5)
                <!-- Step 5: AI Configuration -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">AI Configuration</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Select Provider and test limits</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AI Provider</label>
                        <select wire:model="ai_provider" class="w-full rounded-lg border border-stroke bg-transparent py-2 pl-4 pr-10 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                            <option value="gemini">Google Gemini</option>
                            <option value="openai">OpenAI (GPT-4o)</option>
                            <option value="anthropic">Anthropic (Claude 3.5)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Key</label>
                        <input type="password" wire:model="ai_api_key" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="enable_ai" id="enable_ai" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                        <label for="enable_ai" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Enable AI Analysis globally
                        </label>
                    </div>
                </div>
            @endif

            @if($currentStep === 6)
                <!-- Step 6: Task Provider -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Task Provider</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Native Work Platform</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Task Provider</label>
                        <select wire:model="task_provider" class="w-full rounded-lg border border-stroke bg-transparent py-2 pl-4 pr-10 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                            <option value="native">Native Work Platform (Single Source of Truth)</option>
                            <option value="external">Generic External Integration</option>
                        </select>
                    </div>
                </div>
            @endif

            @if($currentStep === 7)
                <!-- Step 7: Workflow Selection -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Workflow Selection</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Import templates</p>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Workflow Template</label>
                        <select wire:model="workflow_template" class="w-full rounded-lg border border-stroke bg-transparent py-2 pl-4 pr-10 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" required>
                            <option value="software">Software Development (To Do, In Progress, Review, Done)</option>
                            <option value="it_support">IT Support (New, Investigating, Waiting on Customer, Resolved)</option>
                            <option value="healthcare">Healthcare Clinic (Triage, Admitted, Discharged)</option>
                        </select>
                    </div>
                </div>
            @endif

            @if($currentStep === 8)
                <!-- Step 8: Automation -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Automation</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Toggle built-in business rules</p>
                    
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="rule_auto_assign" id="rule_auto_assign" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                        <label for="rule_auto_assign" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Auto-assign tasks based on skills
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="rule_notify_customer" id="rule_notify_customer" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                        <label for="rule_notify_customer" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Notify customer on completion
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="rule_escalate_overdue" id="rule_escalate_overdue" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                        <label for="rule_escalate_overdue" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Escalate overdue work to Boss
                        </label>
                    </div>
                </div>
            @endif

            @if($currentStep === 9)
                <!-- Step 9: Feature Flags -->
                <div class="space-y-4">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Feature Flags</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Enable/disable capabilities</p>
                    
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="flag_customer_portal" id="flag_customer_portal" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                        <label for="flag_customer_portal" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Customer Portal (Beta)
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="flag_advanced_analytics" id="flag_advanced_analytics" class="h-4 w-4 text-brand-600 focus:ring-brand-500 border-gray-300 rounded">
                        <label for="flag_advanced_analytics" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                            Advanced Analytics
                        </label>
                    </div>
                </div>
            @endif

            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700 flex justify-between">
                @if($currentStep > 1)
                    <button type="button" wire:click="previousStep" class="px-6 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 font-medium transition">
                        Back
                    </button>
                @else
                    <div></div>
                @endif
                
                @if($currentStep < 9)
                    <button type="submit" class="px-6 py-2 rounded-lg bg-brand-500 text-white hover:bg-brand-600 font-medium transition">
                        Next Step
                    </button>
                @else
                    <button type="button" wire:click="submit" class="px-6 py-2 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 font-medium transition flex items-center gap-2">
                        <x-heroicon-s-check-circle class="w-5 h-5"/> Go Live & Finalize Workspace
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
