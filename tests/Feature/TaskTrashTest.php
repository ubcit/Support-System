<?php

namespace Tests\Feature;

use App\Helpers\TaskQuery;
use App\Helpers\TaskSidebarCounts;
use App\Livewire\TaskDashboard\Index;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskChecklist;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskTrashTest extends TestCase
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

    public function test_delete_preserves_assignees_and_checklists_and_restore_brings_them_back(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $employee = Employee::where('email', 'ahmed@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $service = app(NativeTaskService::class);

        $task = Task::factory()->create([
            'title' => 'Trash restore me',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);
        $checklist = TaskChecklist::create([
            'task_id' => $task->id,
            'title' => 'DoD',
            'sort_order' => 0,
        ]);
        $checklist->items()->create([
            'title' => 'Ship it',
            'sort_order' => 0,
        ]);
        $subtask = Task::factory()->create([
            'title' => 'Child of trash',
            'parent_id' => $task->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $service->deleteTask($task, $boss->resolveEmployee());

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSoftDeleted('tasks', ['id' => $subtask->id]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'employee_id' => $employee->id,
        ]);
        $this->assertDatabaseHas('task_checklists', [
            'id' => $checklist->id,
            'task_id' => $task->id,
        ]);
        $this->assertDatabaseHas('task_checklist_items', [
            'checklist_id' => $checklist->id,
            'title' => 'Ship it',
        ]);

        $service->restoreTask($task->fresh(), $boss->resolveEmployee());

        $this->assertNull($task->fresh()->deleted_at);
        $this->assertNull($subtask->fresh()->deleted_at);
        $this->assertTrue($task->fresh()->assignees->contains('id', $employee->id));
        $this->assertSame(1, $task->fresh()->checklists()->count());
        $this->assertDatabaseHas('task_activity_logs', [
            'task_id' => $task->id,
            'action' => 'task_restored',
        ]);
    }

    public function test_purge_removes_trash_older_than_retention_and_keeps_recent(): void
    {
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $service = app(NativeTaskService::class);

        $old = Task::factory()->create([
            'title' => 'Old trash',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $recent = Task::factory()->create([
            'title' => 'Recent trash',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $service->deleteTask($old);
        $service->deleteTask($recent);

        Task::onlyTrashed()->where('id', $old->id)->update([
            'deleted_at' => now()->subDays(31),
        ]);

        $purged = $service->purgeExpiredTrash(30);

        $this->assertSame(1, $purged);
        $this->assertDatabaseMissing('tasks', ['id' => $old->id]);
        $this->assertSoftDeleted('tasks', ['id' => $recent->id]);
    }

    public function test_dashboard_trash_filter_lists_and_restores_tasks(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Trash Project']);
        $task = Task::factory()->create([
            'title' => 'Visible in trash',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        app(NativeTaskService::class)->deleteTask($task, $boss->resolveEmployee());

        $this->assertSame(1, TaskSidebarCounts::for($boss->resolveEmployee(), false)['trashed']);

        $trashed = TaskQuery::dashboardQuery(['trashed' => true], $boss->resolveEmployee())->get();
        $this->assertTrue($trashed->contains('id', $task->id));

        Livewire::actingAs($boss)
            ->withQueryParams(['trashed' => 1])
            ->test(Index::class)
            ->assertSet('showTrashed', true)
            ->assertSee('Visible in trash')
            ->call('restoreTask', $task->id)
            ->assertSet('showTrashed', true);

        $this->assertNull($task->fresh()->deleted_at);
        $this->assertSame(0, TaskSidebarCounts::for($boss->resolveEmployee(), false)['trashed']);
    }

    public function test_employee_cannot_see_trash_or_permanently_delete(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $employeeUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $employee = $employeeUser->resolveEmployee();
        $this->assertNotNull($employee);
        $this->assertFalse($employee->isPrivileged());

        // Even with tasks.delete, trash browse / permanent delete stay manager-only.
        $employeeRole = \Modules\Security\Models\Role::where('slug', \Modules\Security\Models\Role::EMPLOYEE)->firstOrFail();
        $deletePerm = \Modules\Security\Models\Permission::where('slug', 'tasks.delete')->firstOrFail();
        $employeeRole->permissions()->syncWithoutDetaching([$deletePerm->id]);

        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $task = Task::factory()->create([
            'title' => 'Hidden from employee trash',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        app(NativeTaskService::class)->deleteTask($task, $boss->resolveEmployee());

        $this->assertSame(0, TaskSidebarCounts::for($employee, true)['trashed']);
        $this->assertTrue(
            TaskQuery::dashboardQuery(['trashed' => true], $employee)->get()->isEmpty()
        );

        Livewire::actingAs($employeeUser)
            ->withQueryParams(['trashed' => 1])
            ->test(Index::class)
            ->assertSet('showTrashed', false)
            ->assertDontSee('Hidden from employee trash')
            ->call('forceDeleteTask', $task->id);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_purge_trash_command_runs(): void
    {
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $task = Task::factory()->create([
            'title' => 'Command purge',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        app(NativeTaskService::class)->deleteTask($task);
        Task::onlyTrashed()->where('id', $task->id)->update([
            'deleted_at' => now()->subDays(40),
        ]);

        $this->artisan('tasks:purge-trash', ['--days' => 30])
            ->expectsOutputToContain('Purged 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
