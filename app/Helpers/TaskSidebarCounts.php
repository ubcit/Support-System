<?php

namespace App\Helpers;

use Illuminate\Support\Collection;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class TaskSidebarCounts
{
    /**
     * Sidebar counts, computed once per request (the panel can render 3 times).
     *
     * @return array{
     *     all: int,
     *     mine: int,
     *     today: int,
     *     overdue: int,
     *     week: int,
     *     unassigned: int,
     *     completed: int,
     *     trashed: int,
     *     review: int,
     *     projects: Collection<int, array{id: int, name: string, count: int}>,
     *     employee: array|null
     * }
     */
    public static function for(?Employee $actor = null, ?bool $isWorkspace = null, string $tab = 'queue', ?int $selectedTaskId = null): array
    {
        $isWorkspace = $isWorkspace ?? request()->is('workspace*');
        $key = 'task.sidebar.counts.'.($actor?->id ?? 'anon').'.'.($isWorkspace ? '1' : '0').'.'.$tab.'.'.($selectedTaskId ?? 'none');

        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $open = Task::query()
            ->whereNull('archived_at')
            ->whereNull('parent_id')
            ->whereNull('completed_at');

        if ($actor) {
            $open->visibleTo($actor);
        }

        $today = now()->toDateString();
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        $mineQuery = (clone $open);
        if ($actor) {
            $mineQuery->whereHas('assignees', fn ($q) => $q->where('employees.id', $actor->id));
        } else {
            $mineQuery->whereRaw('1 = 0');
        }

        $reviewStateIds = TaskQuery::stateIdsForStatus('review');
        $projectCounts = (clone $open)
            ->selectRaw('project_id, count(*) as c')
            ->groupBy('project_id')
            ->pluck('c', 'project_id');

        $projects = Project::query()->orderBy('name')->get(['id', 'name'])->map(fn (Project $project) => [
            'id' => (int) $project->id,
            'name' => $project->name,
            'count' => (int) ($projectCounts[$project->id] ?? 0),
        ]);

        $trashedQuery = Task::onlyTrashed()->whereNull('parent_id');
        if ($actor) {
            $trashedQuery->visibleTo($actor);
        }

        $result = [
            'all' => (clone $open)->count(),
            'mine' => $mineQuery->count(),
            'today' => (clone $open)->whereDate('due_date', $today)->count(),
            'overdue' => (clone $open)->whereNotNull('due_date')->whereDate('due_date', '<', $today)->count(),
            'week' => (clone $open)->whereBetween('due_date', [$weekStart, $weekEnd])->count(),
            'unassigned' => (clone $open)->whereDoesntHave('assignees')->count(),
            'completed' => Task::query()->whereNull('archived_at')->whereNull('parent_id')->whereNotNull('completed_at')
                ->when($actor, fn ($q) => $q->visibleTo($actor))
                ->count(),
            'trashed' => $trashedQuery->count(),
            'review' => $reviewStateIds === []
                ? 0
                : (clone $open)->whereIn('current_state_id', $reviewStateIds)->count(),
            'projects' => $projects,
            'employee' => ($actor && $isWorkspace)
                ? static::employeeQueue($actor, $tab, $selectedTaskId)
                : null,
        ];

        request()->attributes->set($key, $result);

        return $result;
    }

    public static function forget(): void
    {
        foreach (array_keys(request()->attributes->all()) as $key) {
            if (is_string($key) && str_starts_with($key, 'task.sidebar.counts.')) {
                request()->attributes->remove($key);
            }
        }
    }

    /**
     * Assigned-to-me queues for the workspace My Tasks sidebar.
     *
     * @return array{
     *     queue: int,
     *     overdue: int,
     *     today: int,
     *     completed: int,
     *     tab: string,
     *     selected_id: int|null,
     *     tasks: list<array{id: int, title: string, project: ?string, due: ?string, overdue: bool}>
     * }
     */
    protected static function employeeQueue(Employee $actor, string $tab = 'queue', ?int $selectedId = null): array
    {
        $assigned = Task::query()
            ->whereNull('archived_at')
            ->whereNull('parent_id')
            ->whereHas('assignees', fn ($query) => $query->where('employees.id', $actor->id))
            ->with('project:id,name')
            ->orderBy('due_date')
            ->get(['id', 'title', 'project_id', 'due_date', 'completed_at']);

        $open = $assigned->whereNull('completed_at')->values();
        $today = now()->toDateString();
        $overdue = $open->filter(fn (Task $task) => $task->isOverdue())->values();
        $todayTasks = $open->filter(fn (Task $task) => $task->due_date && $task->due_date->toDateString() === $today)->values();
        $completed = $assigned->whereNotNull('completed_at')->values();

        if (! in_array($tab, ['queue', 'overdue', 'today', 'completed'], true)) {
            $tab = 'queue';
        }

        $tasks = match ($tab) {
            'overdue' => $overdue,
            'today' => $todayTasks,
            'completed' => $completed,
            default => $open,
        };

        if (! $selectedId || ! $tasks->contains(fn (Task $task) => (int) $task->id === (int) $selectedId)) {
            $selectedId = $tasks->first()?->id;
        }

        return [
            'queue' => $open->count(),
            'overdue' => $overdue->count(),
            'today' => $todayTasks->count(),
            'completed' => $completed->count(),
            'tab' => $tab,
            'selected_id' => $selectedId ? (int) $selectedId : null,
            'tasks' => $tasks->map(fn (Task $task) => [
                'id' => (int) $task->id,
                'title' => $task->title,
                'project' => $task->project?->name,
                'due' => $task->due_date?->format('M d'),
                'overdue' => $task->isOverdue(),
            ])->all(),
        ];
    }
}
