<?php

namespace Tests\Feature;

use App\Livewire\TaskDashboard\Index as TaskDashboard;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskDashboardAssigneeToggleTest extends TestCase
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

    public function test_list_modal_toggles_multiple_assignees_and_clears_all(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $first = $boss->resolveEmployee();
        $second = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Multi assignee toggle task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->assertCount(0, $task->fresh()->assignees);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('toggleTaskAssignee', $task->id, $first->id)
            ->call('toggleTaskAssignee', $task->id, $second->id);

        $assigneeIds = $task->fresh()->assignees->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $this->assertSame(
            collect([$first->id, $second->id])->map(fn ($id) => (int) $id)->sort()->values()->all(),
            $assigneeIds
        );

        Livewire::test(TaskDashboard::class)
            ->call('toggleTaskAssignee', $task->id, $first->id);

        $remaining = $task->fresh()->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([(int) $second->id], $remaining);

        Livewire::test(TaskDashboard::class)
            ->call('updateTaskAssignee', $task->id, null);

        $this->assertCount(0, $task->fresh()->assignees);
    }
}
