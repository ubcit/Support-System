<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\BenchmarkSession;
use App\Models\CertificationTest;
use App\Models\AiModel;
use App\Services\Pipeline\MessagePipeline;

class RunGoldenSuiteJob implements ShouldQueue
{
    use Queueable;

    protected $sessionId;
    protected $aiModelId;

    public function __construct($sessionId, $aiModelId)
    {
        $this->sessionId = $sessionId;
        $this->aiModelId = $aiModelId;
    }

    public function handle(): void
    {
        $session = BenchmarkSession::find($this->sessionId);
        $model = AiModel::find($this->aiModelId);

        if (!$session || !$model) {
            return;
        }

        $goldenTests = CertificationTest::with('input')->where('is_golden', true)->where('is_active', true)->get();

        foreach ($goldenTests as $test) {
            try {
                $payload = [
                    'correlation_id' => (string) Str::uuid(),
                    'certification_test_id' => $test->id,
                    'ai_provider' => $model->provider,
                    'sync_provider' => 'mock', // Keep sync isolated during benchmarks
                    'customer_phone' => $test->input->customer_phone ?? '077000000',
                    'project' => $test->input->project_name ?? 'Clinic ERP',
                    'boss_notes' => $test->input->boss_notes ?? '',
                    'customer_message' => $test->input->messages ?? '',
                ];

                $pipeline = new MessagePipeline($payload, true);
                
                // Override the certification run benchmark fields
                // The constructor creates the run, we need to find it and update it
                $pipeline->execute();
                
                // Find the run and attach the benchmark session
                $run = \App\Models\CertificationRun::where('certification_test_id', $test->id)
                    ->orderByDesc('id')
                    ->first();
                    
                if ($run) {
                    $run->update([
                        'benchmark_session_id' => $session->id,
                        'ai_model_id' => $model->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Benchmark test failed for {$test->name}: " . $e->getMessage());
            }
        }

        $session->update(['status' => 'completed']);
    }
}
