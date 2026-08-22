<?php

namespace Modules\Issues\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Issues\Enums\IssueStatus;
use Modules\Issues\Models\Issue;
use Modules\Issues\Requests\StoreIssueRequest;
use Modules\Issues\Requests\UpdateIssueRequest;
use Modules\Issues\Resources\IssueCommentResource;
use Modules\Issues\Resources\IssueResource;
use Modules\Issues\Services\IssueService;

class IssueController extends Controller
{
    public function __construct(
        protected IssueService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Issue::class);

        $issues = $this->service->list(
            filters: $request->only(['status', 'priority', 'source', 'project_id', 'customer_id', 'assigned_to']),
            search: $request->get('search'),
            sortBy: $request->get('sort_by'),
            direction: $request->get('direction', 'desc'),
            perPage: $request->integer('per_page', 15),
        );

        return IssueResource::collection($issues)->response();
    }

    public function store(StoreIssueRequest $request): JsonResponse
    {
        $issue = $this->service->create($request->validated());

        return (new IssueResource($issue))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): JsonResponse
    {
        $issue = $this->service->findByUuid($uuid);

        Gate::authorize('view', $issue);

        return (new IssueResource(
            $issue->load(['project', 'customer', 'reporter', 'assignee'])
                ->loadCount(['comments', 'attachments', 'tasks'])
        ))->response();
    }

    public function update(UpdateIssueRequest $request, string $uuid): JsonResponse
    {
        $issue = $this->service->update($uuid, $request->validated());

        return (new IssueResource($issue))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', $this->service->findByUuid($uuid));

        $this->service->delete($uuid);

        return response()->json(['message' => 'Issue deleted successfully']);
    }

    public function changeStatus(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('update', $this->service->findByUuid($uuid));

        $request->validate([
            'status' => ['required', 'string'],
        ]);

        $status = IssueStatus::from($request->status);
        $issue = $this->service->changeStatus($uuid, $status, $request->user()?->id);

        return (new IssueResource($issue))->response();
    }

    public function assign(Request $request, string $uuid): JsonResponse
    {
        Gate::authorize('update', $this->service->findByUuid($uuid));

        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        $issue = $this->service->assign($uuid, $request->employee_id, $request->user()?->id);

        return (new IssueResource($issue))->response();
    }

    public function comments(string $uuid): JsonResponse
    {
        $issue = $this->service->findByUuid($uuid);

        Gate::authorize('view', $issue);

        return IssueCommentResource::collection(
            $issue->comments()->with('employee')->latest()->get()
        )->response();
    }

    public function addComment(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string'],
            'is_internal' => ['boolean'],
            'employee_id' => ['nullable', 'exists:employees,id'],
        ]);

        $issue = $this->service->addComment($uuid, $request->only(['body', 'is_internal', 'employee_id']));

        return response()->json(['message' => 'Comment added successfully'], 201);
    }

    public function timeline(string $uuid): JsonResponse
    {
        $issue = $this->service->findByUuid($uuid);

        Gate::authorize('view', $issue);

        return response()->json([
            'data' => $issue->timeline()->with('employee')->get(),
        ]);
    }
}
