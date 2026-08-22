<?php

namespace App\Livewire\ProjectHub;

use App\Livewire\Concerns\AuthorizesActions;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Issues\Enums\IssueStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Models\Milestone;
use Modules\Tasks\Models\Sprint;
use Modules\Tasks\Models\Task;

class Index extends Component
{
    use AuthorizesActions;

    public ?int $selectedProjectId = null;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public string $name = '';

    public mixed $customer_id = null;

    public string $description = '';

    public string $status = 'active';

    public array $employees = [];

    protected function queryString(): array
    {
        return [
            'selectedProjectId' => ['as' => 'project', 'except' => null],
        ];
    }

    public function mount(): void
    {
        if (request()->filled('project')) {
            $projectId = (int) request('project');
            $this->selectedProjectId = Project::whereKey($projectId)->exists() ? $projectId : null;
        }

        if (request()->boolean('create')) {
            $this->openCreateModal();
        }
    }

    public function selectProject(int $id): void
    {
        $this->selectedProjectId = $id;
    }

    public function clearSelection(): void
    {
        $this->selectedProjectId = null;
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(?int $projectId = null): void
    {
        $project = Project::with('employees')->find($projectId ?? $this->selectedProjectId);
        if (! $project) {
            return;
        }

        $this->selectedProjectId = $project->id;
        $this->name = $project->name;
        $this->customer_id = $project->customer_id;
        $this->description = (string) $project->description;
        $this->status = $project->status instanceof \BackedEnum
            ? $project->status->value
            : (string) ($project->status ?? 'active');
        $this->employees = $project->employees->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showEditModal = true;
    }

    public function newProject(): void
    {
        $this->saveProject();
    }

    public function editProject(): void
    {
        $this->updateProject();
    }

    public function saveProject(): void
    {
        $this->authorizePermission('projects.manage');
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,on_hold,paused,completed'],
            'employees' => ['array'],
        ]);

        $status = $this->status === 'on_hold' ? 'paused' : $this->status;

        // workspace_id is intentionally omitted here: Modules\MultiTenancy\Traits\
        // BelongsToWorkspace auto-stamps it from the creating user's own Employee
        // record (see Phase 3c). This used to hardcode Workspace::first()?->id,
        // which silently misassigned every project created here to whichever
        // workspace was created first, regardless of which workspace the actual
        // creator belonged to.
        $data = [
            'name' => $this->name,
            'customer_id' => $this->customer_id ?: null,
            'description' => $this->description,
            'status' => $status,
            'uuid' => (string) Str::uuid(),
        ];

        $project = app(ProjectService::class)->create($data);

        if (! empty($this->employees)) {
            app(ProjectService::class)->syncEmployees($project, array_map('intval', $this->employees));
        }

        $this->selectedProjectId = $project->id;
        $this->showCreateModal = false;
        $this->resetForm();
        session()->flash('success', 'Project created successfully');
    }

    public function updateProject(): void
    {
        $this->authorizePermission('projects.manage');
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,on_hold,paused,completed'],
            'employees' => ['array'],
        ]);

        $project = Project::find($this->selectedProjectId);
        if (! $project) {
            return;
        }

        $status = $this->status === 'on_hold' ? 'paused' : $this->status;
        $customerId = $this->customer_id ?: null;

        $project->update([
            'name' => $this->name,
            'customer_id' => $customerId,
            'description' => $this->description,
            'status' => $status,
        ]);
        app(ProjectService::class)->syncEmployees($project, array_map('intval', $this->employees));
        if ($customerId) {
            app(ProjectService::class)->attachCustomer($project->fresh(), Customer::find($customerId));
        }

        $this->showEditModal = false;
        $this->resetForm();
        session()->flash('success', 'Project Updated');
    }

    public function archiveProject(int $id): void
    {
        $this->authorizePermission('projects.manage');
        $project = Project::find($id);
        if ($project) {
            $project->delete();
            if ($this->selectedProjectId === $id) {
                $this->selectedProjectId = null;
            }
            session()->flash('success', 'Project archived.');
        }
    }

    protected function resetForm(): void
    {
        $this->reset(['name', 'customer_id', 'description', 'employees']);
        $this->status = 'active';
    }

    public function render()
    {
        $selectedProject = $this->selectedProjectId
            ? Project::with(['issues', 'customer', 'customers', 'employees.user'])->find($this->selectedProjectId)
            : null;

        $milestones = collect();
        $visibleTasks = collect();
        $sprints = collect();
        $members = collect();
        $issues = collect();
        $openIssues = collect();
        $memberStats = collect();
        $progress = 0;
        $healthStatus = 'No Data';
        $overdueCount = 0;
        $unassignedOpen = 0;
        $openIssueCount = 0;
        $completedCount = 0;
        $totalTasks = 0;
        $projects = collect();

        if ($selectedProject) {
            $milestones = Milestone::with('tasks')->where('project_id', $selectedProject->id)->get();
            $sprints = Sprint::with('tasks')->where('project_id', $selectedProject->id)->get();
            $members = $selectedProject->employees;
            $issues = $selectedProject->issues;
            $openIssueStatuses = [
                IssueStatus::New,
                IssueStatus::Open,
                IssueStatus::InProgress,
                IssueStatus::Waiting,
            ];
            $openIssues = $issues->filter(fn ($issue) => in_array($issue->status, $openIssueStatuses, true));
            $openIssueCount = $openIssues->count();

            $projectTaskBase = Task::where('project_id', $selectedProject->id)->whereNull('archived_at');
            $totalTasks = (clone $projectTaskBase)->count();
            $completedCount = (clone $projectTaskBase)->whereNotNull('completed_at')->count();
            $overdueCount = (clone $projectTaskBase)->whereNotNull('due_date')->where('due_date', '<', now()->startOfDay())->whereNull('completed_at')->count();
            $unassignedOpen = (clone $projectTaskBase)->whereNull('completed_at')->whereDoesntHave('assignees')->count();
            $progress = $totalTasks > 0 ? (int) round(($completedCount / $totalTasks) * 100) : 0;

            $healthStatus = match (true) {
                $totalTasks === 0 => 'No Data',
                $overdueCount > 0 || $unassignedOpen > 0 => 'At Risk',
                default => 'Healthy',
            };

            $memberStats = $members->map(function ($member) use ($selectedProject) {
                $base = Task::where('project_id', $selectedProject->id)->whereNull('archived_at')
                    ->whereHas('assignees', fn ($q) => $q->where('employees.id', $member->id));

                return [
                    'employee' => $member,
                    'active' => (clone $base)->whereNull('completed_at')->count(),
                    'overdue' => (clone $base)->whereNotNull('due_date')->where('due_date', '<', now()->startOfDay())->whereNull('completed_at')->count(),
                    'done' => (clone $base)->whereNotNull('completed_at')->count(),
                ];
            });

            $visibleTasks = Task::with(['assignees', 'currentState'])
                ->where('project_id', $selectedProject->id)
                ->whereNull('archived_at')
                ->orderByRaw('CASE WHEN due_date IS NOT NULL AND due_date < ? AND completed_at IS NULL THEN 0 WHEN completed_at IS NULL THEN 1 ELSE 2 END', [now()->toDateString()])
                ->latest()
                ->take(20)
                ->get();
        } else {
            $today = now()->toDateString();
            $projects = Project::query()
                ->with(['customer', 'customers', 'employees'])
                ->withCount([
                    'tasks as total_tasks_count' => fn ($q) => $q->whereNull('archived_at'),
                    'tasks as active_tasks_count' => fn ($q) => $q->whereNull('archived_at')->whereNull('completed_at'),
                    'tasks as completed_tasks_count' => fn ($q) => $q->whereNull('archived_at')->whereNotNull('completed_at'),
                    'tasks as overdue_tasks_count' => fn ($q) => $q->whereNull('archived_at')
                        ->whereNull('completed_at')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', $today),
                    'employees as members_count',
                ])
                ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
                ->orderByDesc('updated_at')
                ->get();
        }

        return view('livewire.project-hub.index', [
            'selected_project' => $selectedProject,
            'projects' => $projects,
            'milestones' => $milestones,
            'tasks' => $visibleTasks,
            'visible_tasks' => $visibleTasks,
            'sprints' => $sprints,
            'members' => $members,
            'member_stats' => $memberStats,
            'issues' => $issues,
            'open_issues' => $openIssues,
            'progress' => $progress,
            'health_status' => $healthStatus,
            'overdue_count' => $overdueCount,
            'unassigned_open' => $unassignedOpen,
            'open_issue_count' => $openIssueCount,
            'completed_count' => $completedCount,
            'total_tasks' => $totalTasks,
            'customerOptions' => Customer::query()->pluck('name', 'id'),
            'employeeOptions' => Employee::query()->pluck('name', 'id'),
            'canManage' => auth()->user()?->hasPermission('projects.manage') ?? false,
        ]);
    }
}
