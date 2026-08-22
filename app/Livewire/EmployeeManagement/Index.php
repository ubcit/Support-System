<?php

namespace App\Livewire\EmployeeManagement;

use App\Livewire\Concerns\AuthorizesActions;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\Employees\Enums\SkillLevel;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Security\Models\Role;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskTimeLog;

class Index extends Component
{
    use AuthorizesActions;

    public ?int $selectedEmployeeId = null;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public string $formName = '';

    public string $formEmail = '';

    public string $formJobTitle = '';

    public string $formSystemRole = Role::EMPLOYEE;

    public string $formDepartment = '';

    public int $formMaxWorkload = 5;

    public string $formSkills = '';

    public bool $formIsAvailable = true;

    public function selectEmployee(int $id): void
    {
        $this->selectedEmployeeId = $id;
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->formMaxWorkload = 5;
        $this->formIsAvailable = true;
        $this->formSystemRole = Role::EMPLOYEE;
        $this->showCreateModal = true;
    }

    public function openEditModal(?int $employeeId = null): void
    {
        $id = $employeeId ?? $this->selectedEmployeeId;
        $employee = Employee::with(['skills', 'roles'])->find($id);
        if (! $employee) {
            return;
        }

        $this->formName = $employee->name ?? '';
        $this->formEmail = $employee->email ?? '';
        $this->formJobTitle = $employee->role ?? '';
        $this->formSystemRole = $employee->roles->first()?->slug ?? Role::EMPLOYEE;
        $this->formDepartment = $employee->department ?? '';
        $this->formMaxWorkload = $employee->max_workload ?? 5;
        $this->formSkills = $employee->skills->pluck('skill')->implode(', ');
        $this->formIsAvailable = (bool) $employee->is_available;
        $this->showEditModal = true;
    }

    public function hireEmployee(): void
    {
        $this->authorizePermission('employees.manage');
        $this->validate($this->formRules(uniqueEmail: true));

        $workspace = auth()->user()?->resolveEmployee()?->workspace ?? Workspace::first();
        $user = $this->resolveOrCreateUser($this->formName, $this->formEmail);
        $jobTitle = $this->resolvedJobTitle();

        $employee = Employee::create([
            'workspace_id' => $workspace?->id,
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => $this->formName,
            'email' => $this->formEmail,
            'role' => $jobTitle,
            'department' => $this->formDepartment ?: null,
            'max_workload' => $this->formMaxWorkload,
            'is_available' => $this->formIsAvailable,
        ]);

        $this->syncSystemRole($employee, $this->formSystemRole);
        $this->syncSkills($employee, $this->formSkills);

        $user->sendPasswordResetNotification(
            app('auth.password.broker')->createToken($user)
        );

        $this->selectedEmployeeId = $employee->id;
        $this->showCreateModal = false;
        $this->resetForm();
        session()->flash('success', 'Employee hired successfully. A password-reset email has been sent.');
    }

    public function editEmployee(): void
    {
        $this->authorizePermission('employees.manage');
        $employee = Employee::find($this->selectedEmployeeId);
        if (! $employee) {
            return;
        }

        $this->validate($this->formRules(uniqueEmail: false));

        if (! $employee->user_id) {
            $user = $this->resolveOrCreateUser($this->formName, $this->formEmail);
            $employee->user_id = $user->id;
        } elseif ($employee->user) {
            $employee->user->update([
                'name' => $this->formName,
                'email' => $this->formEmail,
            ]);
        }

        $employee->update([
            'name' => $this->formName,
            'email' => $this->formEmail,
            'role' => $this->resolvedJobTitle(),
            'department' => $this->formDepartment ?: null,
            'max_workload' => $this->formMaxWorkload,
            'is_available' => $this->formIsAvailable,
        ]);

        $this->syncSystemRole($employee, $this->formSystemRole);

        $employee->skills()->delete();
        $this->syncSkills($employee, $this->formSkills);

        $this->showEditModal = false;
        $this->resetForm();
        session()->flash('success', 'Profile & Skills Updated');
    }

    public function deleteEmployee(int $id): void
    {
        $this->authorizePermission('employees.manage');
        $employee = Employee::find($id);
        if (! $employee) {
            return;
        }

        $openTaskCount = Task::whereHas('assignees', fn ($q) => $q->where('employees.id', $employee->id))
            ->whereNull('completed_at')
            ->count();

        if ($openTaskCount > 0) {
            session()->flash('error', "Cannot offboard: employee has {$openTaskCount} open task(s). Reassign or complete them first.");

            return;
        }

        $employee->delete();
        if ($this->selectedEmployeeId === $id) {
            $this->selectedEmployeeId = null;
        }
        session()->flash('success', 'Employee Offboarded.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formRules(bool $uniqueEmail): array
    {
        return [
            'formName' => 'required|string|max:255',
            'formEmail' => $uniqueEmail
                ? ['required', 'email', 'unique:users,email']
                : ['required', 'email'],
            'formJobTitle' => 'nullable|string|max:255',
            'formSystemRole' => ['required', Rule::in(Role::systemSlugs())],
            'formDepartment' => 'nullable|string|max:255',
            'formMaxWorkload' => 'required|integer|min:1',
            'formSkills' => 'nullable|string',
            'formIsAvailable' => 'boolean',
        ];
    }

    protected function resolvedJobTitle(): string
    {
        $title = trim($this->formJobTitle);

        if ($title !== '') {
            return $title;
        }

        return match ($this->formSystemRole) {
            Role::ADMIN => 'Admin',
            Role::BOSS => 'Boss',
            Role::MANAGER => 'Manager',
            default => 'Employee',
        };
    }

    protected function syncSystemRole(Employee $employee, string $slug): void
    {
        $role = Role::where('slug', $slug)->first();
        if (! $role) {
            return;
        }

        $employee->roles()->sync([$role->id]);
    }

    protected function resolveOrCreateUser(string $name, string $email): User
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(16)),
        ]);
    }

    protected function syncSkills(Employee $employee, string $skillsCsv): void
    {
        $skills = array_filter(array_map('trim', explode(',', $skillsCsv)));
        foreach ($skills as $skillName) {
            if ($skillName !== '') {
                $employee->skills()->create([
                    'skill' => $skillName,
                    'level' => SkillLevel::Mid->value,
                ]);
            }
        }
    }

    protected function resetForm(): void
    {
        $this->formName = '';
        $this->formEmail = '';
        $this->formJobTitle = '';
        $this->formSystemRole = Role::EMPLOYEE;
        $this->formDepartment = '';
        $this->formMaxWorkload = 5;
        $this->formSkills = '';
        $this->formIsAvailable = true;
    }

    public function render()
    {
        $employees = Employee::with(['user'])->withCount([
            'assignments as active_tasks_count' => fn ($q) => $q->whereHas('task', fn ($t) => $t->whereNull('completed_at')),
            'assignments as completed_tasks_count' => fn ($q) => $q->whereHas('task', fn ($t) => $t->whereNotNull('completed_at')),
        ])->get();

        $selectedEmployee = null;
        if ($this->selectedEmployeeId) {
            $selectedEmployee = $employees->firstWhere('id', $this->selectedEmployeeId);
            if ($selectedEmployee) {
                $selectedEmployee->loadMissing(['skills', 'projects', 'assignments.task.project', 'roles', 'user']);
            }
        } else {
            $selectedEmployee = $employees->first();
            if ($selectedEmployee) {
                $this->selectedEmployeeId = $selectedEmployee->id;
                $selectedEmployee->loadMissing(['skills', 'projects', 'assignments.task.project', 'roles', 'user']);
            }
        }

        $activeTasks = collect();
        $completedTasks = collect();
        $timeLogs = collect();

        if ($selectedEmployee) {
            $activeTasks = $selectedEmployee->assignments
                ->filter(fn ($a) => $a->task && $a->task->completed_at === null)
                ->map(fn ($a) => $a->task)
                ->values();
            $completedTasks = $selectedEmployee->assignments
                ->filter(fn ($a) => $a->task && $a->task->completed_at !== null)
                ->map(fn ($a) => $a->task)
                ->values();
            $timeLogs = TaskTimeLog::where('employee_id', $selectedEmployee->id)->orderBy('created_at', 'desc')->limit(5)->get();
        }

        return view('livewire.employee-management.index', [
            'employees' => $employees,
            'selected_employee' => $selectedEmployee,
            'active_tasks' => $activeTasks,
            'completed_tasks' => $completedTasks,
            'time_logs' => $timeLogs,
            'systemRoles' => [
                Role::ADMIN => 'Admin',
                Role::BOSS => 'Boss',
                Role::MANAGER => 'Manager',
                Role::EMPLOYEE => 'Employee',
            ],
            'canManage' => auth()->user()?->hasPermission('employees.manage') ?? false,
        ]);
    }
}
