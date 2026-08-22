<?php

namespace Modules\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Projects\Enums\ProjectStatus;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('projects.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'customer_id' => ['sometimes', 'nullable', 'exists:customers,id'],
            'category_id' => ['nullable', 'exists:project_categories,id'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'settings' => ['nullable', 'array'],
            'started_at' => ['nullable', 'date'],
            'deadline_at' => ['nullable', 'date'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
        ];
    }
}
