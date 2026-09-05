<?php

namespace Tests\Feature;

use App\Livewire\TaskDetail\Index as TaskDetail;
use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Tag;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskTagsTest extends TestCase
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

    public function test_create_task_syncs_existing_and_new_tags(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $workspaceId = $actor->workspace_id ?? Workspace::query()->value('id');
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $existing = Tag::factory()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Backend',
            'color' => '#3B82F6',
        ]);

        $task = app(NativeTaskService::class)->createTask([
            'title' => 'Tagged auth work',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'workspace_id' => $workspaceId,
            'tag_ids' => [$existing->id],
            'tags' => [
                ['name' => 'Urgent Fix', 'color' => '#EF4444'],
            ],
        ], $actor);

        $this->assertCount(2, $task->tags);
        $this->assertTrue($task->tags->contains('name', 'Backend'));
        $this->assertTrue($task->tags->contains('name', 'Urgent Fix'));
        $this->assertDatabaseHas('tags', [
            'name' => 'Urgent Fix',
            'workspace_id' => $workspaceId,
            'color' => '#EF4444',
        ]);
        $this->assertDatabaseHas('task_activity_logs', [
            'task_id' => $task->id,
            'action' => 'tags_changed',
        ]);
    }

    public function test_update_tags_replaces_set_and_is_workspace_scoped(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $workspaceId = $actor->workspace_id ?? Workspace::query()->value('id');
        $otherWorkspace = Workspace::create([
            'name' => 'Other Space',
            'slug' => 'other-space',
        ]);
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $local = Tag::factory()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Local Tag',
        ]);
        $foreign = Tag::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'name' => 'Foreign Tag',
        ]);

        $task = Task::factory()->create([
            'title' => 'Workspace tag isolation',
            'workspace_id' => $workspaceId,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $service = app(NativeTaskService::class);
        $service->updateTags($task, [$local->id, $foreign->id, 'Created Inline'], $actor);

        $task->refresh()->load('tags');
        $this->assertCount(2, $task->tags);
        $this->assertTrue($task->tags->contains('id', $local->id));
        $this->assertTrue($task->tags->contains('name', 'Created Inline'));
        $this->assertFalse($task->tags->contains('id', $foreign->id));
    }

    public function test_dashboard_filters_by_tag(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $workspaceId = $actor->workspace_id ?? Workspace::query()->value('id');
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Tag Filter Project']);

        $bug = Tag::factory()->create([
            'workspace_id' => $workspaceId,
            'name' => 'bug-filter-unique',
            'color' => '#EF4444',
        ]);

        $tagged = Task::factory()->create([
            'title' => 'Has bug tag unique',
            'project_id' => $project->id,
            'workspace_id' => $workspaceId,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $tagged->tags()->attach($bug->id);
        $tagged->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        $untagged = Task::factory()->create([
            'title' => 'No tag unique task',
            'project_id' => $project->id,
            'workspace_id' => $workspaceId,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $untagged->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        $this->actingAs($boss)
            ->get(route('task-dashboard', ['tag' => $bug->id]))
            ->assertOk()
            ->assertSee('Has bug tag unique')
            ->assertDontSee('No tag unique task')
            ->assertSee('bug-filter-unique');
    }

    public function test_task_detail_can_attach_and_detach_tags(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $workspaceId = $actor->workspace_id ?? Workspace::query()->value('id');
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $tag = Tag::factory()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Detail Tag',
            'color' => '#10B981',
        ]);

        $task = Task::factory()->create([
            'title' => 'Detail tagging task',
            'workspace_id' => $workspaceId,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'created_by' => $actor->id,
        ]);
        $task->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        Livewire::actingAs($boss)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->call('toggleTag', $tag->id)
            ->assertSet('selectedTagIds', [$tag->id]);

        $this->assertTrue($task->fresh()->tags->contains('id', $tag->id));

        Livewire::actingAs($boss)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->call('removeTag', $tag->id)
            ->assertSet('selectedTagIds', []);

        $this->assertFalse($task->fresh()->tags->contains('id', $tag->id));
    }

    public function test_dashboard_create_form_tag_selects_new_tag(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $workspaceId = $actor->workspace_id ?? Workspace::query()->value('id');

        Livewire::actingAs($boss)
            ->test(\App\Livewire\TaskDashboard\Index::class)
            ->set('formNewTagColor', '#8B5CF6')
            ->call('createFormTag', 'Dashboard Created Tag')
            ->assertSet('formTagIds', fn ($ids) => in_array(
                (string) Tag::query()->where('name', 'Dashboard Created Tag')->value('id'),
                array_map('strval', $ids),
                true
            ))
            ->assertSee('Dashboard Created Tag');

        $this->assertDatabaseHas('tags', [
            'name' => 'Dashboard Created Tag',
            'workspace_id' => $workspaceId,
            'color' => '#8B5CF6',
        ]);
    }

    public function test_dashboard_quick_add_toggle_and_create_tags(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $workspaceId = $actor->workspace_id ?? Workspace::query()->value('id');
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $existing = Tag::factory()->create([
            'workspace_id' => $workspaceId,
            'name' => 'Quick Existing',
            'color' => '#3B82F6',
        ]);

        $task = Task::factory()->create([
            'title' => 'Quick tag target',
            'workspace_id' => $workspaceId,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $task->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        Livewire::actingAs($boss)
            ->test(\App\Livewire\TaskDashboard\Index::class)
            ->call('toggleTaskTag', $task->id, $existing->id)
            ->assertSee('Quick Existing');

        $this->assertTrue($task->fresh()->tags->contains('id', $existing->id));

        Livewire::actingAs($boss)
            ->test(\App\Livewire\TaskDashboard\Index::class)
            ->call('quickCreateTaskTag', $task->id, 'Quick New Tag', '#EC4899');

        $this->assertTrue($task->fresh()->tags->contains('name', 'Quick New Tag'));
        $this->assertDatabaseHas('tags', [
            'name' => 'Quick New Tag',
            'workspace_id' => $workspaceId,
            'color' => '#EC4899',
        ]);

        Livewire::actingAs($boss)
            ->test(\App\Livewire\TaskDashboard\Index::class)
            ->call('toggleTaskTag', $task->id, $existing->id);

        $this->assertFalse($task->fresh()->tags->contains('id', $existing->id));
    }

    public function test_edit_modal_resets_tag_draft_fields_on_close(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $workspaceId = $actor->workspace_id ?? Workspace::query()->value('id');
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();

        $task = Task::factory()->create([
            'title' => 'Edit tag draft reset',
            'workspace_id' => $workspaceId,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'created_by' => $actor->id,
        ]);

        Livewire::actingAs($boss)
            ->test(\App\Livewire\TaskDashboard\Index::class)
            ->call('openEditModal', $task->id)
            ->set('formNewTagName', 'Should not stick')
            ->set('formNewTagColor', '#8B5CF6')
            ->call('closeEditModal')
            ->assertSet('showEditModal', false)
            ->assertSet('formNewTagName', '')
            ->assertSet('formNewTagColor', '#6B7280')
            ->call('openEditModal', $task->id)
            ->assertSet('formNewTagName', '')
            ->assertSet('formNewTagColor', '#6B7280');
    }
}
