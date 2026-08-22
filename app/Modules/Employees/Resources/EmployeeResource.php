<?php

namespace Modules\Employees\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'department' => $this->department,
            'is_available' => $this->is_available,
            'max_workload' => $this->max_workload,
            'skills' => $this->whenLoaded('skills', fn () =>
                $this->skills->map(fn ($skill) => [
                    'skill' => $skill->skill,
                    'level' => $skill->level->value,
                ])
            ),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
