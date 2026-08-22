<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            Create Certification Test
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="{{ route('dashboard') }}">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('certification-tests.index') }}">Certification Tests /</a></li>
                <li class="font-medium text-brand-500">Create</li>
            </ol>
        </nav>
    </div>

    <form wire:submit="save">
        <div x-data="{ activeTab: 'details' }" class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            
            <!-- Tabs Navigation -->
            <div class="flex border-b border-gray-200 px-5 pt-4 dark:border-gray-800 gap-6">
                <button type="button" @click="activeTab = 'details'" :class="activeTab === 'details' ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" class="pb-3 border-b-2 font-medium text-sm transition">
                    Test Details
                </button>
                <button type="button" @click="activeTab = 'inputs'" :class="activeTab === 'inputs' ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" class="pb-3 border-b-2 font-medium text-sm transition">
                    Inputs
                </button>
                <button type="button" @click="activeTab = 'expectations'" :class="activeTab === 'expectations' ? 'border-brand-500 text-brand-500' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'" class="pb-3 border-b-2 font-medium text-sm transition">
                    Expectations
                </button>
            </div>

            <div class="p-5">
                <!-- Test Details Tab -->
                <div x-show="activeTab === 'details'" class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Name <span class="text-error-500">*</span></label>
                        <input type="text" wire:model="name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('name') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Category <span class="text-error-500">*</span></label>
                        <select wire:model="category" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="functional">Functional</option>
                            <option value="chaos">Chaos</option>
                            <option value="golden">Golden</option>
                            <option value="regression">Regression</option>
                            <option value="performance">Performance</option>
                        </select>
                        @error('category') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="xl:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        @error('description') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="xl:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Tags (Comma separated)</label>
                        <input type="text" wire:model="tags" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" placeholder="e.g. invoice, core, critical" />
                        @error('tags') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-3">
                        <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                            <div class="relative">
                                <input type="checkbox" wire:model="is_golden" class="sr-only peer" />
                                <div class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-transparent peer-checked:border-brand-500 peer-checked:bg-brand-500 dark:border-gray-700">
                                    <span class="opacity-0 peer-checked:opacity-100">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            Golden Test
                        </label>

                        <label class="flex cursor-pointer items-center text-sm font-medium text-gray-700 select-none dark:text-gray-400">
                            <div class="relative">
                                <input type="checkbox" wire:model="is_active" class="sr-only peer" />
                                <div class="mr-3 flex h-5 w-5 items-center justify-center rounded-md border-[1.25px] border-gray-300 bg-transparent peer-checked:border-brand-500 peer-checked:bg-brand-500 dark:border-gray-700">
                                    <span class="opacity-0 peer-checked:opacity-100">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M11.6666 3.5L5.24992 9.91667L2.33325 7" stroke="white" stroke-width="1.94437" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>
                                </div>
                            </div>
                            Is Active
                        </label>
                    </div>
                </div>

                <!-- Inputs Tab -->
                <div x-show="activeTab === 'inputs'" style="display: none;" class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Customer Phone</label>
                        <input type="text" wire:model="customer_phone" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('customer_phone') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Project Name</label>
                        <input type="text" wire:model="project_name" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                        @error('project_name') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Boss Notes</label>
                        <textarea wire:model="boss_notes" rows="4" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        @error('boss_notes') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Customer Message <span class="text-gray-400 font-normal ml-1">(Input raw JSON or text)</span></label>
                        <textarea wire:model="inputMessages" rows="4" class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        @error('inputMessages') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Expectations Tab -->
                <div x-show="activeTab === 'expectations'" style="display: none;" class="grid grid-cols-1 gap-5">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">Expected JSON output from AI</label>
                        <textarea wire:model="expected_data" rows="12" class="w-full font-mono rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"></textarea>
                        @error('expected_data') <span class="text-xs text-error-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('certification-tests.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-2.5 text-center font-medium text-white hover:bg-opacity-90">
                <span wire:loading.remove wire:target="save">Create Test</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </form>
</div>
