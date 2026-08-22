<?php

namespace Modules\Tasks\Models;

use App\Shared\Traits\Filterable;
use App\Shared\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Attachments\Models\Attachment;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\MultiTenancy\Traits\BelongsToWorkspace;
use Modules\Projects\Models\Project;
use Modules\Security\Models\Role;
use Modules\Tasks\Enums\TaskPriority;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Support\TaskLifecycle;
use Modules\Workflows\Models\WorkflowState;

class Task extends Model
{
    use BelongsToWorkspace, Filterable, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'issue_id',
        'project_id',
        'milestone_id',
        'sprint_id',
        'type',
        'title',
        'summary',
        'description',
        'workflow_id',
        'current_state_id',
        'priority',
        'sync_status',
        'created_by',
        'due_date',
        'start_date',
        'recurrence_rule',
        'recurrence_pattern',
        'archived_at',
        'wip_limit',
        'sort_order',
        'estimated_hours',
        'actual_hours',
        'started_at',
        'completed_at',
        'metadata',
        'custom_fields',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
        'due_date' => 'date',
        'start_date' => 'date',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
        'recurrence_pattern' => 'array',
        'metadata' => 'array',
        'custom_fields' => 'array',
    ];

    protected array $filterable = [
        'status',
        'priority',
        'type',
        'project_id',
        'milestone_id',
        'sprint_id',
        'issue_id',
        'created_by',
    ];

    protected array $searchable = [
        'title',
        'description',
        'summary',
    ];

    protected array $sortable = [
        'title',
        'status',
        'priority',
        'due_date',
        'start_date',
        'sort_order',
        'created_at',
        'updated_at',
        'completed_at',
    ];

    /**
     * Scope tasks to those visible to the given employee.
     *
     * Privileged roles (admin/boss/manager) see all workspace tasks.
     * Employees see tasks assigned to them OR belonging to a project
     * they are a member of (via employee_project).
     */
    public function scopeVisibleTo($query, Employee $actor): void
    {
        if ($actor->isPrivileged()) {
            return;
        }

        $projectIds = $actor->projects()->pluck('projects.id');

        $query->where(function ($q) use ($actor, $projectIds) {
            $q->whereHas('assignees', fn ($a) => $a->where('employees.id', $actor->id));

            if ($projectIds->isNotEmpty()) {
                $q->orWhereIn('project_id', $projectIds);
            }
        });
    }

    public function currentState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'current_state_id');
    }

    public function getStatusAttribute(): TaskStatus
    {
        if ($this->currentState) {
            $slug = Str::slug($this->currentState->name, '_');
            $normalized = match ($slug) {
                'to_do', 'todo' => TaskStatus::Todo->value,
                'in_progress' => TaskStatus::InProgress->value,
                'review', 'code_review', 'in_review' => TaskStatus::Review->value,
                'done' => TaskStatus::Done->value,
                'cancelled', 'canceled' => TaskStatus::Cancelled->value,
                default => $slug,
            };

            return TaskStatus::tryFrom($normalized) ?? TaskStatus::Todo;
        }

        return TaskStatus::Todo;
    }

    /**
     * Status slug used by the dashboard UI (to_do / code_review), distinct
     * from the enum values (todo / review).
     */
    public function statusKey(): string
    {
        return match ($this->status) {
            TaskStatus::Todo => 'to_do',
            TaskStatus::Review => 'code_review',
            default => $this->status->value,
        };
    }

    public function setStatusAttribute($value): void
    {
        $val = $value instanceof TaskStatus ? $value->value : (string) $value;
        $normalizedName = match ($val) {
            'to_do' => 'To Do',
            'in_progress' => 'In Progress',
            'code_review', 'review' => 'Review',
            'done' => 'Done',
            default => str_replace('_', ' ', ucwords($val, '_')),
        };

        $state = WorkflowState::where('name', $normalizedName)->first()
            ?? WorkflowState::where('name', 'like', "%{$normalizedName}%")->first();

        if ($state) {
            $this->attributes['current_state_id'] = $state->id;
            $this->attributes['workflow_id'] = $state->workflow_id;
        }
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->with('subtasks');
    }

    /**
     * Direct children only (no recursive eager-load). Prefer this for list UIs.
     */
    public function directSubtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignments')
            ->withPivot('assigned_by', 'assigned_at', 'unassigned_at')
            ->wherePivotNull('unassigned_at');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class)->with('items');
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    public function dependedOnBy(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(TaskStakeholder::class);
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(TaskStakeholder::class)->where('role', 'watcher');
    }

    public function reviewers(): HasMany
    {
        return $this->hasMany(TaskStakeholder::class)->where('role', 'reviewer');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TaskTimeLog::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TaskActivityLog::class)->orderBy('created_at', 'desc');
    }

    public function employeeStates(): HasMany
    {
        return $this->hasMany(EmployeeTaskState::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null || $this->status === TaskStatus::Done;
    }

    public function isOverdue(): bool
    {
        return $this->dueUrgency() === 'overdue';
    }

    /**
     * Due-date urgency for UI coloring.
     *
     * @return 'overdue'|'today'|'tomorrow'|null  null = none, completed, or later than tomorrow
     */
    public function dueUrgency(): ?string
    {
        return static::urgencyForDueDate($this->due_date, (bool) $this->completed_at);
    }

    /**
     * @return 'overdue'|'today'|'tomorrow'|null
     */
    public static function urgencyForDueDate(mixed $dueDate, bool $completed = false): ?string
    {
        if ($completed || ! $dueDate) {
            return null;
        }

        $due = \Illuminate\Support\Carbon::parse($dueDate)->startOfDay();
        $today = now()->startOfDay();

        if ($due->lt($today)) {
            return 'overdue';
        }

        if ($due->equalTo($today)) {
            return 'today';
        }

        if ($due->equalTo($today->copy()->addDay())) {
            return 'tomorrow';
        }

        return null;
    }

    /**
     * Tailwind classes for due-date pills / text.
     *
     * @param  'pill'|'text'  $variant
     */
    public function dueDateToneClasses(string $variant = 'pill'): string
    {
        return static::toneClassesForUrgency($this->dueUrgency(), $variant, (bool) $this->due_date);
    }

    /**
     * @param  'overdue'|'today'|'tomorrow'|null  $urgency
     * @param  'pill'|'text'  $variant
     */
    public static function toneClassesForUrgency(?string $urgency, string $variant = 'pill', bool $hasDate = true): string
    {
        if ($variant === 'text') {
            return match ($urgency) {
                'overdue' => 'text-red-600 dark:text-red-400 font-bold',
                'today' => 'text-yellow-600 dark:text-yellow-400 font-semibold',
                'tomorrow' => 'text-cyan-600 dark:text-cyan-400 font-semibold',
                default => $hasDate
                    ? 'text-gray-500 dark:text-gray-400'
                    : 'text-gray-400 dark:text-gray-500',
            };
        }

        return match ($urgency) {
            'overdue' => 'bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400 font-bold border-red-200 dark:border-red-800',
            'today' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-950/40 dark:text-yellow-400 font-semibold border-yellow-200 dark:border-yellow-800',
            'tomorrow' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-400 font-semibold border-cyan-200 dark:border-cyan-800',
            default => $hasDate
                ? 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'
                : 'text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 border-transparent hover:border-gray-200 dark:hover:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800',
        };
    }

    public function latestChangesRequest(): ?TaskStakeholder
    {
        return $this->reviewers
            ->where('approval_status', 'changes_requested')
            ->sortByDesc(fn (TaskStakeholder $reviewer) => $reviewer->updated_at?->timestamp ?? 0)
            ->first();
    }

    public function isLinkedToConversationSession(): bool
    {
        return (int) ($this->metadata['conversation_session_id'] ?? 0) > 0;
    }

    public function mustPassReview(): bool
    {
        $reviewers = $this->relationLoaded('reviewers')
            ? $this->reviewers
            : $this->reviewers()->get();

        if ($reviewers->contains(fn (TaskStakeholder $reviewer) => $reviewer->approval_status === 'approved')) {
            return false;
        }

        if ($reviewers->isNotEmpty() || $this->isLinkedToConversationSession()) {
            return true;
        }

        $stateName = strtolower((string) ($this->currentState?->name ?? ''));

        return str_contains($stateName, 'review')
            || $this->status === TaskStatus::Review;
    }

    /**
     * Employees who can be @mentioned on this task: assignees, stakeholders,
     * creator, project members, and managers.
     *
     * @return list<int>
     */
    public function accessibleEmployeeIds(): array
    {
        $ids = collect();

        $ids = $ids->merge($this->assignees()->pluck('employees.id'));
        $ids = $ids->merge($this->stakeholders()->pluck('employee_id'));

        if ($this->created_by) {
            $ids->push((int) $this->created_by);
        }

        if ($this->project_id) {
            $this->loadMissing('project.employees');
            $ids = $ids->merge($this->project?->employees->pluck('id') ?? collect());
        }

        $managerIds = Employee::query()
            ->where(function ($query) {
                $privileged = Role::privileged();
                $query->where(function ($roleQuery) use ($privileged) {
                    foreach ($privileged as $term) {
                        $roleQuery->orWhere('role', 'like', '%'.$term.'%');
                    }
                })->orWhereHas('roles', fn ($roles) => $roles->whereIn('slug', $privileged));
            })
            ->pluck('id');

        return $ids->merge($managerIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function calculateProgress(): int
    {
        $totalItems = 0;
        $completedItems = 0;

        foreach ($this->checklists as $checklist) {
            foreach ($checklist->items as $item) {
                $totalItems++;
                if ($item->is_completed) {
                    $completedItems++;
                }
            }
        }

        if ($totalItems === 0) {
            return $this->completed_at ? 100 : 0;
        }

        return (int) round(($completedItems / $totalItems) * 100);
    }

    /**
     * Status dwell segments reconstructed from `task_activity_logs`.
     *
     * Used by the Task status timeline UI to answer:
     * - "How long until complete?"
     * - "How long spent in each workflow state?"
     *
     * @return array<int, array{
     *   status: TaskStatus,
     *   label: string,
     *   entered_at: Carbon,
     *   exited_at: ?Carbon,
     *   seconds: int,
     *   duration_label: string,
     *   is_current: bool
     * }>
     */
    public function lifecycleSegments(?Carbon $asOf = null): array
    {
        return app(TaskLifecycle::class)->segments($this, $asOf);
    }

    public function cycleDurationLabel(?Carbon $asOf = null): string
    {
        $lifecycle = app(TaskLifecycle::class);
        $seconds = $lifecycle->cycleSeconds($this, $asOf);

        return $lifecycle->format($seconds);
    }

    public function currentStatusDurationLabel(?Carbon $asOf = null): string
    {
        $lifecycle = app(TaskLifecycle::class);
        $seconds = $lifecycle->currentStatusSeconds($this, $asOf);

        return $lifecycle->format($seconds);
    }
}
