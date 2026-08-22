<?php

namespace Modules\Issues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Issues\Enums\IssuePriority;
use Modules\Issues\Enums\IssueSource;
use Modules\Issues\Enums\IssueStatus;

class UpdateIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('issues.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:500'],
            'description' => ['sometimes', 'string'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'assigned_to' => ['nullable', 'exists:employees,id'],
            'status' => ['nullable', Rule::enum(IssueStatus::class)],
            'priority' => ['nullable', Rule::enum(IssuePriority::class)],
            'source' => ['nullable', Rule::enum(IssueSource::class)],
            'due_date' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
