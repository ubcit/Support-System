<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Index as ExecutiveDashboard;
use App\Livewire\TaskDashboard\Index as TaskDashboard;
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

class DashboardMetricCountsTest extends TestCase
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

    public function test_tasks_waiting_ignores_unassigned_subtasks_and_matches_unassigned_list(): void
    {
        $boss = User::where('email', 'boss@thespace.app')->firstOrFail();
        $actor = $boss->resolveEmployee();
        $todo = WorkflowState::where('name', 'To Do')->firstOrFail();
        $project = Project::factory()->create(['name' => 'Metric Align Project']);

        $parent = Task::factory()->create([
            'title' => 'Assigned parent task',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);
        $parent->assignments()->create(['employee_id' => $actor->id, 'assigned_at' => now()]);

        Task::factory()->create([
            'title' => 'Orphan unassigned subtask',
            'parent_id' => $parent->id,
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        Livewire::actingAs($boss)
            ->test(ExecutiveDashboard::class)
            ->assertViewHas('tasks_waiting', 0);

        Livewire::actingAs($boss)
            ->withQueryParams(['scope' => 'unassigned'])
            ->test(TaskDashboard::class)
            ->assertDontSee('Orphan unassigned subtask')
            ->assertDontSee('Assigned parent task');

        $unassignedParent = Task::factory()->create([
            'title' => 'Truly unassigned parent',
            'project_id' => $project->id,
            'current_state_id' => $todo->id,
            'workflow_id' => $todo->workflow_id,
        ]);

        Livewire::actingAs($boss)
            ->test(ExecutiveDashboard::class)
            ->assertViewHas('tasks_waiting', 1);

        Livewire::actingAs($boss)
            ->withQueryParams(['scope' => 'unassigned'])
            ->test(TaskDashboard::class)
            ->assertSee('Truly unassigned parent')
            ->assertDontSee('Orphan unassigned subtask');

        $this->assertNull($unassignedParent->parent_id);
    }
}
