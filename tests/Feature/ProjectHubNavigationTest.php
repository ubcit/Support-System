<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\EssentialPlatformSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserAndEmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class ProjectHubNavigationTest extends TestCase
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

    public function test_project_hub_lists_projects_as_cards_until_one_is_selected(): void
    {
        $boss = User::query()->orderBy('id')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Website Rebuild']);
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();

        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Open rebuild task',
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Finished rebuild task',
            'completed_at' => now(),
            'current_state_id' => $done->id,
            'workflow_id' => $done->workflow_id,
        ]);

        $this->actingAs($boss)
            ->get(route('project-hub'))
            ->assertOk()
            ->assertSee('All projects')
            ->assertSee('Website Rebuild')
            ->assertSee('Active')
            ->assertSee('Done')
            ->assertDontSee('Select a project')
            ->assertDontSee('Pick a project from the sidebar');
    }

    public function test_project_query_string_opens_that_project(): void
    {
        $boss = User::query()->orderBy('id')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Website Rebuild']);

        $this->actingAs($boss)
            ->get(route('project-hub', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Website Rebuild')
            ->assertSee('All projects')
            ->assertDontSee('Select a project')
            ->assertDontSee('Pick a project from the sidebar');
    }

    public function test_invalid_project_query_falls_back_to_card_listing(): void
    {
        $boss = User::query()->orderBy('id')->firstOrFail();
        Project::factory()->create(['name' => 'Fallback Project']);

        $this->actingAs($boss)
            ->get(route('project-hub', ['project' => 99999]))
            ->assertOk()
            ->assertSee('Fallback Project')
            ->assertSee('All projects');
    }

    public function test_dashboard_metric_cards_link_into_my_tasks_filters(): void
    {
        $boss = User::query()->orderBy('id')->firstOrFail();

        $this->actingAs($boss)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('task-dashboard', ['due' => 'today']), false)
            ->assertSee(route('task-dashboard', ['scope' => 'unassigned']), false)
            ->assertSee(route('task-dashboard', ['due' => 'overdue']), false)
            ->assertSee(route('task-dashboard', ['view' => 'board']), false);
    }

    public function test_selected_project_shows_overdue_issue_and_completion_stats(): void
    {
        $boss = User::query()->orderBy('id')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Harbor Site Ops']);
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::where('name', 'Done')->firstOrFail();

        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Overdue Harbor paint',
            'due_date' => now()->subDay(),
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'title' => 'Finished Harbor roof',
            'completed_at' => now(),
            'current_state_id' => $done->id,
            'workflow_id' => $done->workflow_id,
        ]);
        Issue::factory()->create([
            'project_id' => $project->id,
            'title' => 'Leaky Harbor roof',
            'description' => 'Water in the lobby.',
            'status' => 'open',
        ]);

        $this->actingAs($boss)
            ->get(route('project-hub', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Harbor Site Ops')
            ->assertSee('Overdue Harbor paint')
            ->assertSee('Leaky Harbor roof')
            ->assertSee('50%')
            ->assertSee('At Risk')
            ->assertSee('Open Issues')
            ->assertDontSee('Healthy Velocity');
    }
}
