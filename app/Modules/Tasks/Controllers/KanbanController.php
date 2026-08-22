<?php

namespace Modules\Tasks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\KanbanEngineService;

class KanbanController extends Controller
{
    public function __construct(
        protected KanbanEngineService $kanbanService
    ) {}

    public function board(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id');
        $workflowId = $request->query('workflow_id');
        $filters = [
            'priority' => $request->query('priority'),
            'assignee_id' => $request->query('assignee_id'),
            'type' => $request->query('type'),
        ];

        $boardData = $this->kanbanService->getBoardData(
            $projectId ? (int) $projectId : null,
            $workflowId ? (int) $workflowId : null,
            $filters
        );

        return response()->json(['data' => $boardData]);
    }

    public function moveCard(Request $request, string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();
        $targetStateId = (int) $request->input('target_state_id');
        $newPosition = (int) $request->input('position', 0);
        $employee = auth()->user()?->resolveEmployee();
        if (! $employee) {
            return response()->json(['error' => 'Employee profile not found'], 403);
        }

        $result = $this->kanbanService->moveCard($task, $targetStateId, $newPosition, $employee);

        if (!$result['success']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json(['data' => $result['task']]);
    }
}
