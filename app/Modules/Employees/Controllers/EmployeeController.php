<?php

namespace Modules\Employees\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Employees\Models\Employee;
use Modules\Employees\Requests\StoreEmployeeRequest;
use Modules\Employees\Requests\UpdateEmployeeRequest;
use Modules\Employees\Resources\EmployeeResource;
use Modules\Employees\Services\EmployeeService;

class EmployeeController extends Controller
{
    public function __construct(
        protected EmployeeService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $employees = $this->service->list(
            filters: $request->only(['role', 'department', 'is_available']),
            search: $request->get('search'),
            sortBy: $request->get('sort_by'),
            direction: $request->get('direction', 'desc'),
            perPage: $request->integer('per_page', 15),
        );

        return EmployeeResource::collection($employees)->response();
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->service->create($request->validated());

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): JsonResponse
    {
        $employee = $this->service->findByUuid($uuid);

        Gate::authorize('view', $employee);

        return (new EmployeeResource($employee->load('skills')))->response();
    }

    public function update(UpdateEmployeeRequest $request, string $uuid): JsonResponse
    {
        $employee = $this->service->update($uuid, $request->validated());

        return (new EmployeeResource($employee))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', $this->service->findByUuid($uuid));

        $this->service->delete($uuid);

        return response()->json(['message' => 'Employee deleted successfully']);
    }

    public function tasks(string $uuid): JsonResponse
    {
        $employee = $this->service->findByUuid($uuid);

        Gate::authorize('view', $employee);

        $tasks = $employee->taskAssignments()
            ->whereNull('unassigned_at')
            ->with('task.project')
            ->get()
            ->pluck('task');

        return response()->json(['data' => $tasks]);
    }

    public function issues(string $uuid): JsonResponse
    {
        $employee = $this->service->findByUuid($uuid);

        Gate::authorize('view', $employee);

        return response()->json([
            'data' => $employee->assignedIssues()->with(['project', 'customer'])->get(),
        ]);
    }

    public function skills(string $uuid): JsonResponse
    {
        $employee = $this->service->findByUuid($uuid);

        Gate::authorize('view', $employee);

        return response()->json([
            'data' => $employee->skills,
        ]);
    }
}
