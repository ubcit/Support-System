<div>
    <x-common.page-breadcrumb pageTitle="AI Center" compact>
        <x-slot:subtitle>Models, prompts, schemas, and playground</x-slot:subtitle>
    </x-common.page-breadcrumb>

    <div class="space-y-6">
        <!-- Navigation Bar -->
        <div class="flex flex-wrap gap-2 bg-white dark:bg-gray-800 p-2 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm text-sm font-medium">
            <button wire:click="setTab('models')" class="px-4 py-2 rounded-lg transition-all {{ $activeTab === 'models' ? 'bg-brand-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Models</button>
            <button wire:click="setTab('prompts')" class="px-4 py-2 rounded-lg transition-all {{ $activeTab === 'prompts' ? 'bg-brand-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Prompts</button>
            <button wire:click="setTab('schemas')" class="px-4 py-2 rounded-lg transition-all {{ $activeTab === 'schemas' ? 'bg-brand-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Schemas</button>
            <button wire:click="setTab('playground')" class="px-4 py-2 rounded-lg transition-all {{ $activeTab === 'playground' ? 'bg-brand-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Playground</button>
            <button wire:click="setTab('logs')" class="px-4 py-2 rounded-lg transition-all {{ $activeTab === 'logs' ? 'bg-brand-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Request Audit Logs</button>
            <button wire:click="setTab('costs')" class="px-4 py-2 rounded-lg transition-all {{ $activeTab === 'costs' ? 'bg-brand-500 text-white shadow-md' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">Costs & Benchmarks</button>
        </div>

        <!-- Tab Content -->
        @if($activeTab === 'models')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 flex justify-between items-center">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">Registered AI Models & Shadow AI Engine</h3>
                    <a href="{{ route('ai.models.index') }}" class="text-sm text-brand-500 hover:underline">Manage Models</a>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        @forelse($models as $m)
                            <div class="p-5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50 hover:shadow-sm transition-shadow">
                                <h4 class="font-semibold text-gray-800 dark:text-white/90">{{ $m->name }}</h4>
                                <span class="text-sm text-gray-500 dark:text-gray-400 mt-1 block">Provider: <span class="capitalize">{{ $m->provider }}</span></span>
                                <div class="flex items-center justify-between pt-4 mt-4 border-t border-gray-200 dark:border-gray-700 text-sm">
                                    <span class="px-2.5 py-0.5 rounded-full {{ $m->is_active ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }} font-medium">
                                        {{ $m->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <span class="text-gray-500 dark:text-gray-400 font-medium">{{ $avgLatencyMs }}ms avg</span>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full text-center text-sm text-gray-500 dark:text-gray-400 py-8 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                                No custom AI models registered. <a href="{{ route('ai.models.index') }}" class="text-brand-500 hover:underline">Configure engines</a>.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'prompts')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 flex justify-between items-center">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">AI Prompt Version Templates</h3>
                    <a href="{{ route('ai.prompts.index') }}" class="text-sm text-brand-500 hover:underline">Manage Prompts</a>
                </div>
                <div class="p-5 space-y-4">
                    @forelse($prompts as $p)
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50 flex flex-col sm:flex-row justify-between sm:items-center gap-4 hover:shadow-sm transition-shadow">
                            <div>
                                <h4 class="font-semibold text-gray-800 dark:text-white/90 text-base">{{ $p->name }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">Version {{ $p->version }}</span> &bull; 
                                    {{ $p->system_prompt ? Str::limit($p->system_prompt, 80) : 'Standard System Prompt' }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <span class="px-3 py-1 text-xs font-medium rounded-full {{ $p->is_active ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                    {{ $p->is_active ? 'Active' : 'Draft' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-gray-500 dark:text-gray-400 py-8 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                            No AI prompts registered. <a href="{{ route('ai.prompts.index') }}" class="text-brand-500 hover:underline">Add prompt templates</a>.
                        </div>
                    @endforelse
                </div>
            </div>

        @elseif($activeTab === 'schemas')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 flex justify-between items-center">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">JSON Schema Output Governance</h3>
                    <a href="{{ route('ai.schemas.index') }}" class="text-sm text-brand-500 hover:underline">Manage Schemas</a>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                    @forelse($schemas as $s)
                        <div class="p-5 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700/50 hover:shadow-sm transition-shadow flex flex-col">
                            <h4 class="font-semibold text-gray-800 dark:text-white/90 mb-3">{{ $s->name }}</h4>
                            <div class="flex-grow rounded-lg bg-gray-900 p-4 overflow-hidden relative group">
                                <pre class="text-xs font-mono text-gray-300 overflow-x-auto max-h-48 custom-scrollbar">{{ is_array($s->schema_json) ? json_encode($s->schema_json, JSON_PRETTY_PRINT) : $s->schema_json }}</pre>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center text-sm text-gray-500 dark:text-gray-400 py-8 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                            No schemas registered. Schemas define strict JSON output formats for AI analysis.
                        </div>
                    @endforelse
                </div>
            </div>

        @elseif($activeTab === 'playground')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">Interactive Prompt Test Bench</h3>
                </div>
                <div class="p-8 text-center bg-gray-50 dark:bg-gray-800/20">
                    <div class="mx-auto max-w-md">
                        <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-500/10">
                            <svg class="h-8 w-8 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">Full Prompt Playground Test Bench</h3>
                        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                            Execute real AI models against active prompts, schema validations, and chaos mode simulation in real time.
                        </p>
                        <a href="/admin/prompt-playground" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-medium text-white transition hover:bg-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-500/20">
                            Open Prompt Playground
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </a>
                    </div>
                </div>
            </div>

        @elseif($activeTab === 'logs')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 flex justify-between items-center">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">AI Execution Audit Logs</h3>
                    <a href="{{ route('ai.request-logs.index') }}" class="text-sm text-brand-500 hover:underline">View All Logs</a>
                </div>
                <div class="p-0 overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-6 py-4">Log ID</th>
                                <th scope="col" class="px-6 py-4">Model</th>
                                <th scope="col" class="px-6 py-4 text-right">Tokens</th>
                                <th scope="col" class="px-6 py-4 text-right">Latency</th>
                                <th scope="col" class="px-6 py-4 text-right">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $l)
                                <tr class="border-b bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800">
                                    <td class="px-6 py-4 font-mono text-xs font-medium text-gray-900 dark:text-white whitespace-nowrap">
                                        #{{ $l->id }}
                                    </td>
                                    <td class="px-6 py-4">
                                        {{ $l->model_name ?? 'Mock/OpenAI' }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono text-xs">
                                        {{ number_format($l->total_tokens ?? 0) }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="inline-flex rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                            {{ number_format($l->latency_ms ?? 0) }}ms
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        {{ $l->created_at ? $l->created_at->diffForHumans() : 'Just now' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No AI execution logs found in database.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        @elseif($activeTab === 'costs')
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="font-medium text-gray-800 dark:text-white/90">Financial Expenditure & Benchmarking</h3>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-500 dark:bg-blue-500/10 dark:text-blue-400">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tokens Consumed</p>
                            <h4 class="mt-1 text-title-sm font-bold text-black dark:text-white">{{ number_format($totalTokens) }}</h4>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-success-50 text-success-500 dark:bg-success-500/10 dark:text-success-400">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Estimated Expenditure</p>
                            <h4 class="mt-1 text-title-sm font-bold text-success-600 dark:text-success-400">${{ collect([$estimatedCostUsd])->first() }} USD</h4>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-3 rounded-xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-50 text-brand-500 dark:bg-brand-500/10 dark:text-brand-400">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Average Latency</p>
                            <h4 class="mt-1 text-title-sm font-bold text-brand-600 dark:text-brand-400">{{ $avgLatencyMs }} ms</h4>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
