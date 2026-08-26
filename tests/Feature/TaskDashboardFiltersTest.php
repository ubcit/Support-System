<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskDashboardFiltersTest extends TestCase
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

    public function test_dashboard_shows_all_open_tasks_by_default_and_hides_completed(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $other = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Alpha Site']);

        $mine = Task::factory()->create([
            'title' => 'Boss assigned open task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $mine->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        $team = Task::factory()->create([
            'title' => 'Ahmed assigned open task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $team->assignments()->create(['employee_id' => $other->id, 'assigned_at' => now()]);

        Task::factory()->create([
            'title' => 'Already finished task',
            'project_id' => $project->id,
            'current_state_id' => $done->id,
            'workflow_id' => $done->workflow_id,
            'completed_at' => now(),
        ]);

        $this->actingAs($boss)
            ->get(route('task-dashboard'))
            ->assertOk()
            ->assertSee('Boss assigned open task')
            ->assertSee('Ahmed assigned open task')
            ->assertDontSee('Already finished task')
            ->assertDontSee('🔴')
            ->assertSee('bg-brand-500', false)
            ->assertDontSee('text-primary-600', false);
    }

    public function test_project_scope_due_and_completed_filters(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $other = User::where('email', 'ahmed@thespace.app')->firstOrFail()->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        $website = Project::factory()->create(['name' => 'Website Rebuild']);
        $ads = Project::factory()->create(['name' => 'Ad System']);

        $onWebsite = Task::factory()->create([
            'title' => 'Website unique task',
            'project_id' => $website->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => now()->toDateString(),
        ]);
        $onWebsite->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        $onAds = Task::factory()->create([
            'title' => 'Ads unique task',
            'project_id' => $ads->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => now()->addWeek()->toDateString(),
        ]);
        $onAds->assignments()->create(['employee_id' => $other->id, 'assigned_at' => now()]);

        $overdueOpen = Task::factory()->create([
            'title' => 'Open overdue unique',
            'project_id' => $website->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        $overdueDone = Task::factory()->create([
            'title' => 'Completed overdue unique',
            'project_id' => $website->id,
            'current_state_id' => $done->id,
            'workflow_id' => $done->workflow_id,
            'due_date' => now()->subDays(3)->toDateString(),
            'completed_at' => now(),
        ]);

        $child = Task::factory()->create([
            'title' => 'Hidden subtask unique',
            'parent_id' => $onWebsite->id,
            'project_id' => $website->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        unset($child);

        $this->actingAs($boss);

        $this->get(route('task-dashboard', ['project' => $website->id]))
            ->assertOk()
            ->assertSee('Website unique task')
            ->assertDontSee('Ads unique task');

        $this->get(route('task-dashboard', ['scope' => 'mine']))
            ->assertOk()
            ->assertSee('Website unique task')
            ->assertDontSee('Ads unique task');

        $this->get(route('task-dashboard', ['due' => 'today']))
            ->assertOk()
            ->assertSee('Website unique task')
            ->assertDontSee('Open overdue unique');

        $this->get(route('task-dashboard', ['due' => 'overdue']))
            ->assertOk()
            ->assertSee('Open overdue unique')
            ->assertDontSee('Completed overdue unique');

        $this->get(route('task-dashboard', ['completed' => 1]))
            ->assertOk()
            ->assertSee('Completed overdue unique')
            ->assertDontSee('Website unique task');

        $this->get(route('task-dashboard'))
            ->assertOk()
            ->assertSee('Website unique task')
            ->assertSee('0/1')
            ->assertSee('Hidden subtask unique');
    }

    public function test_dashboard_shows_attachment_and_comment_chips_only_when_present(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Chip Project']);

        $withMeta = Task::factory()->create([
            'title' => 'Parent with meta chips unique',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $withMeta->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        $done = WorkflowState::where('name', 'Done')->firstOrFail();
        Task::factory()->create([
            'title' => 'Nested child for chip unique',
            'parent_id' => $withMeta->id,
            'project_id' => $project->id,
            'current_state_id' => $done->id,
            'workflow_id' => $done->workflow_id,
            'completed_at' => now(),
        ]);
        Task::factory()->create([
            'title' => 'Nested open child for chip unique',
            'parent_id' => $withMeta->id,
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $plain = Task::factory()->create([
            'title' => 'Plain parent no meta unique',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        \Modules\Attachments\Models\Attachment::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $actor->workspace_id,
            'attachable_type' => Task::class,
            'attachable_id' => $withMeta->id,
            'original_name' => 'brief.pdf',
            'stored_path' => 'attachments/task/brief.pdf',
            'disk' => 'local',
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'type' => \Modules\Attachments\Enums\AttachmentType::Document->value,
            'processing_status' => 'ready',
            'uploaded_by' => $actor->id,
        ]);

        $withMeta->comments()->create([
            'employee_id' => $actor->id,
            'content' => 'Need review soon',
        ]);

        $this->actingAs($boss);

        $response = $this->get(route('task-dashboard', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Parent with meta chips unique')
            ->assertSee('1/2')
            ->assertSee('Nested child for chip unique')
            ->assertSee('Nested open child for chip unique')
            ->assertSee('Plain parent no meta unique');

        $html = $response->getContent();
        $this->assertStringContainsString('title="Subtasks: 1/2 done"', $html);
        $this->assertStringContainsString('title="1 attachment"', $html);
        $this->assertStringContainsString('title="1 comment"', $html);

        // Plain parent must not get attachment/comment chips (no counts).
        $plainPos = strpos($html, 'Plain parent no meta unique');
        $this->assertNotFalse($plainPos);
        $slice = substr($html, $plainPos, 1200);
        $this->assertStringNotContainsString('title="1 attachment"', $slice);
        $this->assertStringNotContainsString('title="1 comment"', $slice);
    }

    public function test_quick_create_subtask_from_dashboard(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create();

        $parent = Task::factory()->create([
            'title' => 'Parent for quick subtask',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        $this->actingAs($boss);

        \Livewire\Livewire::test(\App\Livewire\TaskDashboard\Index::class)
            ->call('quickCreateSubtask', $parent->id, 'Quick nested unique')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'parent_id' => $parent->id,
            'title' => 'Quick nested unique',
        ]);
    }

    public function test_quick_create_task_inherits_project_from_group_context(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Grouped Project']);

        $this->actingAs($boss);

        \Livewire\Livewire::test(\App\Livewire\TaskDashboard\Index::class)
            ->set('groupBy', 'project')
            ->call('quickCreateTask', 'Task in project group', null, $project->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Task in project group',
            'project_id' => $project->id,
        ]);
    }

    public function test_board_and_calendar_views_render_and_calendar_shows_empty_copy(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();

        $this->actingAs($boss);

        $this->get(route('task-dashboard', ['view' => 'board']))
            ->assertOk();

        $this->get(route('task-dashboard', ['view' => 'calendar']))
            ->assertOk()
            ->assertSee('Nothing scheduled this month')
            ->assertSee('Nothing due');
    }

    public function test_list_can_group_tasks_by_due_date(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Due Group Project']);

        Task::factory()->create([
            'title' => 'Past due bucket task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => now()->subDays(2)->toDateString(),
        ]);
        Task::factory()->create([
            'title' => 'Due today bucket task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => now()->toDateString(),
        ]);
        Task::factory()->create([
            'title' => 'Due tomorrow bucket task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => now()->addDay()->toDateString(),
        ]);
        Task::factory()->create([
            'title' => 'Later due bucket task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        Task::factory()->create([
            'title' => 'No due bucket task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
            'due_date' => null,
        ]);

        $this->actingAs($boss);

        \Livewire\Livewire::test(\App\Livewire\TaskDashboard\Index::class)
            ->set('groupBy', 'due_date')
            ->assertSee('Overdue')
            ->assertSee('Today')
            ->assertSee('Tomorrow')
            ->assertSee('Later')
            ->assertSee('No due date')
            ->assertSee('Past due bucket task')
            ->assertSee('Due today bucket task')
            ->assertSee('Due tomorrow bucket task')
            ->assertSee('Later due bucket task')
            ->assertSee('No due bucket task')
            ->call('moveTaskToDueGroup', Task::where('title', 'No due bucket task')->value('id'), 'today')
            ->assertHasNoErrors();

        $moved = Task::where('title', 'No due bucket task')->firstOrFail();
        $this->assertSame(now()->toDateString(), $moved->due_date?->format('Y-m-d'));
    }
}
