<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\Notification;
use Modules\Projects\Models\Project;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_task_notifies_assignee_and_project_members(): void
    {
        Bus::fake([SendNotificationEmailJob::class]);

        $workspace = Workspace::create(['name' => 'Space', 'slug' => 'space']);

        $creator = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Creator',
            'email' => 'creator@thespace.app',
            'role' => 'boss',
        ]);
        $assignee = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Assignee',
            'email' => 'assignee@thespace.app',
            'role' => 'employee',
        ]);
        $teammate = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Teammate',
            'email' => 'teammate@thespace.app',
            'role' => 'employee',
        ]);

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Alpha',
            'code' => 'ALP-1',
            'status' => 'active',
        ]);
        $project->employees()->sync([$creator->id, $assignee->id, $teammate->id]);

        $workflow = Workflow::create([
            'name' => 'Default',
            'entity_type' => 'task',
            'is_default' => true,
        ]);
        $state = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);

        $task = app(NativeTaskService::class)->createTask([
            'title' => 'Ship notifications',
            'project_id' => $project->id,
            'priority' => 'high',
            'current_state_id' => $state->id,
            'assignee_ids' => [$assignee->id],
        ], $creator);

        $this->assertTrue(
            Notification::query()
                ->where('type', 'task_assigned')
                ->where('employee_id', $assignee->id)
                ->where('metadata->task_id', $task->id)
                ->exists()
        );

        $this->assertTrue(
            Notification::query()
                ->where('type', 'task_created')
                ->where('employee_id', $teammate->id)
                ->where('metadata->task_id', $task->id)
                ->exists()
        );

        $this->assertFalse(
            Notification::query()
                ->where('type', 'task_created')
                ->where('employee_id', $creator->id)
                ->exists()
        );

        Bus::assertDispatched(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $assignee) {
            return $job->type === 'task_assigned'
                && $job->modelId === $task->id
                && $job->employeeId === $assignee->id;
        });

        Bus::assertDispatched(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $creator) {
            return $job->type === 'task_created'
                && $job->modelId === $task->id
                && $job->employeeId === $creator->id;
        });
    }

    public function test_update_assignees_notifies_newly_added_only(): void
    {
        Bus::fake([SendNotificationEmailJob::class]);

        $workspace = Workspace::create(['name' => 'Space', 'slug' => 'space-2']);
        $actor = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Actor',
            'email' => 'actor@thespace.app',
            'role' => 'boss',
        ]);
        $first = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'First',
            'email' => 'first@thespace.app',
            'role' => 'employee',
        ]);
        $second = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Second',
            'email' => 'second@thespace.app',
            'role' => 'employee',
        ]);

        $workflow = Workflow::create([
            'name' => 'Default',
            'entity_type' => 'task',
            'is_default' => true,
        ]);
        $state = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);

        $service = app(NativeTaskService::class);
        $task = $service->createTask([
            'title' => 'Reassign me',
            'priority' => 'medium',
            'current_state_id' => $state->id,
            'assignee_ids' => [$first->id],
        ], $actor);

        Bus::fake([SendNotificationEmailJob::class]);

        $service->updateAssignees($task, [$first->id, $second->id], $actor);

        $this->assertTrue(
            Notification::query()
                ->where('type', 'task_assigned')
                ->where('employee_id', $second->id)
                ->where('metadata->task_id', $task->id)
                ->exists()
        );

        Bus::assertDispatched(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $second) {
            return $job->type === 'task_assigned'
                && $job->modelId === $task->id
                && $job->employeeId === $second->id;
        });

        Bus::assertNotDispatched(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($first) {
            return $job->type === 'task_assigned' && $job->employeeId === $first->id;
        });
    }
}
