<?php

namespace Modules\Tasks\Models;

use App\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employees\Models\Employee;

class TaskActivityLog extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'task_id',
        'employee_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Human-readable activity line (without the actor name).
     */
    public function summary(): string
    {
        return match ($this->action) {
            'task_created' => 'created this task',
            'task_archived' => 'archived this task',
            'task_deleted' => 'deleted this task',
            'task_approved' => 'approved this task',
            'changes_requested' => 'requested changes',
            'comment_added' => 'added a comment',
            'assignees_changed' => 'updated the assignees',
            'state_moved', 'state_changed', 'bulk_status_changed' => $this->statusChangeSummary(),
            'bulk_priority_changed' => 'changed the priority',
            'bulk_due_date_changed' => 'changed the due date',
            'field_updated' => $this->fieldUpdatedSummary(),
            default => str_replace('_', ' ', (string) $this->action),
        };
    }

    protected function statusChangeSummary(): string
    {
        $from = $this->resolveStateLabel($this->old_value);
        $to = $this->resolveStateLabel($this->new_value);

        if ($from && $to) {
            return "moved status from {$from} to {$to}";
        }

        if ($to) {
            return "moved status to {$to}";
        }

        return 'moved the status';
    }

    protected function fieldUpdatedSummary(): string
    {
        $field = (string) ($this->field ?? '');

        return match ($field) {
            'status', 'current_state_id' => $this->statusChangeSummary(),
            'priority' => $this->valueChangeSummary('priority'),
            'due_date' => $this->valueChangeSummary('due date'),
            'start_date' => $this->valueChangeSummary('start date'),
            'title' => 'updated the title',
            'description' => 'updated the description',
            'estimated_hours' => $this->valueChangeSummary('estimate'),
            'project_id' => 'updated the project',
            default => $field !== ''
                ? 'updated '.str_replace('_', ' ', $field)
                : 'updated a field',
        };
    }

    protected function valueChangeSummary(string $label): string
    {
        $old = $this->displayValue($this->old_value);
        $new = $this->displayValue($this->new_value);

        if ($old !== null && $new !== null) {
            return "changed {$label} from {$old} to {$new}";
        }

        if ($new !== null) {
            return "set {$label} to {$new}";
        }

        return "updated the {$label}";
    }

    protected function resolveStateLabel(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            static $stateNames = null;
            $stateNames ??= \Modules\Workflows\Models\WorkflowState::query()
                ->pluck('name', 'id')
                ->map(fn ($name) => (string) $name)
                ->all();

            return $stateNames[(int) $value] ?? null;
        }

        return $this->displayValue($value);
    }

    protected function displayValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = is_string($value) ? $value : (string) $value;

        return str_replace('_', ' ', $text);
    }
}
