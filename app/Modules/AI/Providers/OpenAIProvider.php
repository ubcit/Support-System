<?php

namespace Modules\AI\Providers;

use App\Models\AiModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\AI\Contracts\AIProviderInterface;
use Modules\AI\Models\AILog;

class OpenAIProvider implements AIProviderInterface
{
    protected string $model = 'gpt-4o';
    protected int $promptVersion = 1;
    protected int $schemaVersion = 1;
    protected float $temperature = 0.2;

    public function setPromptVersion(int $version): self
    {
        $this->promptVersion = $version;
        return $this;
    }

    public function setSchemaVersion(int $version): self
    {
        $this->schemaVersion = $version;
        return $this;
    }

    public function setTemperature(float $temperature): self
    {
        $this->temperature = $temperature;
        return $this;
    }

    protected function resolveActiveModel(): ?AiModel
    {
        return AiModel::where('is_active', true)->first();
    }

    protected function resolveApiConfig(?AiModel $activeModel): array
    {
        $provider = $activeModel?->provider;

        if ($provider === 'groq') {
            return [
                'api_key' => config('services.groq.key'),
                'base_url' => rtrim(config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/'),
                'model' => $activeModel->apiModelId(),
                'provider' => 'groq',
            ];
        }

        return [
            'api_key' => config('services.openai.key'),
            'base_url' => rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/'),
            'model' => $activeModel?->apiModelId() ?? config('services.openai.model', $this->model),
            'provider' => $activeModel?->provider ?? 'openai',
        ];
    }

    public function analyzeConversation(array $conversationContext, array $messages, array $attachments = []): array
    {
        $startTime = microtime(true);
        $activeModel = $this->resolveActiveModel();
        $apiConfig = $this->resolveApiConfig($activeModel);

        $systemPrompt = $this->getSystemPrompt($conversationContext, $attachments);
        
        $apiMessages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        foreach ($messages as $msg) {
            $role = in_array($msg['role'], ['system', 'user', 'assistant']) ? $msg['role'] : 'user';
            $apiMessages[] = [
                'role' => $role,
                'content' => "({$msg['role']}) " . $msg['text'],
            ];
        }

        try {
            $response = Http::withToken($apiConfig['api_key'])
                ->retry(3, 1000)
                ->timeout(60)
                ->post($apiConfig['base_url'] . '/chat/completions', [
                    'model' => $apiConfig['model'],
                    'messages' => $apiMessages,
                    'temperature' => $this->temperature,
                    'response_format' => ['type' => 'json_object'],
                ]);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                Log::error("{$apiConfig['provider']} API Request Failed", ['response' => $response->body()]);
                throw new \Exception("{$apiConfig['provider']} API Error: " . $response->body());
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '{}';
            $parsed = json_decode($content, true);
            if ($parsed === null) {
                if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $content, $m)) {
                    $parsed = json_decode($m[1], true);
                } elseif (preg_match('/(\{[\s\S]*\})/', $content, $m)) {
                    $parsed = json_decode($m[1], true);
                }
            }

            $inputTokens = $data['usage']['prompt_tokens'] ?? 0;
            $outputTokens = $data['usage']['completion_tokens'] ?? 0;
            
            // Very rough estimation of cost for GPT-4o
            $costCents = (($inputTokens / 1000) * 0.5) + (($outputTokens / 1000) * 1.5);

            $this->logInteraction(
                'analyze_conversation',
                json_encode($apiMessages),
                $content,
                $inputTokens,
                $outputTokens,
                $costCents,
                $durationMs,
                $apiConfig['provider'],
                $apiConfig['model']
            );

            // Ensure schema version is appended to the response as per contract
            $parsed['schema_version'] = $this->schemaVersion;

            return $parsed;

        } catch (\Exception $e) {
            Log::error('OpenAIProvider Error', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function getSystemPrompt(array $context, array $attachments): string
    {
        return <<<EOT
You are an information extraction engine, not an autonomous agent.
Your ONLY job is to convert messy customer communication into structured JSON information.

RULES:
1. NEVER decide if a customer is correct or incorrect.
2. NEVER classify something as a bug, feature request, or user error.
3. NEVER create tasks, assign employees, or execute business rules.
4. Correct grammar in the summary without changing the intent.
5. Provide a confidence score (0.00 to 1.00) for inferred fields.

CONTEXT:
Project Hints: {$context['project']}
Customer: {$context['customer']}
Boss Notes: {$context['boss_notes']}
Attachments provided: {$this->formatAttachments($attachments)}

OUTPUT SCHEMA (STRICT JSON ONLY, NO MARKDOWN):
{
  "title": "Concise task title",
  "description": "Detailed description extracted from messages",
  "summary": "One-paragraph internal developer summary",
  "project": {
    "matched": "Matched project name from hints if any",
    "confidence": 0.95
  },
  "employee": {
    "matched": "Matched employee name from hints if any",
    "confidence": 0.95
  },
  "tags": ["tag1", "tag2"],
  "attachments": [
    {
      "id": 123,
      "type": "image",
      "analysis": "Brief analysis if applicable"
    }
  ],
  "confidence": 0.90
}
EOT;
    }

    protected function formatAttachments(array $attachments): string
    {
        if (empty($attachments)) return 'None';
        return json_encode($attachments);
    }

    protected function logInteraction(string $action, string $input, string $output, int $inTokens, int $outTokens, float $cost, int $duration, string $provider = 'OpenAI', ?string $modelName = null): void
    {
        AILog::create([
            'provider' => ucfirst($provider),
            'model' => $modelName ?? $this->model,
            'action' => $action,
            'input_text' => $input,
            'output_text' => $output,
            'input_tokens' => $inTokens,
            'output_tokens' => $outTokens,
            'cost_cents' => $cost,
            'duration_ms' => $duration,
            'metadata' => [
                'prompt_version' => $this->promptVersion,
                'schema_version' => $this->schemaVersion,
                'temperature' => $this->temperature,
            ],
        ]);
    }
}
