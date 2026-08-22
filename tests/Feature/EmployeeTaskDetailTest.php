<?php

namespace Tests\Feature;

use App\Helpers\TaskNav;
use App\Livewire\TaskDetail\Index as TaskDetail;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Attachments\Models\Attachment;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Security\Models\Role;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class EmployeeTaskDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $boss;

    protected User $employeeUser;

    protected Employee $employee;

    protected WorkflowState $todo;

    protected function setUp(): void
    {
        parent::setUp();

        Workspace::create([
            'name' => 'The Space',
            'slug' => 'the-space',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $workspace = Workspace::firstOrFail();
        $bossRole = Role::where('slug', Role::BOSS)->firstOrFail();
        $employeeRole = Role::where('slug', Role::EMPLOYEE)->firstOrFail();

        $this->boss = User::create([
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        $bossEmployee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->boss->id,
            'workspace_id' => $workspace->id,
            'name' => 'Yousif',
            'email' => 'boss@thespace.app',
            'role' => 'Executive Boss',
        ]);
        $bossEmployee->roles()->sync([$bossRole->id]);

        $this->employeeUser = User::create([
            'name' => 'Yassen',
            'email' => 'yassen@thespace.app',
            'password' => Hash::make('password123'),
        ]);

        $this->employee = Employee::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->employeeUser->id,
            'workspace_id' => $workspace->id,
            'name' => 'Yassen',
            'email' => 'yassen@thespace.app',
            'role' => 'Employee',
        ]);
        $this->employee->roles()->sync([$employeeRole->id]);

        $workflow = Workflow::create([
            'name' => 'Default',
            'entity_type' => 'task',
            'is_default' => true,
        ]);

        $this->todo = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);
    }

    protected function assignedTask(string $title = 'Employee task detail'): Task
    {
        $project = Project::factory()->create(['name' => 'Employee Project']);
        $task = Task::factory()->create([
            'title' => $title,
            'project_id' => $project->id,
            'current_state_id' => $this->todo->id,
            'workflow_id' => $this->todo->workflow_id,
        ]);
        $task->assignments()->create([
            'employee_id' => $this->employee->id,
            'assigned_at' => now(),
        ]);

        return $task->fresh();
    }

    public function test_employee_can_open_workspace_task_detail(): void
    {
        $task = $this->assignedTask();

        $this->actingAs($this->employeeUser)
            ->get(route('workspace.task-detail', $task->id))
            ->assertOk()
            ->assertSee($task->title)
            ->assertSee('Cycle Time')
            ->assertSee('Upload File');
    }

    public function test_employee_admin_task_detail_link_redirects_to_workspace_copy(): void
    {
        $task = $this->assignedTask();

        $this->actingAs($this->employeeUser)
            ->get(route('task-detail', $task->id))
            ->assertRedirect(route('workspace.task-detail', $task->id));
    }

    public function test_employee_detail_url_points_to_workspace_route(): void
    {
        $task = $this->assignedTask();

        $this->actingAs($this->employeeUser);

        $this->assertSame(
            route('workspace.task-detail', $task->id),
            TaskNav::detailUrl($task->id, [])
        );
    }

    public function test_employee_can_upload_attachment_on_assigned_task(): void
    {
        Storage::fake('public');

        $task = $this->assignedTask();

        Livewire::actingAs($this->employeeUser)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->set('newAttachmentFile', UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf'))
            ->call('uploadAttachment')
            ->assertHasNoErrors()
            ->assertSee('notes.pdf');

        $this->assertDatabaseHas('attachments', [
            'attachable_type' => Task::class,
            'attachable_id' => $task->id,
            'original_name' => 'notes.pdf',
        ]);
    }

    public function test_employee_can_download_uploaded_attachment(): void
    {
        Storage::fake('public');

        $task = $this->assignedTask();

        Livewire::actingAs($this->employeeUser)
            ->test(TaskDetail::class, ['record' => $task->id])
            ->set('newAttachmentFile', UploadedFile::fake()->image('shot.jpg'))
            ->call('uploadAttachment')
            ->assertHasNoErrors();

        $attachment = Attachment::query()
            ->where('attachable_type', Task::class)
            ->where('attachable_id', $task->id)
            ->firstOrFail();

        $this->actingAs($this->employeeUser)
            ->get(route('attachments.media', ['uuid' => $attachment->uuid]))
            ->assertOk();
    }

    public function test_boss_still_uses_admin_task_detail(): void
    {
        $task = $this->assignedTask();

        $this->actingAs($this->boss)
            ->get(route('task-detail', $task->id))
            ->assertOk()
            ->assertSee($task->title);

        $this->assertSame(
            route('task-detail', $task->id),
            TaskNav::detailUrl($task->id, [])
        );
    }
}
