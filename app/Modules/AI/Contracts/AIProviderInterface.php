<?php

namespace Modules\AI\Contracts;

interface AIProviderInterface
{
    /**
     * Set the version of the prompt being used.
     */
    public function setPromptVersion(int $version): self;

    /**
     * Set the version of the expected output schema.
     */
    public function setSchemaVersion(int $version): self;

    /**
     * Set the temperature for the AI response.
     */
    public function setTemperature(float $temperature): self;

    /**
     * Analyze a conversation and extract structured information.
     *
     * @param array $conversationContext Context (project hints, customer names, boss notes).
     * @param array $messages Array of ['role' => 'user|assistant', 'text' => 'message content'].
     * @param array $attachments Array of attachment references (e.g., ['type' => 'image', 'reference_id' => 123]).
     * 
     * @return array The strictly formatted JSON response based on the defined schema.
     */
    public function analyzeConversation(array $conversationContext, array $messages, array $attachments = []): array;
}
