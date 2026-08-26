<?php

namespace Tests\Feature;

use App\Livewire\TaskDashboard\Index as TaskDashboard;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskDragReorderNestTest extends TestCase
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

    public function test_reorder_tasks_persists_sort_order(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $a = Task::factory()->create([
            'title' => 'Alpha',
            'sort_order' => 0,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $b = Task::factory()->create([
            'title' => 'Beta',
            'sort_order' => 1,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $c = Task::factory()->create([
            'title' => 'Gamma',
            'sort_order' => 2,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('reorderTasks', [$c->id, $a->id, $b->id]);

        $this->assertSame(0, (int) $c->fresh()->sort_order);
        $this->assertSame(1, (int) $a->fresh()->sort_order);
        $this->assertSame(2, (int) $b->fresh()->sort_order);
    }

    public function test_nest_task_under_root_and_detach(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $parent = Task::factory()->create([
            'title' => 'Parent',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $child = Task::factory()->create([
            'title' => 'Child candidate',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('nestTask', $child->id, $parent->id);

        $this->assertSame($parent->id, (int) $child->fresh()->parent_id);

        Livewire::test(TaskDashboard::class)
            ->call('detachSubtask', $child->id);

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_attach_as_subtask_rejects_invalid_nests(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $service = app(NativeTaskService::class);

        $root = Task::factory()->create([
            'title' => 'Root',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $sub = Task::factory()->create([
            'title' => 'Already sub',
            'parent_id' => $root->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $withKids = Task::factory()->create([
            'title' => 'Has kids',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        Task::factory()->create([
            'title' => 'Grand',
            'parent_id' => $withKids->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $other = Task::factory()->create([
            'title' => 'Other root',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        try {
            $service->attachAsSubtask($other, $sub, $boss);
            $this->fail('Expected nest under subtask to fail');
        } catch (InvalidArgumentException) {
            // expected
        }

        try {
            $service->attachAsSubtask($withKids, $other, $boss);
            $this->fail('Expected nesting a parent with children to fail');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertNull($other->fresh()->parent_id);
        $this->assertNull($withKids->fresh()->parent_id);
    }

    public function test_cross_group_status_drop_still_works(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Move me',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('moveTaskToState', $task->id, $inProgress->id);

        $this->assertSame($inProgress->id, (int) $task->fresh()->current_state_id);
    }

    public function test_move_to_status_and_reorder_inserts_among_siblings(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();

        $first = Task::factory()->create([
            'title' => 'IP First',
            'sort_order' => 0,
            'current_state_id' => $inProgress->id,
            'workflow_id' => $inProgress->workflow_id,
        ]);
        $second = Task::factory()->create([
            'title' => 'IP Second',
            'sort_order' => 1,
            'current_state_id' => $inProgress->id,
            'workflow_id' => $inProgress->workflow_id,
        ]);
        $incoming = Task::factory()->create([
            'title' => 'From Todo',
            'sort_order' => 0,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('moveTaskToState', $incoming->id, $inProgress->id)
            ->call('reorderTasks', [$first->id, $incoming->id, $second->id]);

        $incoming->refresh();
        $this->assertSame($inProgress->id, (int) $incoming->current_state_id);
        $this->assertSame(0, (int) $first->fresh()->sort_order);
        $this->assertSame(1, (int) $incoming->sort_order);
        $this->assertSame(2, (int) $second->fresh()->sort_order);
    }

    public function test_list_renders_empty_status_groups_as_drop_targets(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        Task::factory()->create([
            'title' => 'Only todo',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->set('currentView', 'list')
            ->set('groupBy', 'status')
            ->assertSee('To Do')
            ->assertSee('In Progress')
            ->assertSee('Review')
            ->assertSee('Done')
            ->assertSee('Drop tasks here');
    }
}
