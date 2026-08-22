<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use Illuminate\Support\Facades\Http;

class OpenAIProvider implements AIProviderInterface
{
    public function supportsVision(): bool { return true; }
    public function supportsAudio(): bool { return false; }
    public function supportsJsonMode(): bool { return true; }
    public function supportsTools(): bool { return true; }

    public function analyzeConversation(string $message, \App\Models\AiPrompt $prompt, \App\Models\AiSchema $schema, ?\App\Models\AiModel $model = null): \App\DTOs\AiResponseDTO
    {
        $startTime = microtime(true);
        $isGroq = $model?->provider === 'groq';

        $apiKey = $isGroq
            ? config('services.groq.key')
            : config('services.openai.key');

        if (!$apiKey) {
            throw new \Exception(($isGroq ? 'Groq' : 'OpenAI') . " API Key not configured.");
        }

        $modelName = $model?->apiModelId()
            ?? $prompt->provider_overrides['model']
            ?? config('services.openai.model', 'gpt-4o');
        
        $systemPrompt = $prompt->system_prompt ?? 'Extract data as structured JSON.';
        $userContent = str_replace('{{message}}', $message, $prompt->user_prompt_template ?? '{{message}}');

        $baseUrl = $isGroq
            ? rtrim(config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/')
            : rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

        if ($isGroq) {
            $systemPrompt .= "\n\nYou MUST return a JSON object adhering exactly to this schema: \n" . json_encode($schema->schema_json);
        }

        $payload = [
            'model' => $modelName,
            'temperature' => (float) $prompt->temperature,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $userContent
                ]
            ],
            'response_format' => $isGroq ? ['type' => 'json_object'] : [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'extraction_schema',
                    'schema' => $schema->schema_json,
                    'strict' => true,
                ]
            ]
        ];

        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->post($baseUrl . '/chat/completions', $payload);

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->failed()) {
            throw new \Exception("OpenAI API Error: " . $response->body());
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

        $promptTokens = $data['usage']['prompt_tokens'] ?? 0;
        $completionTokens = $data['usage']['completion_tokens'] ?? 0;
        
        $cost = ($promptTokens * 0.005 / 1000) + ($completionTokens * 0.015 / 1000);
        
        return new \App\DTOs\AiResponseDTO(
            parsedData: $parsed ?? [],
            rawResponse: $response->body(),
            rawRequest: $payload,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            cost: $cost,
            latencyMs: $latencyMs
        );
    }

    public function analyze(string $message): array
    {
        throw new \Exception("analyze() is deprecated, use analyzeConversation()");
    }
}
