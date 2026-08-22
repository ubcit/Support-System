<?php

namespace App\DTOs;

class AiResponseDTO
{
    public function __construct(
        public array $parsedData,
        public string $rawResponse,
        public array $rawRequest = [],
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public float $cost = 0.0,
        public int $latencyMs = 0
    ) {}

    public function getTotalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
