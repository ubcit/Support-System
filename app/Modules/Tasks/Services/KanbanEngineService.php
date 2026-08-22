<?php

namespace Modules\Tasks\Services;

use App\Helpers\TaskQuery;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Services\WorkflowManager;

class KanbanEngineService
{
    public function getBoardData(?int $projectId = null, ?int $workflowId = null, array $filters = []): array
    {
        $workflow = null;
        if ($workflowId) {
            $workflow = Workflow::with('states')->find($workflowId);
        } elseif ($projectId) {
            $project = Project::find($projectId);
            if ($project && $project->workflow_id) {
                $workflow = Workflow::with('states')->find($project->workflow_id);
            }
        }

        if (! $workflow) {
            $initial = app(WorkflowManager::class)->getDefaultState('task');
            $workflow = $initial?->workflow()->with('states')->first() ?? $initial?->workflow;
        }

        if (! $workflow) {
            $workflow = Workflow::with('states')->first();
        }

        $states = $workflow ? $workflow->states()->orderBy('order')->get() : collect();

        $filters['project_id'] = $filters['project_id'] ?? $projectId;
        $query = TaskQuery::dashboardQuery($filters, $filters['actor'] ?? null);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $tasks = $query->withCount(['attachments', 'comments'])
            ->with([
                'assignees.user',
                'checklists',
                'tags',
                'creator',
                'project',
                'reviewers',
                'currentState',
                'directSubtasks' => fn ($q) => $q
                    ->whereNull('archived_at')
                    ->with(['currentState', 'assignees.user'])
                    ->orderBy('sort_order')
                    ->orderBy('id'),
                'activityLogs' => fn ($q) => $q->where(function ($q) {
                    $q->whereIn('action', ['state_moved', 'bulk_status_changed'])
                        ->orWhere(function ($q) {
                            $q->where('action', 'field_updated')
                                ->whereIn('field', ['status', 'current_state_id']);
                        });
                })->orderBy('created_at'),
            ])->orderBy('sort_order')->take(200)->get();

        $firstState = $states->first();
        $columns = [];
        foreach ($states as $state) {
            $columnTasks = $tasks->filter(function ($t) use ($state, $firstState) {
                if ($t->current_state_id === $state->id) {
                    return true;
                }
                if ($firstState && $state->id === $firstState->id && ! $t->current_state_id) {
                    return true;
                }

                return false;
            })->values();

            $totalEstimated = $columnTasks->sum('estimated_hours');
            $overdueCount = $columnTasks->filter(fn ($t) => $t->isOverdue())->count();

            $columns[] = [
                'state_id' => $state->id,
                'state_name' => $state->name,
                'state_type' => $state->type,
                'position' => $state->order,
                'wip_limit' => $state->wip_limit ?? 0,
                'is_wip_exceeded' => ($state->wip_limit > 0) && ($columnTasks->count() > $state->wip_limit),
                'stats' => [
                    'count' => $columnTasks->count(),
                    'total_estimated_hours' => (float) $totalEstimated,
                    'overdue_count' => $overdueCount,
                ],
                'tasks' => $columnTasks,
            ];
        }

        return [
            'workflow_id' => $workflow?->id,
            'workflow_name' => $workflow?->name,
            'columns' => $columns,
        ];
    }

    public function moveCard(Task $task, int $targetStateId, int $newPosition = 0, ?Employee $employee = null): array
    {
        $currentStateId = $task->current_state_id;
        $targetState = WorkflowState::findOrFail($targetStateId);

        // WIP limit check
        if ($targetState->wip_limit > 0) {
            $currentCount = Task::where('current_state_id', $targetStateId)
                ->whereNull('archived_at')
                ->where('id', '!=', $task->id)
                ->count();

            if ($currentCount >= $targetState->wip_limit) {
                return [
                    'success' => false,
                    'message' => "WIP limit exceeded for column '{$targetState->name}'. Max allowed: {$targetState->wip_limit}",
                ];
            }
        }

        // Validate workflow transition using WorkflowManager if available
        $workflowManager = app(WorkflowManager::class);
        if ($currentStateId && $task->workflow_id) {
            if (! $workflowManager->canTransition($task->workflow_id, $currentStateId, $targetStateId)) {
                return [
                    'success' => false,
                    'message' => "Invalid transition from current state to '{$targetState->name}' according to Workflow Rules.",
                ];
            }
        }

        $oldStateId = (int) $task->current_state_id;
        $moved = app(NativeTaskService::class)->moveToState($task, $targetStateId, $employee);

        if ($oldStateId !== $targetStateId && (int) $moved->current_state_id !== $targetStateId) {
            return [
                'success' => false,
                'message' => 'This task needs manager approval before it can be marked done.',
            ];
        }

        $moved->update(['sort_order' => $newPosition]);

        return [
            'success' => true,
            'task' => $moved->fresh(['assignees', 'checklists']),
        ];
    }
}
