<?php

namespace Modules\Projects\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Resources\CustomerResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'aliases' => $this->whenLoaded('aliases', fn () => $this->aliases->pluck('alias')
            ),
            'settings' => $this->settings,
            'started_at' => $this->started_at?->toISOString(),
            'deadline_at' => $this->deadline_at?->toISOString(),
            'employees_count' => $this->whenCounted('employees'),
            'issues_count' => $this->whenCounted('issues'),
            'tasks_count' => $this->whenCounted('tasks'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
