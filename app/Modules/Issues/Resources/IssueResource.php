<?php

namespace Modules\Issues\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Resources\CustomerResource;
use Modules\Employees\Resources\EmployeeResource;
use Modules\Projects\Resources\ProjectResource;

class IssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'raw_message' => $this->raw_message,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'reporter' => new EmployeeResource($this->whenLoaded('reporter')),
            'assignee' => new EmployeeResource($this->whenLoaded('assignee')),
            'status' => [
                'value' => $this->status?->value ?? \Modules\Issues\Enums\IssueStatus::New->value,
                'label' => ($this->status ?? \Modules\Issues\Enums\IssueStatus::New)->label(),
                'color' => ($this->status ?? \Modules\Issues\Enums\IssueStatus::New)->color(),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
                'color' => $this->priority->color(),
            ],
            'source' => [
                'value' => $this->source->value,
                'label' => $this->source->label(),
            ],
            'ai_summary' => $this->ai_summary,
            'ai_metadata' => $this->ai_metadata,
            'due_date' => $this->due_date?->toDateString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'metadata' => $this->metadata,
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
            'tasks_count' => $this->whenCounted('tasks'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
