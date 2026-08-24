<?php

namespace Tests\Feature;

use App\Helpers\AppShell;
use App\Helpers\TaskNav;
use App\Helpers\TaskSidebarCounts;
use App\Livewire\AppShell\TasksPanel;
use App\Livewire\TaskDashboard\Index;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskNavigationTest extends TestCase
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

    public function test_task_detail_back_link_keeps_dashboard_filters(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Filter Memory']);
        $task = Task::factory()->create([
            'title' => 'Keep my filters',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss)
            ->get(route('task-detail', ['record' => $task->id, 'project' => $project->id, 'due' => 'today', 'view' => 'board']))
            ->assertOk()
            ->assertSee('due=today', false)
            ->assertSee('view=board', false)
            ->assertSee('project='.$project->id, false)
            ->assertDontSee('href="'.url('/admin/task-dashboard').'"', false);
    }

    public function test_task_detail_remembers_filters_from_my_tasks(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Opened from My Tasks',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss)
            ->get(route('task-dashboard', ['scope' => 'mine', 'due' => 'overdue']));

        $this->get(route('task-detail', $task->id))
            ->assertOk()
            ->assertSee('scope=mine', false)
            ->assertSee('due=overdue', false);
    }

    public function test_employee_my_tasks_uses_multi_view_dashboard_layout(): void
    {
        $employeeUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $employee = $employeeUser->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Employee Space']);
        $task = Task::factory()->create([
            'title' => 'Ahmed assigned sidebar task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $this->actingAs($employeeUser)
            ->get(route('workspace.employee'))
            ->assertOk()
            ->assertSee('My Tasks')
            ->assertSee('Ahmed assigned sidebar task')
            ->assertSee('List')
            ->assertSee('Board')
            ->assertSee('Table')
            ->assertSee('Calendar')
            ->assertSee('Timeline')
            ->assertSee('Assigned to me')
            ->assertDontSee('/admin/task-dashboard');

        $this->get(route('workspace.employee', ['view' => 'board']))
            ->assertOk()
            ->assertSee('Ahmed assigned sidebar task')
            ->assertDontSee('/admin/task-dashboard');
    }

    public function test_employee_workspace_dashboard_does_not_link_to_admin_tasks(): void
    {
        $employeeUser = User::where('email', 'sara@thespace.app')->firstOrFail();

        $this->actingAs($employeeUser)
            ->get(route('workspace.dashboard'))
            ->assertOk()
            ->assertDontSee('Multi-View')
            ->assertDontSee(route('task-dashboard'), false);
    }

    public function test_my_tasks_title_links_to_task_detail_and_edit_button_opens_modal(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Panel Project']);
        $task = Task::factory()->create([
            'title' => 'Open me in a panel',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $detailUrl = TaskNav::detailUrl($task->id, []);

        $this->actingAs($boss)
            ->get(route('task-dashboard'))
            ->assertOk()
            ->assertSee('Open me in a panel')
            ->assertSee($detailUrl, false)
            ->assertSee('wire:click.stop="openEditModal('.$task->id.')"', false)
            ->assertSee('Edit task')
            ->assertSee('The list stays open');

        $this->get(route('task-dashboard', ['task' => $task->id]))
            ->assertOk()
            ->assertSee('Open me in a panel')
            ->assertSee('The list stays open')
            ->assertSee('Open full task')
            ->assertSee(route('task-detail', $task->id), false);

        Livewire::actingAs($boss)
            ->test(Index::class)
            ->call('openEditModal', $task->id)
            ->assertSet('showEditModal', true)
            ->assertSet('editingTaskId', $task->id)
            ->assertSee('The list stays open')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false)
            ->assertSet('editingTaskId', null);
    }

    public function test_deleting_a_task_refreshes_the_tasks_sidebar(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $employeeUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $employee = $employeeUser->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Sidebar Delete Project']);
        $task = Task::factory()->create([
            'title' => 'Sidebar delete this task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $this->actingAs($employeeUser);
        $sidebar = Livewire::test(TasksPanel::class);
        $sidebar->set('isWorkspace', true)
            ->assertSee('Sidebar Delete Project')
            ->assertSee('Assigned to me');

        $this->assertSame(1, TaskSidebarCounts::for($employee, true)['mine']);

        Livewire::actingAs($boss)
            ->test(Index::class)
            ->call('deleteTask', $task->id)
            ->assertDispatched(AppShell::UPDATED_EVENT);

        AppShell::refresh(); // clears count caches in this test request
        $this->actingAs($employeeUser);
        $sidebar->dispatch(AppShell::UPDATED_EVENT)
            ->assertSee('Sidebar Delete Project');

        $this->assertSame(0, TaskSidebarCounts::for($employee, true)['mine']);
    }
}
