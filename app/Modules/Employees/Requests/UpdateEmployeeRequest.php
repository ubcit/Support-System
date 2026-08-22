<?php

namespace Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('employees.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['sometimes', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'is_available' => ['boolean'],
            'max_workload' => ['nullable', 'integer', 'min:1', 'max:100'],
            'skills' => ['nullable', 'array'],
            'skills.*.skill' => ['required_with:skills', 'string', 'max:255'],
            'skills.*.level' => ['required_with:skills', 'in:junior,mid,senior,expert'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
