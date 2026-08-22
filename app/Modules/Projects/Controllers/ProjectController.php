<?php

namespace Modules\Projects\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Projects\Models\Project;
use Modules\Projects\Requests\StoreProjectRequest;
use Modules\Projects\Requests\UpdateProjectRequest;
use Modules\Projects\Resources\ProjectResource;
use Modules\Projects\Services\ProjectService;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $projects = $this->service->list(
            filters: $request->only(['status', 'customer_id', 'category_id']),
            search: $request->get('search'),
            sortBy: $request->get('sort_by'),
            direction: $request->get('direction', 'desc'),
            perPage: $request->integer('per_page', 15),
        );

        return ProjectResource::collection($projects)->response();
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->service->create($request->validated());

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): JsonResponse
    {
        $project = $this->service->findByUuid($uuid);

        Gate::authorize('view', $project);

        return (new ProjectResource(
            $project->load(['customer', 'category', 'aliases'])
                ->loadCount(['employees', 'issues', 'tasks'])
        ))->response();
    }

    public function update(UpdateProjectRequest $request, string $uuid): JsonResponse
    {
        $project = $this->service->update($uuid, $request->validated());

        return (new ProjectResource($project))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', $this->service->findByUuid($uuid));

        $this->service->delete($uuid);

        return response()->json(['message' => 'Project deleted successfully']);
    }
}
