<div>
    <x-common.page-breadcrumb pageTitle="Message Simulator" />

    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20 px-4 py-3 text-sm text-amber-900 dark:text-amber-100">
        Uses the <strong>real WhatsApp job pipeline</strong> (same path as the Meta webhook). Keep
        <code class="text-xs bg-white/60 dark:bg-black/30 px-1 rounded">php artisan queue:work</code>
        running. Production AI default is <strong>Gemini</strong> via workspace settings.
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-5">
        {{-- Scenario presets --}}
        <div>
            <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-2">Scenario presets</h3>
            <div class="flex flex-wrap gap-2">
                @foreach([
                    'unknown' => 'Unknown number',
                    'project_code' => 'Project code',
                    'known' => 'Known number',
                    'boss' => 'Boss message',
                    'task_created' => 'Task created',
                    'ai_error' => 'AI error',
                    'boss_notes_project' => 'Boss notes + project',
                ] as $key => $label)
                    <button
                        type="button"
                        wire:click="loadScenario('{{ $key }}')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition
                            {{ $active_scenario === $key
                                ? 'bg-emerald-500 text-white border-emerald-500'
                                : 'bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-600 hover:border-emerald-400' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Project code flow: load <em>Unknown number</em> → Send Real Workflow → load <em>Project code</em> (same phone) → Send again → then send a real request with Known/Unknown as needed.
            </p>
        </div>

        {{-- Demo checklist --}}
        <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-2">Demo checklist</h3>
            <ul class="grid sm:grid-cols-2 gap-1.5 text-xs text-gray-700 dark:text-gray-300">
                @foreach($demoChecklist as $item)
                    <li class="flex items-start gap-2">
                        <span class="mt-0.5 text-emerald-500">✓</span>
                        <span>
                            {{ $item['label'] }}
                            @if($item['scenario'])
                                <button type="button" wire:click="loadScenario('{{ $item['scenario'] }}')" class="text-brand-600 dark:text-brand-400 underline ml-1">load</button>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2 text-xs text-gray-500">After tasks appear: Conversation Center, Task Dashboard, employee <code>/workspace</code>. Use “Mark session tasks Done” to test completion auto-reply.</p>
        </div>

        <form wire:submit.prevent="sendRealWorkflow" class="space-y-5">
            {{-- Real workflow controls --}}
            <div class="bg-emerald-50/60 dark:bg-emerald-900/10 p-4 rounded-lg border border-emerald-200 dark:border-emerald-800">
                <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-3">Real workflow controls</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sender mode</label>
                        <select wire:model="sender_mode" class="w-full rounded-lg border border-stroke bg-white dark:bg-form-input py-2 pl-4 pr-10 text-gray-900 dark:text-white">
                            <option value="unknown">Unknown (needs project code)</option>
                            <option value="known">Known verified customer</option>
                            <option value="boss">Boss / manager phone</option>
                        </select>
                    </div>
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AI Provider (sim override)</label>
                        <select wire:model="ai_provider" class="w-full rounded-lg border border-stroke bg-white dark:bg-form-input py-2 pl-4 pr-10 text-gray-900 dark:text-white">
                            <option value="mock">Mock (local / offline)</option>
                            <option value="gemini">Google Gemini</option>
                            <option value="openai">OpenAI / Groq</option>
                        </select>
                    </div>
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Project (link / AI hint)</label>
                        <x-form.select.searchable
                            wire:model="project"
                            :options="$projects->mapWithKeys(fn ($p) => [$p->name => $p->name.($p->code ? ' ('.$p->code.')' : '')])->all()"
                            placeholder="Auto-detect from message"
                            empty-option="Auto-detect from message"
                            search-placeholder="Search projects..."
                        />
                    </div>
                    <div class="lg:col-span-3 flex flex-col gap-2 justify-end pb-1">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-900 dark:text-gray-300">
                            <input type="checkbox" wire:model="instant_ai" id="instant_ai" class="h-4 w-4 text-brand-600 border-gray-300 rounded">
                            Instant AI (0s cooldown)
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-900 dark:text-gray-300">
                            <input type="checkbox" wire:model="force_ai_error" id="force_ai_error" class="h-4 w-4 text-red-600 border-gray-300 rounded">
                            Force AI error
                        </label>
                    </div>
                </div>
            </div>

            {{-- Simulated Incoming Message --}}
            <div>
                <h3 class="font-semibold text-sm text-gray-900 dark:text-white mb-3">Simulated incoming WhatsApp message</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sender Phone</label>
                        <input type="text" wire:model="customer_phone" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                        @error('customer_phone')
                            <div class="mt-1 text-xs text-red-500">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sender Name</label>
                        <input type="text" wire:model="sender_name" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                        @error('sender_name')
                            <div class="mt-1 text-xs text-red-500">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="lg:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Boss Notes (stored on customer metadata for known senders)</label>
                        <textarea wire:model="boss_notes" rows="2" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white" placeholder="Assign to Ahmed. Project: Acme Portal."></textarea>
                        <p class="mt-1 text-xs text-gray-500">Injected into the AI prompt as <code>@{{boss_notes}}</code>. Name the project in notes and/or the message so matching works.</p>
                    </div>

                    <div class="lg:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message Content</label>
                        <textarea wire:model="customer_message" rows="3" class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white"></textarea>
                        @error('customer_message')
                            <div class="mt-1 text-xs text-red-500">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="lg:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Simulated Attachments</label>
                        <input type="file" wire:model="attachments" multiple class="w-full rounded-lg border border-stroke bg-transparent py-2 px-4 outline-none focus:border-brand-500 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                        <div wire:loading wire:target="attachments" class="mt-2 flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin text-brand-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span class="text-xs font-medium text-brand-600 dark:text-brand-400 animate-pulse">Uploading files...</span>
                        </div>
                        <div wire:loading.remove wire:target="attachments">
                            @if(!empty($attachments))
                                <div class="mt-2 space-y-1">
                                    @foreach($attachments as $file)
                                        <div class="flex items-center gap-2 p-1.5 rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 text-xs">
                                            <x-heroicon-s-check-circle class="w-4 h-4 text-emerald-500 shrink-0"/>
                                            <span class="text-gray-800 dark:text-gray-200 truncate flex-1">{{ $file->getClientOriginalName() }}</span>
                                            <span class="text-gray-400 font-mono text-[10px] shrink-0">{{ number_format($file->getSize() / 1024, 1) }} KB</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Images/audio go through real attachment jobs locally.</p>
                    </div>
                </div>
            </div>

            {{-- Primary actions --}}
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-3">
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendRealWorkflow,attachments" class="justify-center rounded-lg bg-emerald-500 px-5 py-3 font-medium text-white hover:bg-emerald-600 disabled:opacity-50 flex items-center gap-2">
                        <x-heroicon-s-bolt class="w-5 h-5" wire:loading.remove wire:target="sendRealWorkflow"/>
                        <svg wire:loading wire:target="sendRealWorkflow" class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        <span wire:loading.remove wire:target="sendRealWorkflow">Send Real Workflow</span>
                        <span wire:loading wire:target="sendRealWorkflow">Dispatching...</span>
                    </button>
                    <button type="button" wire:click="refreshWorkflowResults" class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Refresh results
                    </button>
                    <button type="button" wire:click="markLatestSessionTasksDone" class="rounded-lg border border-violet-300 dark:border-violet-700 px-4 py-3 text-sm font-medium text-violet-800 dark:text-violet-200 hover:bg-violet-50 dark:hover:bg-violet-900/30">
                        Mark session tasks Done
                    </button>
                </div>
            </div>

            {{-- Legacy / certification (collapsed) --}}
            <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                <button type="button" wire:click="$toggle('show_legacy')" class="w-full text-left px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300 flex items-center justify-between">
                    <span>Legacy / certification tools (E2E inline, Pipeline)</span>
                    <span class="text-xs">{{ $show_legacy ? 'Hide' : 'Show' }}</span>
                </button>
                @if($show_legacy)
                    <div class="px-4 pb-4 space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                            <div class="lg:col-span-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Load Certification Test</label>
                                <x-form.select.searchable
                                    wire:model.live="certification_test_id"
                                    :options="$certificationTests->pluck('name', 'id')->toArray()"
                                    placeholder="Select a test"
                                    empty-option="Select a test"
                                    search-placeholder="Search tests..."
                                />
                            </div>
                            <div class="lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">AI Provider (legacy E2E)</label>
                                <select wire:model="ai_provider" class="w-full rounded-lg border border-stroke bg-white dark:bg-form-input py-2 pl-4 pr-10 text-gray-900 dark:text-white">
                                    <option value="mock">Mock</option>
                                    <option value="gemini">Google Gemini</option>
                                    <option value="openai">OpenAI / Groq</option>
                                </select>
                            </div>
                            <div class="lg:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Workspace</label>
                                <select wire:model="workspace" class="w-full rounded-lg border border-stroke bg-transparent py-2 pl-4 pr-10 dark:border-form-strokedark dark:bg-form-input text-gray-900 dark:text-white">
                                    @foreach($workspaces as $ws)
                                        <option value="{{ $ws->name }}">{{ $ws->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="lg:col-span-3 flex items-center h-[42px] gap-2">
                                <input type="checkbox" wire:model="chaos_mode" id="chaos_mode" class="h-4 w-4 text-brand-600 border-gray-300 rounded">
                                <label for="chaos_mode" class="text-sm text-gray-900 dark:text-gray-300">Chaos mode</label>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button type="button" wire:click="simulateE2E" wire:loading.attr="disabled" class="rounded bg-sky-500 p-3 font-medium text-white hover:bg-sky-600 disabled:opacity-50">
                                Simulate E2E (inline)
                            </button>
                            <button type="button" wire:click="runPipeline" wire:loading.attr="disabled" class="rounded bg-brand-500 p-3 font-medium text-white hover:bg-brand-600 disabled:opacity-50">
                                Run Pipeline & AI
                            </button>
                            <button type="button" wire:click="runWithoutAi" wire:loading.attr="disabled" class="rounded bg-amber-500 p-3 font-medium text-white hover:bg-amber-600 disabled:opacity-50">
                                Run Without AI
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">These do <strong>not</strong> use the production WhatsApp job path. Prefer Send Real Workflow for delivery demos.</p>
                    </div>
                @endif
            </div>
        </form>
    </div>

    {{-- Real workflow results --}}
    @if($workflowResults)
        <div class="mt-6 space-y-4" wire:poll.5s="refreshWorkflowResults">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-xl font-bold dark:text-white flex items-center gap-2">
                    <x-heroicon-o-queue-list class="w-6 h-6 text-emerald-500" />
                    Real workflow results
                </h2>
                <span class="text-xs text-gray-500">Auto-refresh · last {{ $workflowResults['refreshed_at'] ?? '' }}</span>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <p class="text-xs text-gray-500 mb-1">Customer</p>
                    @if($workflowResults['customer'])
                        <p class="font-semibold text-sm dark:text-white">{{ $workflowResults['customer']['name'] }}</p>
                        <p class="text-xs text-gray-500">{{ $workflowResults['phone'] }}</p>
                        <p class="text-xs mt-1">
                            @if($workflowResults['customer']['needs_verification'])
                                <span class="text-amber-600">Needs project code</span>
                            @elseif($workflowResults['customer']['verified'])
                                <span class="text-emerald-600">Verified</span>
                            @else
                                <span class="text-gray-500">Unverified flags clear</span>
                            @endif
                        </p>
                        @if(!empty($workflowResults['customer']['boss_notes']))
                            <p class="text-xs text-gray-500 mt-2"><span class="font-medium">Boss notes:</span> {{ $workflowResults['customer']['boss_notes'] }}</p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">No customer yet (unknown until first message processes).</p>
                        <p class="text-xs text-gray-400">{{ $workflowResults['phone'] }}</p>
                    @endif
                </div>

                <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <p class="text-xs text-gray-500 mb-1">Session</p>
                    @if($workflowResults['session'])
                        <p class="font-semibold text-sm dark:text-white">{{ $workflowResults['session']['status'] }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">{{ $workflowResults['session']['title'] ?: '—' }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit($workflowResults['session']['summary'] ?? '', 120) }}</p>
                        @if($workflowResults['session']['needs_review'])
                            <p class="text-xs text-red-600 mt-1">Needs review</p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500">Waiting for queue…</p>
                    @endif
                </div>

                <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <p class="text-xs text-gray-500 mb-1">Links</p>
                    @if($workflowResults['conversation_url'])
                        <a href="{{ $workflowResults['conversation_url'] }}" class="text-sm text-brand-600 dark:text-brand-400 font-semibold underline">Open Conversation Center</a>
                    @else
                        <p class="text-sm text-gray-500">No conversation yet</p>
                    @endif
                    <p class="text-xs text-gray-500 mt-2">Employee view: <a href="{{ route('workspace.dashboard') }}" class="underline">/workspace</a></p>
                </div>
            </div>

            @if(!empty($workflowResults['tasks']))
                <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <h3 class="font-semibold text-sm mb-3 dark:text-white">Tasks ({{ count($workflowResults['tasks']) }})</h3>
                    <div class="space-y-2">
                        @foreach($workflowResults['tasks'] as $task)
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm border-b border-gray-100 dark:border-gray-700 pb-2">
                                <div>
                                    <a href="{{ $task['url'] }}" class="font-medium text-brand-600 dark:text-brand-400 underline">#{{ $task['id'] }} {{ $task['title'] }}</a>
                                    <span class="text-xs text-gray-500 ml-2">{{ $task['status'] }}@if($task['source']) · {{ $task['source'] }}@endif</span>
                                </div>
                                @if($task['completed'])
                                    <span class="text-xs text-emerald-600 font-semibold">Done</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($workflowResults['outbound']))
                <div class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <h3 class="font-semibold text-sm mb-3 dark:text-white">Recent outbound replies</h3>
                    <div class="space-y-2">
                        @foreach($workflowResults['outbound'] as $msg)
                            <div class="text-xs text-gray-700 dark:text-gray-300">
                                <span class="font-mono text-gray-400">{{ $msg['created_at'] }}</span>
                                @if($msg['kind'])
                                    <span class="ml-1 px-1 rounded bg-gray-100 dark:bg-gray-700">{{ $msg['kind'] }}</span>
                                @endif
                                <p class="mt-0.5">{{ $msg['body'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- E2E Results --}}
    @if($e2eResults)
        <div class="mt-6 space-y-6">
            <h2 class="text-xl font-bold dark:text-white flex items-center gap-2">
                <x-heroicon-o-bolt class="w-6 h-6 text-yellow-500" />
                E2E Simulation Results
                @if($e2eResults['is_boss'] ?? false)
                    <span class="px-2 py-0.5 text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded-full">BOSS</span>
                @else
                    <span class="px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">CUSTOMER</span>
                @endif
            </h2>

            <div class="grid gap-2 lg:grid-cols-2">
                @foreach($e2eResults['steps'] ?? [] as $step)
                    <div class="flex items-center gap-4 p-3 rounded-lg {{ $step['status'] === 'success' ? 'bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800' : ($step['status'] === 'failed' ? 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800' : 'bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700') }}">
                        <div class="w-6">
                            @if($step['status'] === 'success')
                                <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-500" />
                            @elseif($step['status'] === 'failed')
                                <x-heroicon-s-x-circle class="w-5 h-5 text-red-500" />
                            @else
                                <x-heroicon-s-minus-circle class="w-5 h-5 text-gray-400" />
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="font-semibold text-sm dark:text-white">{{ $step['stage'] }}</span>
                            <span class="text-sm text-gray-600 dark:text-gray-400 ml-2">{{ $step['detail'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($e2eResults['ai_output'])
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-sm mb-3 dark:text-white">AI Decision</h3>
                    <pre class="text-xs overflow-auto max-h-64 bg-gray-50 dark:bg-gray-900 p-3 rounded">{{ json_encode($e2eResults['ai_output'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif

            @if(!empty($e2eResults['tasks_created']))
                <div class="p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-sm mb-3 dark:text-white">Tasks Created</h3>
                    <ul class="text-sm space-y-1 dark:text-gray-300">
                        @foreach($e2eResults['tasks_created'] as $t)
                            <li>#{{ $t['id'] }} {{ $t['title'] }} — {{ $t['assigned_to'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    @if($pipelineLog)
        <div class="mt-6 p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-bold dark:text-white mb-2">Pipeline Log #{{ $pipelineLog->id }}</h2>
            <pre class="text-xs overflow-auto max-h-96 bg-gray-50 dark:bg-gray-900 p-3 rounded">{{ json_encode($pipelineLog->toArray(), JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
</div>
