<?php

namespace Modules\AI\DTOs;

class AIResult
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $summary,
        public readonly ?string $projectMatch,
        public readonly float $projectConfidence,
        public readonly ?string $employeeMatch,
        public readonly float $employeeConfidence,
        public readonly array $tags,
        public readonly array $attachments,
        public readonly float $overallConfidence,
        public readonly int $schemaVersion,
        public readonly array $rawPayload
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            summary: $data['summary'] ?? null,
            projectMatch: $data['project']['matched'] ?? null,
            projectConfidence: (float) ($data['project']['confidence'] ?? 0.0),
            employeeMatch: $data['employee']['matched'] ?? null,
            employeeConfidence: (float) ($data['employee']['confidence'] ?? 0.0),
            tags: $data['tags'] ?? [],
            attachments: $data['attachments'] ?? [],
            overallConfidence: (float) ($data['confidence'] ?? 0.0),
            schemaVersion: (int) ($data['schema_version'] ?? 1),
            rawPayload: $data
        );
    }
}
