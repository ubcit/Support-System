<?php

namespace App\Services\Pipeline;

use App\Models\PipelineLog;
use App\Contracts\AIProviderInterface;
use App\Contracts\SyncProviderInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MessagePipeline
{
    protected PipelineLog $log;
    protected array $payload;
    protected bool $runAi;
    protected ?\App\Models\CertificationRun $certificationRun = null;
    protected ?\App\Models\CertificationTest $certificationTest = null;
    protected float $pipelineStartTime;
    protected array $aiOutput = [];

    public function __construct(array $payload, bool $runAi = true)
    {
        $this->payload = $payload;
        $this->runAi = $runAi;
        $this->pipelineStartTime = microtime(true);
        
        $this->log = PipelineLog::create([
            'uuid' => (string) Str::uuid(),
            'status' => 'running',
            'input_payload' => $payload,
            'correlation_id' => $payload['correlation_id'] ?? (string) Str::uuid(),
            'stage_timings' => [],
            'stage_status' => [],
        ]);

        if (!empty($payload['certification_test_id'])) {
            $this->certificationTest = \App\Models\CertificationTest::with('expectation')->find($payload['certification_test_id']);
            if ($this->certificationTest) {
                $this->certificationRun = \App\Models\CertificationRun::create([
                    'certification_test_id' => $this->certificationTest->id,
                    'pipeline_log_id' => $this->log->id,
                    'ai_provider' => $payload['ai_provider'] ?? 'mock',
                    'sync_provider' => $payload['sync_provider'] ?? 'mock',
                    'started_at' => now(),
                ]);
            }
        }
    }

    public function execute(): PipelineLog
    {
        try {
            $this->runStage('webhook', fn() => $this->handleWebhook());
            $this->runStage('conversation', fn() => $this->handleConversation());
            
            if ($this->runAi) {
                $this->runStage('ai', fn() => $this->handleAi());
            } else {
                $this->skipStage('ai');
            }

            $this->runStage('issue', fn() => $this->handleIssue());
            $this->runStage('task', fn() => $this->handleTask());
            $this->runStage('rules', fn() => $this->handleRules());
            $this->runStage('sync', fn() => $this->handleSync());
            $this->runStage('notification', fn() => $this->handleNotification());

            $this->log->update(['status' => 'success']);

            // Dispatch Shadow AI
            $this->dispatchShadowAi();

            if ($this->certificationRun) {
                $this->certificationRun->update([
                    'success' => true,
                    'finished_at' => now(),
                    'duration_ms' => round((microtime(true) - $this->pipelineStartTime) * 1000),
                ]);
                $this->runAssertions();
                
                // Calculate Score
                $scoringEngine = new \App\Services\AI\AiScoringEngine();
                $this->certificationRun->update(['score' => $scoringEngine->calculateScore($this->certificationRun)]);
            }

        } catch (\Exception $e) {
            Log::error("Pipeline failed: " . $e->getMessage());
            $this->log->update(['status' => 'failed']);
            
            if ($this->certificationRun) {
                $this->certificationRun->update([
                    'success' => false,
                    'finished_at' => now(),
                    'duration_ms' => round((microtime(true) - $this->pipelineStartTime) * 1000),
                ]);
                $scoringEngine = new \App\Services\AI\AiScoringEngine();
                $this->certificationRun->update(['score' => $scoringEngine->calculateScore($this->certificationRun)]);
            }
        }

        return $this->log;
    }

    protected function runAssertions()
    {
        if (!$this->certificationTest->expectation) return;
        
        $expectedData = $this->certificationTest->expectation->expected_data ?? [];
        
        foreach ($expectedData as $key => $expectedValue) {
            $actualValue = $this->aiOutput[$key] ?? null;
            $passed = $actualValue == $expectedValue;
            
            \App\Models\CertificationAssertion::create([
                'certification_run_id' => $this->certificationRun->id,
                'key' => $key,
                'expected_value' => is_array($expectedValue) ? json_encode($expectedValue) : (string)$expectedValue,
                'actual_value' => is_array($actualValue) ? json_encode($actualValue) : (string)$actualValue,
                'passed' => $passed,
            ]);
        }
    }

    protected function dispatchShadowAi()
    {
        // Only run shadow AI for actual customer messages, not empty ones
        $message = $this->payload['customer_message'] ?? '';
        if (empty($message)) {
            return;
        }

        // Do not trigger shadow AI if this is already a benchmark run to avoid infinite loops
        if ($this->certificationRun && $this->certificationRun->benchmark_session_id) {
            return;
        }

        $shadowModels = \App\Models\AiModel::where('is_shadow_mode', true)->where('is_active', true)->get();
        if ($shadowModels->isEmpty()) {
            return;
        }

        $prompt = \App\Models\AiPrompt::where('is_active', true)->orderByDesc('version')->first();
        if (!$prompt) {
            return;
        }

        foreach ($shadowModels as $shadowModel) {
            // We do not want shadow mode to run the exact same provider model if it's identical
            if (($this->payload['ai_provider'] ?? 'mock') === $shadowModel->provider) {
                continue;
            }
            dispatch(new \App\Jobs\RunShadowAiJob($message, $shadowModel->id, $prompt->id, $this->log->id));
        }
    }

    protected function runStage(string $stage, callable $callback)
    {
        $startTime = microtime(true);
        
        try {
            $result = $callback();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2); // ms
            $this->updateStageLog($stage, 'success', $duration);
            
            return $result;
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2); // ms
            $this->updateStageLog($stage, 'failed', $duration, $e->getMessage());
            throw $e;
        }
    }

    protected function skipStage(string $stage)
    {
        $this->updateStageLog($stage, 'skipped', 0);
    }

    protected function updateStageLog(string $stage, string $status, float $duration, ?string $error = null)
    {
        $timings = $this->log->stage_timings ?? [];
        $statuses = $this->log->stage_status ?? [];

        $timings[$stage] = $duration;
        $statuses[$stage] = [
            'status' => $status,
            'error' => $error
        ];

        $this->log->update([
            'stage_timings' => $timings,
            'stage_status' => $statuses,
        ]);
    }

    // --- Mock Stage Handlers ---

    protected function handleWebhook()
    {
        usleep(10000); // 10ms
    }

    protected function handleConversation()
    {
        usleep(20000); // 20ms
    }

    protected function handleAi()
    {
        $chaosMode = $this->payload['chaos_mode'] ?? false;
        
        // Resolve the active prompt and its schema
        $prompt = \App\Models\AiPrompt::with('schema')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
            
        if (!$prompt || !$prompt->schema) {
            throw new \Exception("No active AI Prompt or Schema found in registry.");
        }

        // Determine which model we want to use (from simulator or fallback)
        $providerKey = $this->payload['ai_provider'] ?? 'mock';
        $model = \App\Models\AiModel::where('provider', $providerKey)
            ->where('is_active', true)
            ->first();
            
        if (!$model) {
            throw new \Exception("No active AI Model found for provider: {$providerKey}");
        }

        $message = $this->payload['customer_message'] ?? $this->payload['message'] ?? 'Default test message';

        // Use AIManager to execute and track the request
        $manager = new \App\Services\AI\AIManager();
        $aiRequestLog = $manager->execute(
            $message, 
            $model, 
            $prompt, 
            $prompt->schema, 
            $this->log ? $this->log->id : null, 
            $chaosMode
        );

        if ($aiRequestLog->validation_status !== 'passed') {
            throw new \Exception("AI Output validation failed: " . $aiRequestLog->error_message);
        }
        
        $this->aiOutput = $aiRequestLog->parsed_json;
        return $this->aiOutput;
    }

    protected function handleIssue()
    {
        usleep(15000); // 15ms
    }

    protected function handleTask()
    {
        usleep(10000); // 10ms
    }

    protected function handleRules()
    {
        usleep(12000); // 12ms
    }

    protected function handleSync()
    {
        $chaosMode = $this->payload['chaos_mode'] ?? false;
        $syncProvider = new \App\Services\Mock\MockSyncProvider($chaosMode);
        return $syncProvider->createTask($this->payload);
    }

    protected function handleNotification()
    {
        usleep(50000); // 50ms
    }
}
