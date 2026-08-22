<?php

namespace Modules\Tasks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskChecklistItem;
use Modules\Tasks\Resources\TaskResource;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Tasks\Services\TimeTrackingService;

class NativeTaskController extends Controller
{
    public function __construct(
        protected NativeTaskService $taskService,
        protected TimeTrackingService $timeTrackingService
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $query = Task::with(['assignees', 'checklists', 'tags', 'subtasks']);

        $actor = auth()->user()?->resolveEmployee();
        if ($actor) {
            $query->visibleTo($actor);
        }

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        if ($request->boolean('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        $tasks = $query->orderBy($request->query('sort_by', 'created_at'), $request->query('sort_order', 'desc'))
            ->paginate($request->query('per_page', 15));

        return response()->json(TaskResource::collection($tasks)->response()->getData(true));
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'type' => 'nullable|string',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:projects,id',
            'current_state_id' => 'nullable|exists:workflow_states,id',
            'priority' => 'nullable|string',
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:employees,id',
        ]);

        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }
        $task = $this->taskService->createTask($validated, $employee);

        return response()->json(['data' => new TaskResource($task)], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)
            ->with(['assignees', 'subtasks', 'checklists.items', 'dependencies.dependsOnTask', 'stakeholders.employee', 'timeLogs', 'tags', 'activityLogs'])
            ->firstOrFail();

        Gate::authorize('view', $task);

        return response()->json(['data' => new TaskResource($task)]);
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('update', $task);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'description' => 'nullable|string',
            'priority' => 'nullable|string',
            'current_state_id' => 'nullable|exists:workflow_states,id',
            'due_date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric',
        ]);

        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }

        if (array_key_exists('current_state_id', $validated) && $validated['current_state_id'] !== null) {
            $stateId = (int) $validated['current_state_id'];
            unset($validated['current_state_id']);
            $moved = $this->taskService->moveToState($task, $stateId, $employee);
            if ((int) $moved->current_state_id !== $stateId) {
                return response()->json([
                    'error' => 'This task needs manager approval before it can be marked done.',
                ], 422);
            }
            $task = $moved;
        }

        if ($validated !== []) {
            $task = $this->taskService->updateFields($task, $validated, $employee);
        }

        return response()->json(['data' => new TaskResource($task->fresh(['assignees', 'checklists']))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('delete', $task);

        $task->delete();

        return response()->json(['message' => 'Task soft deleted successfully']);
    }

    public function createSubtask(Request $request, string $uuid): JsonResponse
    {
        $parentTask = Task::where('uuid', $uuid)->firstOrFail();

        Gate::authorize('create', Task::class);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'priority' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }
        $subtask = $this->taskService->createSubtask($parentTask, $validated, $employee);

        return response()->json(['data' => new TaskResource($subtask)], 201);
    }

    public function toggleChecklist(Request $request, string $itemUuid): JsonResponse
    {
        $item = TaskChecklistItem::where('uuid', $itemUuid)->firstOrFail();
        $completed = $request->boolean('completed', true);
        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }

        $updatedItem = $this->taskService->toggleChecklistItem($item, $completed, $employee);

        return response()->json(['data' => $updatedItem]);
    }

    public function startTimer(Request $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();
        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }

        $log = $this->timeTrackingService->startTimer($task, $employee, $request->input('description'));

        return response()->json(['data' => $log]);
    }

    public function stopTimer(Request $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();
        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }

        $runningLog = $task->timeLogs()->where('employee_id', $employee->id)->where('is_running', true)->firstOrFail();
        $stoppedLog = $this->timeTrackingService->stopTimer($runningLog);

        return response()->json(['data' => $stoppedLog]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_uuids' => 'required|array',
            'state_id' => 'required|exists:workflow_states,id',
        ]);

        $employee = $this->actor();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }
        $count = $this->taskService->bulkUpdateStatus($validated['task_uuids'], $validated['state_id'], $employee);

        return response()->json(['message' => "Successfully updated {$count} tasks"]);
    }

    protected function actor(): ?Employee
    {
        return auth()->user()?->resolveEmployee();
    }
}
