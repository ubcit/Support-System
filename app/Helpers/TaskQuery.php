<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Builder;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Services\WorkflowManager;

class TaskQuery
{
    /**
     * Base My Tasks query: unarchived parent tasks, with dashboard filters applied.
     * Pass trashed=true to list soft-deleted parents instead.
     */
    public static function dashboardQuery(array $filters, ?Employee $actor = null): Builder
    {
        $trashed = (bool) ($filters['trashed'] ?? false);

        // Employees must not browse Trash; only privileged roles can.
        if ($trashed && $actor && ! $actor->isPrivileged()) {
            return Task::query()->whereRaw('0 = 1');
        }

        $query = $trashed
            ? Task::onlyTrashed()->whereNull('parent_id')
            : Task::query()->whereNull('archived_at')->whereNull('parent_id');

        if ($actor) {
            $query->visibleTo($actor);
        }

        if ($trashed) {
            // Trash lists all deleted parents; do not apply completed filters.
            $filters = array_merge($filters, [
                'show_completed' => true,
                'completed_only' => false,
            ]);
        }

        return static::applyFilters($query, $filters, $actor);
    }

    public static function applyFilters(Builder $query, array $filters, ?Employee $actor = null): Builder
    {
        $completedOnly = (bool) ($filters['completed_only'] ?? false);
        $showCompleted = (bool) ($filters['show_completed'] ?? false);

        if ($completedOnly) {
            $query->whereNotNull('completed_at');
        } elseif (! $showCompleted) {
            $query->whereNull('completed_at');
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $scope = $filters['scope'] ?? 'all';
        if ($scope === 'mine' && $actor) {
            $query->whereHas('assignees', fn ($q) => $q->where('employees.id', $actor->id));
        } elseif ($scope === 'created' && $actor) {
            $query->where('created_by', $actor->id);
        } elseif ($scope === 'unassigned') {
            $query->whereDoesntHave('assignees');
        } elseif (! empty($filters['assignee_id'])) {
            $query->whereHas('assignees', fn ($q) => $q->where('employees.id', $filters['assignee_id']));
        }

        $due = $filters['due'] ?? null;
        if ($due === 'today') {
            $query->whereDate('due_date', now()->toDateString());
        } elseif ($due === 'overdue') {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereNull('completed_at');
        } elseif ($due === 'week') {
            $query->whereBetween('due_date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ]);
        } elseif ($due === 'upcoming') {
            $query->whereDate('due_date', '>', now()->toDateString());
        }

        if (! empty($filters['status'])) {
            $stateIds = static::stateIdsForStatus((string) $filters['status']);
            if ($stateIds !== []) {
                $query->whereIn('current_state_id', $stateIds);
            }
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', (int) $filters['tag_id']));
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    public static function stateIdsForStatus(string $status): array
    {
        $names = match ($status) {
            'to_do', 'todo' => ['To Do', 'Todo'],
            'in_progress' => ['In Progress'],
            'code_review', 'review' => ['Review', 'Code Review'],
            'done' => ['Done'],
            default => [str_replace('_', ' ', ucwords($status, '_'))],
        };

        return WorkflowState::query()
            ->where(function ($query) use ($names) {
                foreach ($names as $name) {
                    $query->orWhere('name', $name)->orWhere('name', 'like', '%'.$name.'%');
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function defaultWorkflowStates()
    {
        $initial = app(WorkflowManager::class)->getDefaultState('task');
        if (! $initial) {
            return WorkflowState::query()->orderBy('order')->get();
        }

        return $initial->workflow->states()->orderBy('order')->get();
    }
}
