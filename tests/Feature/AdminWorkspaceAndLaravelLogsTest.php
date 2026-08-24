<?php

namespace Tests\Feature;

use App\Helpers\TaskNav;
use App\Livewire\LaravelLogs\Index as LaravelLogsIndex;
use App\Livewire\WorkerLogs\Index as WorkerLogsIndex;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\Notification;
use Modules\Projects\Models\Project;
use Modules\Security\Models\Role;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class AdminWorkspaceAndLaravelLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            EssentialPlatformSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserAndEmployeeSeeder::class,
        ]);
    }

    public function test_boss_can_render_laravel_log_viewer(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/admin/laravel-logs')
            ->assertOk()
            ->assertSee('Laravel Log Viewer', false);
    }

    public function test_employee_cannot_access_laravel_log_viewer(): void
    {
        $employee = User::where('email', 'ahmed@thespace.app')->firstOrFail();

        $this->actingAs($employee)
            ->get('/admin/laravel-logs')
            ->assertRedirect();
    }

    public function test_boss_can_clear_laravel_log(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $path = storage_path('logs/laravel.log');
        file_put_contents($path, "old log line that should be wiped\n");

        Livewire::actingAs($boss)
            ->test(LaravelLogsIndex::class)
            ->call('clear')
            ->assertSee('Laravel log cleared.');

        $this->assertStringNotContainsString(
            'old log line that should be wiped',
            file_get_contents($path) ?: ''
        );
    }

    public function test_boss_can_render_worker_log_viewer(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/admin/worker-logs')
            ->assertOk()
            ->assertSee('Worker Log Viewer', false);
    }

    public function test_employee_cannot_access_worker_log_viewer(): void
    {
        $employee = User::where('email', 'ahmed@thespace.app')->firstOrFail();

        $this->actingAs($employee)
            ->get('/admin/worker-logs')
            ->assertRedirect();
    }

    public function test_boss_can_clear_worker_log(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $path = storage_path('logs/worker.log');
        file_put_contents($path, "worker processed job that should be wiped\n");

        Livewire::actingAs($boss)
            ->test(WorkerLogsIndex::class)
            ->call('clear')
            ->assertSee('Worker log cleared.');

        $this->assertStringNotContainsString(
            'worker processed job that should be wiped',
            file_get_contents($path) ?: ''
        );
    }

    public function test_admin_visiting_workspace_my_tasks_redirects_to_admin_dashboard(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/workspace/my-tasks?view=board')
            ->assertRedirect(route('task-dashboard', ['view' => 'board']));
    }

    public function test_admin_visiting_workspace_task_detail_redirects_to_admin(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/workspace/tasks/42')
            ->assertRedirect(route('task-detail', ['record' => 42]));
    }

    public function test_admin_visiting_workspace_home_redirects_to_dashboard(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss)
            ->get('/workspace')
            ->assertRedirect(route('dashboard'));
    }

    public function test_employee_still_uses_workspace_routes(): void
    {
        $employee = User::where('email', 'ahmed@thespace.app')->firstOrFail();

        $this->actingAs($employee)
            ->get('/workspace')
            ->assertOk();

        $this->actingAs($employee);

        $this->assertTrue(TaskNav::usesWorkspaceTaskRoutes());
        $this->assertSame(
            route('workspace.task-detail', 7),
            TaskNav::detailUrl(7, [])
        );
    }

    public function test_task_assigned_notification_uses_admin_url_for_admin_recipient(): void
    {
        $workspace = Workspace::firstOrFail();
        $employeeRole = Role::where('slug', Role::EMPLOYEE)->firstOrFail();
        $adminRole = Role::where('slug', Role::ADMIN)->firstOrFail();

        $creatorUser = User::create([
            'name' => 'Creator Emp',
            'email' => 'creator-emp@thespace.app',
            'password' => Hash::make('password123'),
        ]);
        $creator = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $creatorUser->id,
            'workspace_id' => $workspace->id,
            'name' => 'Creator Emp',
            'email' => 'creator-emp@thespace.app',
            'role' => 'Employee',
        ]);
        $creator->roles()->sync([$employeeRole->id]);

        $adminUser = User::create([
            'name' => 'Admin Assignee',
            'email' => 'admin-assignee@thespace.app',
            'password' => Hash::make('password123'),
        ]);
        $adminEmployee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $adminUser->id,
            'workspace_id' => $workspace->id,
            'name' => 'Admin Assignee',
            'email' => 'admin-assignee@thespace.app',
            'role' => 'Admin',
        ]);
        $adminEmployee->roles()->sync([$adminRole->id]);

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Notify Project',
            'code' => 'NP-1',
            'status' => 'active',
        ]);
        $project->employees()->sync([$creator->id, $adminEmployee->id]);

        $workflow = Workflow::create([
            'name' => 'Default',
            'entity_type' => 'task',
            'is_default' => true,
        ]);
        $state = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);

        $this->actingAs($creatorUser);

        $task = app(NativeTaskService::class)->createTask([
            'title' => 'Assign admin',
            'project_id' => $project->id,
            'priority' => 'high',
            'current_state_id' => $state->id,
            'assignee_ids' => [$adminEmployee->id],
        ], $creator);

        $notification = Notification::query()
            ->where('type', 'task_assigned')
            ->where('employee_id', $adminEmployee->id)
            ->where('metadata->task_id', $task->id)
            ->firstOrFail();

        $this->assertSame(
            route('task-detail', $task->id),
            $notification->action_url
        );
    }

    public function test_detail_url_for_respects_recipient_access(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $employee = User::where('email', 'ahmed@thespace.app')->firstOrFail();

        $this->assertSame(
            route('task-detail', 9),
            TaskNav::detailUrlFor($boss, 9, [])
        );

        $this->assertSame(
            route('workspace.task-detail', 9),
            TaskNav::detailUrlFor($employee, 9, [])
        );
    }
}
