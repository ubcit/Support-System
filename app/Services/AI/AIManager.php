<?php

namespace App\Services\AI;

use App\Contracts\AIProviderInterface;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiRequestLog;
use App\Models\AiSchema;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\Mock\MockAIProvider;
use Illuminate\Support\Str;

class AIManager
{
    public function resolve(AiModel $model, bool $chaosMode = false): AIProviderInterface
    {
        return match ($model->provider) {
            'openai', 'groq' => new OpenAIProvider,
            'gemini' => new GeminiProvider,
            'mock' => new MockAIProvider($chaosMode),
            default => throw new \Exception("Unsupported AI Provider: {$model->provider}"),
        };
    }

    /**
     * @param  array{
     *     customer_id?: ?int,
     *     conversation_id?: ?int,
     *     conversation_session_id?: ?int,
     *     project_id?: ?int,
     *     source?: ?string
     * }  $context
     */
    public function execute(string $message, AiModel $model, AiPrompt $prompt, AiSchema $schema, ?int $pipelineLogId = null, bool $chaosMode = false, array $context = []): AiRequestLog
    {
        $provider = $this->resolve($model, $chaosMode);

        $source = $context['source'] ?? ($pipelineLogId ? 'pipeline' : null);

        $log = AiRequestLog::create([
            'uuid' => (string) Str::uuid(),
            'pipeline_log_id' => $pipelineLogId,
            'ai_model_id' => $model->id,
            'ai_prompt_id' => $prompt->id,
            'customer_id' => $context['customer_id'] ?? null,
            'conversation_id' => $context['conversation_id'] ?? null,
            'conversation_session_id' => $context['conversation_session_id'] ?? null,
            'project_id' => $context['project_id'] ?? null,
            'source' => $source,
            'provider' => $model->provider,
            'model_name' => $model->name,
            'validation_status' => 'pending',
        ]);

        try {
            $dto = $provider->analyzeConversation($message, $prompt, $schema, $model);

            // Validate the DTO parsedData against Schema
            $isValid = $this->validateStrict($dto->parsedData, $schema->schema_json);

            $log->update([
                'prompt_tokens' => $dto->promptTokens,
                'completion_tokens' => $dto->completionTokens,
                'total_tokens' => $dto->getTotalTokens(),
                'cost' => $dto->cost,
                'latency_ms' => $dto->latencyMs,
                'raw_request' => $dto->rawRequest,
                'raw_response' => $dto->rawResponse,
                'parsed_json' => $dto->parsedData,
                'validation_status' => $isValid ? 'passed' : 'failed',
                'error_message' => $isValid ? null : 'JSON Schema validation failed.',
            ]);

            if (! $isValid) {
                throw new \Exception('AI Output failed strict validation.');
            }

        } catch (\Exception $e) {
            $log->update([
                'validation_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $log;
    }

    protected function validateStrict(array $data, array $schema): bool
    {
        // Basic required field validation for now
        $required = $schema['required'] ?? [];
        foreach ($required as $field) {
            if (! array_key_exists($field, $data)) {
                return false;
            }
        }

        return true;
    }
}
