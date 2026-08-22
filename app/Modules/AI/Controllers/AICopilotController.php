<?php

namespace Modules\AI\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Communication\Models\Conversation;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\TaskAICopilotService;

class AICopilotController extends Controller
{
    public function __construct(
        protected TaskAICopilotService $copilotService
    ) {}

    public function summarizeTask(string $uuid): JsonResponse
    {
        $task = Task::where('uuid', $uuid)->firstOrFail();
        $summary = $this->copilotService->summarizeTask($task);

        return response()->json(['data' => $summary]);
    }

    public function summarizeConversation(int $id): JsonResponse
    {
        $conversation = Conversation::with('messages')->findOrFail($id);
        $summary = $this->copilotService->summarizeConversation($conversation);

        return response()->json(['data' => $summary]);
    }

    public function summarizeProject(string $uuid): JsonResponse
    {
        $project = Project::where('uuid', $uuid)->firstOrFail();
        $summary = $this->copilotService->summarizeProject($project);

        return response()->json(['data' => $summary]);
    }

    public function generateChecklist(Request $request): JsonResponse
    {
        $request->validate(['title' => 'required|string']);
        $checklist = $this->copilotService->generateChecklist($request->input('title'), $request->input('description'));

        return response()->json(['data' => $checklist]);
    }

    public function generateReply(string $taskUuid): JsonResponse
    {
        $task = Task::where('uuid', $taskUuid)->firstOrFail();
        $reply = $this->copilotService->generateReply($task);

        return response()->json(['data' => $reply]);
    }
}
