<div>
    <x-common.page-breadcrumb pageTitle="Benchmark Dashboard">
        <x-slot:actions>
            <button @click="$wire.showRunModal = true; $wire.openRunModal()" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">
                <x-heroicon-o-play class="w-4 h-4"/> Run Golden Suite
            </button>
        </x-slot:actions>
    </x-common.page-breadcrumb>

    <!-- Run Modal -->
    <x-ui.slide-form-modal entangle="showRunModal" loading-target="openRunModal" title="Run Golden Suite" description="Pick the model and prompt version for this benchmark run." close-method="$set('showRunModal', false)" size="sm">
        <form id="modal-run-benchmark" wire:submit.prevent="runGoldenSuite" class="space-y-4">
            <x-form.select.searchable
                wire:model="ai_model_id"
                label="Target AI Model"
                :options="$models->pluck('name', 'id')->toArray()"
                placeholder="Select a model"
                empty-option="Select a model"
                search-placeholder="Search models..."
            />
            @error('ai_model_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            <x-form.select.searchable
                wire:model="ai_prompt_id"
                label="AI Prompt Version"
                :options="$prompts->pluck('name', 'id')->toArray()"
                placeholder="Select a prompt"
                empty-option="Select a prompt"
                search-placeholder="Search prompts..."
            />
            @error('ai_prompt_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </form>
        <x-slot:footer>
            <button type="button" wire:click="$set('showRunModal', false)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">Cancel</button>
            <button type="submit" form="modal-run-benchmark" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Run Benchmark</button>
        </x-slot:footer>
    </x-ui.slide-form-modal>

    <div class="grid grid-cols-1 gap-6">
        
        <!-- Leaderboard -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Model Leaderboard</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th class="border-b border-gray-200 dark:border-gray-700 p-3 font-semibold text-gray-900 dark:text-white text-sm">Rank</th>
                            <th class="border-b border-gray-200 dark:border-gray-700 p-3 font-semibold text-gray-900 dark:text-white text-sm">AI Model</th>
                            <th class="border-b border-gray-200 dark:border-gray-700 p-3 font-semibold text-gray-900 dark:text-white text-sm text-right">Average Score</th>
                            <th class="border-b border-gray-200 dark:border-gray-700 p-3 font-semibold text-gray-900 dark:text-white text-sm text-right">Tests Run</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaderboard as $index => $rank)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="border-b border-gray-200 dark:border-gray-700 p-3 text-sm text-gray-600 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="border-b border-gray-200 dark:border-gray-700 p-3 text-sm font-semibold text-gray-900 dark:text-white">{{ $rank['model'] }}</td>
                                <td class="border-b border-gray-200 dark:border-gray-700 p-3 text-sm text-right font-bold {{ $rank['average_score'] >= 90 ? 'text-emerald-500' : ($rank['average_score'] >= 70 ? 'text-amber-500' : 'text-red-500') }}">
                                    {{ $rank['average_score'] }}
                                </td>
                                <td class="border-b border-gray-200 dark:border-gray-700 p-3 text-sm text-right text-gray-600 dark:text-gray-400">{{ $rank['tests_run'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-gray-500 dark:text-gray-400">No benchmark data available. Run the Golden Suite first.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Benchmark Matrix -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Benchmark Matrix</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse border border-gray-200 dark:border-gray-700">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900/50">
                            <th class="border border-gray-200 dark:border-gray-700 p-3 font-semibold text-gray-900 dark:text-white text-sm w-1/4">Golden Test</th>
                            @foreach ($models as $model)
                                <th class="border border-gray-200 dark:border-gray-700 p-3 font-semibold text-gray-900 dark:text-white text-sm text-center">{{ $model->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($goldenTests as $test)
                            <tr>
                                <td class="border border-gray-200 dark:border-gray-700 p-3 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $test->name }}</td>
                                @foreach ($models as $model)
                                    @php
                                        $run = $matrix[$model->name][$test->name] ?? null;
                                    @endphp
                                    <td class="border border-gray-200 dark:border-gray-700 p-3 text-center text-sm">
                                        @if ($run)
                                            <div class="{{ $run->score >= 90 ? 'text-emerald-500' : ($run->score >= 70 ? 'text-amber-500' : 'text-red-500') }} font-bold text-lg">
                                                {{ $run->score }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                {{ $run->duration_ms }}ms
                                            </div>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">-</span>
                                        @endif
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
