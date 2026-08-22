<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Services\AI\AIManager;
use App\Models\PipelineLog;

class RunShadowAiJob implements ShouldQueue
{
    use Queueable;

    protected $message;
    protected $modelId;
    protected $promptId;
    protected $pipelineLogId;

    public function __construct(string $message, int $modelId, int $promptId, int $pipelineLogId)
    {
        $this->message = $message;
        $this->modelId = $modelId;
        $this->promptId = $promptId;
        $this->pipelineLogId = $pipelineLogId;
    }

    public function handle(): void
    {
        try {
            $model = AiModel::find($this->modelId);
            $prompt = AiPrompt::with('schema')->find($this->promptId);

            if (!$model || !$prompt || !$prompt->schema) {
                return;
            }

            $manager = new AIManager();
            // Execute but do not bubble exceptions to avoid cluttering queue logs for expected AI failures
            try {
                $manager->execute($this->message, $model, $prompt, $prompt->schema, $this->pipelineLogId);
            } catch (\Exception $aiError) {
                Log::warning("Shadow AI execution failed (Model: {$model->name}): " . $aiError->getMessage());
            }

        } catch (\Exception $e) {
            Log::error("Shadow AI Job failed: " . $e->getMessage());
        }
    }
}
