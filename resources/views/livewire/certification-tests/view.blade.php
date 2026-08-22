<div>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-title-md2 font-semibold text-black dark:text-white">
            View Certification Test
        </h2>
        <nav>
            <ol class="flex items-center gap-2">
                <li><a class="font-medium hover:text-brand-500" href="{{ route('dashboard') }}">Dashboard /</a></li>
                <li><a class="font-medium hover:text-brand-500" href="{{ route('certification-tests.index') }}">Certification Tests /</a></li>
                <li class="font-medium text-brand-500">View</li>
            </ol>
        </nav>
    </div>

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
            <div x-show="activeTab === 'details'" class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Name</label>
                    <p class="text-base text-gray-800 dark:text-white/90 font-medium">{{ $test->name }}</p>
                </div>
                
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Category</label>
                    <div>
                        @php
                            $colors = [
                                'functional' => 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400',
                                'chaos' => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400',
                                'golden' => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400',
                                'regression' => 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-400',
                                'performance' => 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400',
                            ];
                            $color = $colors[$test->category] ?? 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
                        @endphp
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $color }}">{{ ucfirst($test->category) }}</span>
                    </div>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Description</label>
                    <p class="text-base text-gray-800 dark:text-white/90">{{ $test->description ?? '-' }}</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Tags</label>
                    <div class="flex flex-wrap gap-2">
                        @if($test->tags)
                            @foreach($test->tags as $tag)
                                <span class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $tag }}</span>
                            @endforeach
                        @else
                            <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Golden Test</label>
                    <div>
                        @if($test->is_golden)
                            <span class="inline-flex rounded-full bg-warning-50 px-2.5 py-0.5 text-sm font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">Yes</span>
                        @else
                            <span class="inline-flex rounded-full bg-gray-50 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-400">No</span>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                    <div>
                        @if($test->is_active)
                            <span class="inline-flex rounded-full bg-success-50 px-2.5 py-0.5 text-sm font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">Active</span>
                        @else
                            <span class="inline-flex rounded-full bg-error-50 px-2.5 py-0.5 text-sm font-medium text-error-700 dark:bg-error-500/10 dark:text-error-400">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Inputs Tab -->
            <div x-show="activeTab === 'inputs'" style="display: none;" class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                @if($test->input)
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Customer Phone</label>
                        <p class="text-base text-gray-800 dark:text-white/90">{{ $test->input->customer_phone ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Project Name</label>
                        <p class="text-base text-gray-800 dark:text-white/90">{{ $test->input->project_name ?? '-' }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Boss Notes</label>
                        <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800">
                            <pre class="whitespace-pre-wrap font-sans text-sm text-gray-700 dark:text-gray-300">{{ $test->input->boss_notes ?? 'None' }}</pre>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Customer Message</label>
                        <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800">
                            <pre class="whitespace-pre-wrap font-sans text-sm text-gray-700 dark:text-gray-300">{{ is_array($test->input->messages) ? (array_is_list($test->input->messages) ? implode("\n", $test->input->messages) : json_encode($test->input->messages, JSON_PRETTY_PRINT)) : ($test->input->messages ?? 'None') }}</pre>
                        </div>
                    </div>
                @else
                    <div class="col-span-2 text-center text-gray-500 py-4">No inputs defined.</div>
                @endif
            </div>

            <!-- Expectations Tab -->
            <div x-show="activeTab === 'expectations'" style="display: none;" class="grid grid-cols-1 gap-5">
                @if($test->expectation)
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-500 dark:text-gray-400">Expected JSON output from AI</label>
                        <div class="rounded-lg bg-gray-50 p-4 border border-gray-100 dark:bg-gray-800/50 dark:border-gray-800 max-h-96 overflow-y-auto custom-scrollbar">
                            <pre class="whitespace-pre-wrap font-mono text-sm text-gray-700 dark:text-gray-300">{{ is_array($test->expectation->expected_data) ? json_encode($test->expectation->expected_data, JSON_PRETTY_PRINT) : $test->expectation->expected_data }}</pre>
                        </div>
                    </div>
                @else
                    <div class="text-center text-gray-500 py-4">No expectations defined.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 flex justify-end gap-3">
        <a href="{{ route('certification-tests.index') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-center font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
            Back to List
        </a>
        <a href="{{ route('certification-tests.edit', $test) }}" class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-6 py-2.5 text-center font-medium text-white hover:bg-opacity-90">
            Edit Test
        </a>
    </div>
</div>
