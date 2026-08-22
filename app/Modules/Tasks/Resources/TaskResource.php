<?php

namespace Modules\Tasks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'priority' => $this->priority?->value ?? $this->priority,
            'current_state_id' => $this->current_state_id,
            'parent_id' => $this->parent_id,
            'project_id' => $this->project_id,
            'milestone_id' => $this->milestone_id,
            'sprint_id' => $this->sprint_id,
            'due_date' => $this->due_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'estimated_hours' => (float) $this->estimated_hours,
            'actual_hours' => (float) $this->actual_hours,
            'progress' => $this->calculateProgress(),
            'is_overdue' => $this->isOverdue(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($a) => [
                'id' => $a->id,
                'uuid' => $a->uuid,
                'name' => $a->name,
                'email' => $a->email,
            ])),
            'subtasks' => TaskResource::collection($this->whenLoaded('subtasks')),
            'checklists' => $this->whenLoaded('checklists'),
            'dependencies' => $this->whenLoaded('dependencies'),
            'stakeholders' => $this->whenLoaded('stakeholders'),
            'tags' => $this->whenLoaded('tags'),
        ];
    }
}
