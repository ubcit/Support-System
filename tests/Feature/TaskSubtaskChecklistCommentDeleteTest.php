<?php

namespace Tests\Feature;

use App\Livewire\TaskDashboard\Index as TaskDashboard;
use App\Livewire\TaskDetail\Index as TaskDetail;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskChecklist;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskSubtaskChecklistCommentDeleteTest extends TestCase
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

    public function test_detail_can_soft_delete_a_subtask_without_leaving_parent(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $parent = Task::factory()->create([
            'title' => 'Parent with child',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $child = Task::factory()->create([
            'title' => 'Child to trash',
            'parent_id' => $parent->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDetail::class, ['record' => $parent->id])
            ->call('deleteSubtask', $child->id)
            ->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $child->id]);
        $this->assertNotSoftDeleted('tasks', ['id' => $parent->id]);
    }

    public function test_dashboard_can_soft_delete_a_subtask(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $parent = Task::factory()->create([
            'title' => 'List parent',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $child = Task::factory()->create([
            'title' => 'List child',
            'parent_id' => $parent->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('deleteTask', $child->id);

        $this->assertSoftDeleted('tasks', ['id' => $child->id]);
    }

    public function test_checklist_and_item_can_be_deleted(): void
    {
        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Checklist task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create([
            'employee_id' => $ahmed->resolveEmployee()->id,
            'assigned_at' => now(),
        ]);

        $checklist = TaskChecklist::create([
            'task_id' => $task->id,
            'title' => 'DoD',
            'sort_order' => 0,
        ]);
        $item = $checklist->items()->create([
            'title' => 'Ship it',
            'sort_order' => 0,
        ]);
        $keep = $checklist->items()->create([
            'title' => 'Keep me',
            'sort_order' => 1,
        ]);

        $this->actingAs($ahmed);

        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->call('deleteChecklistItem', $item->id)
            ->assertDontSee('Ship it')
            ->assertSee('Keep me');

        $this->assertDatabaseMissing('task_checklist_items', ['id' => $item->id]);
        $this->assertDatabaseHas('task_checklist_items', ['id' => $keep->id]);

        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->call('deleteChecklist', $checklist->id)
            ->assertDontSee('DoD');

        $this->assertDatabaseMissing('task_checklists', ['id' => $checklist->id]);
        $this->assertDatabaseMissing('task_checklist_items', ['id' => $keep->id]);
    }

    public function test_author_and_privileged_can_delete_comments_others_cannot(): void
    {
        $ahmedUser = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $saraUser = User::where('email', 'sara@thespace.app')->firstOrFail();
        $bossUser = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Comment delete task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $ahmedUser->resolveEmployee()->id, 'assigned_at' => now()]);
        $task->assignments()->create(['employee_id' => $saraUser->resolveEmployee()->id, 'assigned_at' => now()]);

        $ahmedComment = app(NativeTaskService::class)->addComment(
            $task,
            'Ahmed wrote this',
            $ahmedUser->resolveEmployee(),
        );
        $saraComment = app(NativeTaskService::class)->addComment(
            $task,
            'Sara wrote this',
            $saraUser->resolveEmployee(),
        );

        $this->actingAs($ahmedUser);
        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->call('deleteComment', $ahmedComment->id);

        $this->assertDatabaseMissing('task_comments', ['id' => $ahmedComment->id]);

        $this->actingAs($ahmedUser);
        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->call('deleteComment', $saraComment->id)
            ->assertForbidden();

        $this->assertDatabaseHas('task_comments', ['id' => $saraComment->id]);

        $this->actingAs($bossUser);
        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->call('deleteComment', $saraComment->id)
            ->assertOk();

        $this->assertDatabaseMissing('task_comments', ['id' => $saraComment->id]);
    }

    public function test_create_subtask_rejects_nested_parent(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $root = Task::factory()->create([
            'title' => 'Root',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $child = Task::factory()->create([
            'title' => 'Already a subtask',
            'parent_id' => $root->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(NativeTaskService::class)->createSubtask($child, [
            'title' => 'Grandchild',
        ], $boss);
    }

    public function test_task_detail_hides_subtask_add_on_child_and_blocks_addSubtask(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $root = Task::factory()->create([
            'title' => 'Root for nesting UI',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $child = Task::factory()->create([
            'title' => 'Child detail',
            'parent_id' => $root->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDetail::class, ['record' => $child->id])
            ->assertSee('Parent task')
            ->assertSee('Root for nesting UI')
            ->assertDontSee('+ Add subtask title and press Enter...')
            ->set('newSubtaskTitle', 'Should not create')
            ->call('addSubtask');

        $this->assertFalse(
            Task::query()->where('parent_id', $child->id)->where('title', 'Should not create')->exists()
        );
    }

    public function test_dashboard_quick_create_subtask_works_for_root_parents(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $parent = Task::factory()->create([
            'title' => 'Quick add parent',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('quickCreateSubtask', $parent->id, 'Inline subtask');

        $this->assertDatabaseHas('tasks', [
            'parent_id' => $parent->id,
            'title' => 'Inline subtask',
        ]);
    }

    public function test_dashboard_quick_create_subtask_rejects_non_root_parent(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $root = Task::factory()->create([
            'title' => 'Root',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $child = Task::factory()->create([
            'title' => 'Child',
            'parent_id' => $root->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('quickCreateSubtask', $child->id, 'Nested attempt');

        $this->assertFalse(
            Task::query()->where('title', 'Nested attempt')->exists()
        );
    }

    public function test_checklist_and_item_can_be_renamed(): void
    {
        $ahmed = User::where('email', 'ahmed@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Rename checklist task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create([
            'employee_id' => $ahmed->resolveEmployee()->id,
            'assigned_at' => now(),
        ]);

        $checklist = TaskChecklist::create([
            'task_id' => $task->id,
            'title' => 'DoD',
            'sort_order' => 0,
        ]);
        $item = $checklist->items()->create([
            'title' => 'Ship it',
            'sort_order' => 0,
        ]);

        $this->actingAs($ahmed);

        Livewire::test(TaskDetail::class, ['record' => $task->id])
            ->call('renameChecklist', $checklist->id, 'Definition of Done')
            ->call('renameChecklistItem', $item->id, 'Ship to prod');

        $this->assertDatabaseHas('task_checklists', [
            'id' => $checklist->id,
            'title' => 'Definition of Done',
        ]);
        $this->assertDatabaseHas('task_checklist_items', [
            'id' => $item->id,
            'title' => 'Ship to prod',
        ]);
    }

    public function test_dashboard_can_update_subtask_status_and_due_date(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $inProgress = WorkflowState::where('name', 'In Progress')->firstOrFail();

        $parent = Task::factory()->create([
            'title' => 'Ops parent',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $child = Task::factory()->create([
            'title' => 'Ops child',
            'parent_id' => $parent->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        Livewire::test(TaskDashboard::class)
            ->call('moveTaskToState', $child->id, $inProgress->id)
            ->call('updateTaskDueDate', $child->id, '2026-09-01');

        $child->refresh();
        $this->assertSame($inProgress->id, (int) $child->current_state_id);
        $this->assertSame('2026-09-01', $child->due_date?->format('Y-m-d'));
    }
}
