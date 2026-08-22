<?php

namespace Modules\Tasks\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tasks\Services\GanttTimelineService;

class TimelineController extends Controller
{
    public function __construct(
        protected GanttTimelineService $timelineService
    ) {}

    public function timeline(Request $request): JsonResponse
    {
        $projectId = $request->query('project_id');
        $data = $this->timelineService->getTimelineData($projectId ? (int) $projectId : null);

        return response()->json(['data' => $data]);
    }
}
