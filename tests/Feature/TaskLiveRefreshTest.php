<?php

namespace Tests\Feature;

use App\Livewire\AppShell\TasksPanel;
use App\Livewire\TaskDashboard\Index as TaskDashboard;
use App\Livewire\TaskDetail\Index as TaskDetail;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskLiveRefreshTest extends TestCase
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

    public function test_task_detail_refresh_picks_up_remote_title_and_status_changes(): void
    {
        $viewer = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $actor = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Original title',
            'description' => 'Original description',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'status' => 'todo',
        ]);
        $task->assignments()->create([
            'employee_id' => $viewer->resolveEmployee()->id,
            'assigned_at' => now(),
        ]);

        $component = Livewire::actingAs($viewer)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->assertSet('taskTitle', 'Original title')
            ->assertSet('status', 'todo');

        app(NativeTaskService::class)->updateFields($task->fresh(), [
            'title' => 'Updated by teammate',
            'status' => 'in_progress',
            'current_state_id' => $inProgress->id,
        ], $actor);

        $component
            ->call('refreshFromServer')
            ->assertSet('taskTitle', 'Updated by teammate')
            ->assertSet('status', 'in_progress');
    }

    public function test_task_detail_refresh_preserves_dirty_description(): void
    {
        $viewer = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $actor = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Shared task',
            'description' => 'Server description',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create([
            'employee_id' => $viewer->resolveEmployee()->id,
            'assigned_at' => now(),
        ]);

        $component = Livewire::actingAs($viewer)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->assertSet('description', 'Server description')
            ->set('description', 'Local draft in progress');

        app(NativeTaskService::class)->updateFields($task->fresh(), [
            'title' => 'Remote title change',
        ], $actor);

        $component
            ->call('refreshFromServer')
            ->assertSet('description', 'Local draft in progress')
            ->assertSet('taskTitle', 'Remote title change');
    }

    public function test_task_dashboard_and_panel_include_live_poll(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        Livewire::actingAs($boss)
            ->test(TaskDashboard::class)
            ->assertOk()
            ->assertSeeHtml('wire:poll.5s');

        Livewire::actingAs($boss)
            ->test(TasksPanel::class)
            ->assertOk()
            ->assertSeeHtml('wire:poll.10s');
    }

    public function test_task_detail_includes_live_poll(): void
    {
        $viewer = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Poll attribute task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create([
            'employee_id' => $viewer->resolveEmployee()->id,
            'assigned_at' => now(),
        ]);

        Livewire::actingAs($viewer)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->assertOk()
            ->assertSeeHtml('wire:poll.5s="refreshFromServer"');
    }
}
