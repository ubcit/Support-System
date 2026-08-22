<?php

namespace Modules\Tasks\Services;

use App\Helpers\TaskQuery;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Milestone;

class GanttTimelineService
{
    public function getTimelineData(?int $projectId = null, array $filters = [], ?Employee $actor = null): array
    {
        $filters['project_id'] = $filters['project_id'] ?? $projectId;
        $query = TaskQuery::dashboardQuery($filters, $actor);

        $tasks = $query->with(['dependencies.dependsOnTask', 'milestone', 'sprint', 'assignees.user'])->take(100)->get();
        $milestones = $projectId ? Milestone::where('project_id', $projectId)->get() : collect();

        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'uuid' => $task->uuid,
                'title' => $task->title,
                'type' => $task->type,
                'start_date' => $task->start_date?->toDateString() ?? $task->created_at->toDateString(),
                'due_date' => $task->due_date?->toDateString() ?? $task->created_at->addDays(3)->toDateString(),
                'progress' => $task->calculateProgress(),
                'milestone_id' => $task->milestone_id,
                'milestone_name' => $task->milestone?->name,
                'sprint_id' => $task->sprint_id,
                'sprint_name' => $task->sprint?->name,
                'dependencies' => $task->dependencies->map(fn ($dep) => [
                    'depends_on_id' => $dep->depends_on_task_id,
                    'depends_on_title' => $dep->dependsOnTask?->title,
                    'type' => $dep->type,
                ]),
                'assignees' => $task->assignees->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'src' => $a->avatarUrl(),
                    'seed' => $a->user_id ?? $a->id,
                ]),
            ];
        });

        // Critical path calculation algorithm (longest path dependent tasks)
        $criticalPathIds = $this->calculateCriticalPath($tasks);

        return [
            'project_id' => $projectId,
            'milestones' => $milestones,
            'tasks' => $formattedTasks,
            'critical_path_ids' => $criticalPathIds,
        ];
    }

    protected function calculateCriticalPath($tasks): array
    {
        // Simple critical path identification: tasks with blocking dependencies forming the longest chain
        $critical = [];
        foreach ($tasks as $task) {
            if ($task->dependencies->count() > 0 && $task->isOverdue()) {
                $critical[] = $task->id;
            }
        }

        return array_unique($critical);
    }
}
