<?php

namespace App\Services\Mock;

use App\Contracts\AIProviderInterface;
use App\DTOs\AiResponseDTO;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiSchema;
class MockAIProvider implements AIProviderInterface
{
    protected bool $chaosMode;

    public function __construct(bool $chaosMode = false)
    {
        $this->chaosMode = $chaosMode;
    }

    public function supportsVision(): bool
    {
        return false;
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

    public function analyzeConversation(string $message, AiPrompt $prompt, AiSchema $schema, ?AiModel $model = null): AiResponseDTO
    {
        // Chaos mode latency: up to 4s. Normal latency: 50–200ms (keep tests fast).
        $maxLatency = $this->chaosMode ? 4000000 : 200000;
        $latencyUs = rand(50000, $maxLatency);
        usleep($latencyUs);

        $failureRate = $this->chaosMode ? 40 : 0;
        if ($failureRate > 0 && rand(1, 100) <= $failureRate) {
            throw new \Exception($this->chaosMode
                ? 'Chaos: AI Provider catastrophic timeout or API Error 502'
                : 'AI Provider timeout or malformed response');
        }

        $parsedData = $this->buildStructuredOutput($message);

        return new AiResponseDTO(
            parsedData: $parsedData,
            rawResponse: json_encode(['mock' => true, 'choices' => [['message' => ['content' => json_encode($parsedData)]]]]),
            rawRequest: ['message' => $message],
            promptTokens: 120,
            completionTokens: 45,
            cost: 0.0,
            latencyMs: (int) round($latencyUs / 1000)
        );
    }

    /**
     * Schema-compatible payload for Multi-Task Extraction Schema (v2).
     *
     * @return array{
     *     is_actionable: bool,
     *     intent: string,
     *     tasks: list<array{title: string, description: string, priority: string, assigned_to: string}>,
     *     project: string,
     *     summary: string,
     *     confidence: float
     * }
     */
    protected function buildStructuredOutput(string $message): array
    {
        $lower = mb_strtolower($message);
        $casual = (bool) preg_match('/\b(hello|hi|hey|thanks|thank you|ok|okay)\b/u', $lower)
            && ! preg_match('/\b(fix|bug|crash|error|broken|urgent|create|task|issue|down|fail)\b/u', $lower);

        if ($casual) {
            return [
                'is_actionable' => false,
                'intent' => 'greeting',
                'tasks' => [],
                'project' => '',
                'summary' => "English: Casual message — no work requested.\nArabic: رسالة عادية — لا يوجد عمل مطلوب.",
                'confidence' => 0.95,
            ];
        }

        $priority = 'medium';
        if (preg_match('/\b(urgent|asap|crash|crashes|down|critical)\b/u', $lower)) {
            $priority = 'urgent';
        } elseif (preg_match('/\b(important|high|soon)\b/u', $lower)) {
            $priority = 'high';
        } elseif (preg_match('/\b(low priority|when you can)\b/u', $lower)) {
            $priority = 'low';
        }

        $assignedTo = '';
        if (preg_match('/assign(?:ed)?\s+to\s+([A-Za-z\x{0600}-\x{06FF}]+)/u', $message, $m)) {
            $assignedTo = $m[1];
        } elseif (preg_match('/give this to\s+([A-Za-z\x{0600}-\x{06FF}]+)/u', $lower, $m)) {
            $assignedTo = ucfirst($m[1]);
        }

        $project = '';
        if (preg_match('/project:\s*([^\n.(]+)/iu', $message, $m)) {
            $project = trim($m[1]);
        }

        $titleSeed = trim(preg_replace('/\s+/', ' ', strip_tags($message)));
        $titleSeed = \Illuminate\Support\Str::limit($titleSeed, 80, '');

        return [
            'is_actionable' => true,
            'intent' => 'task_request',
            'tasks' => [
                [
                    'title' => "English: {$titleSeed}\nArabic: مهمة مستخرجة من الرسالة",
                    'description' => "English: {$message}\nArabic: وصف المهمة المستخرجة من رسالة المحاكاة.",
                    'priority' => $priority,
                    'assigned_to' => $assignedTo,
                ],
            ],
            'project' => $project,
            'summary' => "English: Mock extraction of an actionable request.\nArabic: استخراج وهمي لطلب قابل للتنفيذ.",
            'confidence' => 0.92,
        ];
    }

    public function analyze(string $message): array
    {
        return $this->buildStructuredOutput($message);
    }
}
