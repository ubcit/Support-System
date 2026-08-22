<?php

namespace App\Livewire\BenchmarkDashboard;

use Livewire\Component;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\CertificationTest;
use App\Models\BenchmarkSession;

class Index extends Component
{
    public ?int $ai_model_id = null;
    public ?int $ai_prompt_id = null;
    public bool $showRunModal = false;

    public function mount(): void
    {
        if (CertificationTest::count() === 0) {
            CertificationTest::create([
                'name' => 'Medical Triage Accuracy Test',
                'input_message' => 'Emergency: Patient booking portal returning 500 error during checkout',
                'expected_output_json' => ['urgency' => 'emergency', 'priority' => 'high'],
                'is_golden' => true,
                'is_active' => true,
            ]);

            CertificationTest::create([
                'name' => 'General Inquiry Routing Test',
                'input_message' => 'Hello, what are your clinic working hours?',
                'expected_output_json' => ['urgency' => 'low', 'priority' => 'low'],
                'is_golden' => true,
                'is_active' => true,
            ]);
        }
    }

    public function openRunModal()
    {
        $this->showRunModal = true;
    }

    public function runGoldenSuite()
    {
        $this->validate([
            'ai_model_id' => 'required|exists:ai_models,id',
            'ai_prompt_id' => 'required|exists:ai_prompts,id',
        ]);

        $goldenCount = CertificationTest::where('is_golden', true)->where('is_active', true)->count();
        
        if ($goldenCount === 0) {
            session()->flash('error', 'No Golden Tests Found');
            return;
        }

        $prompt = AiPrompt::findOrFail($this->ai_prompt_id);
        
        $session = BenchmarkSession::create([
            'name' => 'Golden Suite Benchmark - ' . now()->format('Y-m-d H:i'),
            'ai_prompt_id' => $prompt->id,
            'ai_schema_id' => $prompt->ai_schema_id,
            'status' => 'running',
            'created_by' => auth()->id(),
        ]);

        dispatch(new \App\Jobs\RunGoldenSuiteJob($session->id, $this->ai_model_id));

        $this->showRunModal = false;
        $this->ai_model_id = null;
        $this->ai_prompt_id = null;

        session()->flash('success', "Started Golden Suite Benchmark ($goldenCount tests)");
    }

    public function render()
    {
        $goldenTests = CertificationTest::where('is_golden', true)->where('is_active', true)->get();
        $models = AiModel::where('is_active', true)->get();
        $prompts = AiPrompt::where('is_active', true)->get();

        $leaderboard = [];
        $matrix = [];

        foreach ($models as $model) {
            $totalScore = 0;
            $testCount = 0;
            $matrix[$model->name] = [];

            foreach ($goldenTests as $test) {
                $latestRun = \App\Models\CertificationRun::where('certification_test_id', $test->id)
                    ->where('ai_model_id', $model->id)
                    ->whereNotNull('benchmark_session_id')
                    ->orderByDesc('created_at')
                    ->first();

                if ($latestRun && $latestRun->score !== null) {
                    $totalScore += $latestRun->score;
                    $testCount++;
                    $matrix[$model->name][$test->name] = $latestRun;
                } else {
                    $matrix[$model->name][$test->name] = null;
                }
            }

            if ($testCount > 0) {
                $leaderboard[] = [
                    'model' => $model->name,
                    'average_score' => round($totalScore / $testCount, 2),
                    'tests_run' => $testCount,
                ];
            }
        }

        usort($leaderboard, fn($a, $b) => $b['average_score'] <=> $a['average_score']);

        return view('livewire.benchmark-dashboard.index', [
            'goldenTests' => $goldenTests,
            'models' => $models,
            'prompts' => $prompts,
            'leaderboard' => $leaderboard,
            'matrix' => $matrix,
        ]);
    }
}
