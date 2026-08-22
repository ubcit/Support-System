<?php

namespace App\Contracts;

interface AIProviderInterface
{
    /**
     * @deprecated Use analyzeConversation instead
     */
    public function analyze(string $message): array;

    public function supportsVision(): bool;
    public function supportsAudio(): bool;
    public function supportsJsonMode(): bool;
    public function supportsTools(): bool;

    public function analyzeConversation(string $message, \App\Models\AiPrompt $prompt, \App\Models\AiSchema $schema, ?\App\Models\AiModel $model = null): \App\DTOs\AiResponseDTO;
}
