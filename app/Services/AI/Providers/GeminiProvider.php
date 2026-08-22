<?php

namespace App\Services\AI\Providers;

use App\Contracts\AIProviderInterface;
use App\DTOs\AiResponseDTO;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiSchema;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AIProviderInterface
{
    public function supportsVision(): bool
    {
        return true;
    }

    public function supportsAudio(): bool
    {
        return false;
    }

    public function supportsJsonMode(): bool
    {
        return true;
    }

    public function supportsTools(): bool
    {
        return false;
    }

    /**
     * @deprecated Use analyzeConversation instead
     */
    public function analyze(string $message): array
    {
        return [];
    }

    public function analyzeConversation(string $message, AiPrompt $prompt, AiSchema $schema, ?AiModel $model = null): AiResponseDTO
    {
        $startTime = microtime(true);
        $apiKey = config('services.gemini.key');

        if (! $apiKey) {
            throw new \Exception('Gemini API Key not configured. Please add GEMINI_API_KEY to your .env file.');
        }

        $modelName = $model?->apiModelId()
            ?? $prompt->provider_overrides['model']
            ?? config('services.gemini.model', 'gemini-3.5-flash-lite');

        $systemPrompt = $prompt->system_prompt ?? 'Extract data as structured JSON.';
        $userContent = str_replace('{{message}}', $message, $prompt->user_prompt_template ?? '{{message}}');

        // Note: Gemini JSON Schema format requires slightly different formatting compared to OpenAI (e.g., no additionalProperties)
        // But for a simple test we can pass the JSON schema directly to responseSchema.
        $geminiSchema = $schema->schema_json;

        // Remove `$schema` key if present, as Gemini does not support it
        if (isset($geminiSchema['$schema'])) {
            unset($geminiSchema['$schema']);
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userContent],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => (float) $prompt->temperature,
                'responseMimeType' => 'application/json',
                'responseSchema' => $geminiSchema,
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key={$apiKey}";

        $response = Http::timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if ($response->failed()) {
            throw new \Exception('Gemini API Error: '.$response->body());
        }

        $data = $response->json();

        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '{}';

        // Sometimes Gemini returns JSON wrapped in markdown blocks even with JSON mode
        $content = trim($content);
        if (str_starts_with($content, '```json')) {
            $content = substr($content, 7);
            if (str_ends_with($content, '```')) {
                $content = substr($content, 0, -3);
            }
        }
        $content = trim($content);

        $parsed = json_decode($content, true);

        // Usage data for Gemini
        $promptTokens = $data['usageMetadata']['promptTokenCount'] ?? 0;
        $completionTokens = $data['usageMetadata']['candidatesTokenCount'] ?? 0;

        // Gemini Flash costs (rough estimate: $0.075 / 1M input, $0.30 / 1M output)
        // Using cents.
        $cost = (($promptTokens / 1_000_000) * 7.5) + (($completionTokens / 1_000_000) * 30.0);

        return new AiResponseDTO(
            parsedData: is_array($parsed) ? $parsed : [],
            rawResponse: $response->body(),
            rawRequest: $payload,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            cost: $cost,
            latencyMs: $latencyMs,
        );
    }
}
