<?php

namespace Modules\Synchronization\DTOs;

use Modules\Synchronization\Mappings\SyncMapping;
use Modules\Tasks\Models\Task;

class SyncTaskDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $status,
        public readonly ?int $priority,
        public readonly array $tags
    ) {}

    public static function fromTask(Task $task): self
    {
        $priority = match ($task->priority?->value ?? $task->priority) {
            'urgent' => 1,
            'high' => 2,
            'medium' => 3,
            'low' => 4,
            default => 3,
        };

        $stateName = 'Open';
        if ($task->current_state_id) {
            $stateName = \Modules\Workflows\Models\WorkflowState::find($task->current_state_id)?->name ?? 'Open';
        }

        return new self(
            name: $task->title,
            description: $task->description ?? $task->summary ?? '',
            status: SyncMapping::taskStateToExternal($stateName),
            priority: $priority,
            tags: $task->metadata['ai_tags'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'tags' => $this->tags,
        ];
    }
}
