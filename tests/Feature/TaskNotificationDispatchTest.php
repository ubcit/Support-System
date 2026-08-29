<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmailJob;
use App\Mail\TaskAssignedMail;
use App\Mail\TaskCreatedMail;
use App\Mail\TaskDeletedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_task_notifies_assignee_creator_and_project_members(): void
    {
        Bus::fake([SendNotificationEmailJob::class]);
        Mail::fake();

        [$creator, $assignee, $teammate, $project, $state] = $this->seedProjectActors();

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
                ->where('employee_id', $creator->id)
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

        Bus::assertDispatchedSync(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $assignee) {
            return $job->type === 'task_assigned'
                && $job->modelId === $task->id
                && $job->employeeId === $assignee->id;
        });

        // Fan-out job still uses creator id as the exclude list seed.
        Bus::assertDispatchedSync(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $creator) {
            return $job->type === 'task_created'
                && $job->modelId === $task->id
                && $job->employeeId === $creator->id;
        });

        Mail::assertSent(TaskCreatedMail::class, function (TaskCreatedMail $mail) use ($creator) {
            return $mail->hasTo($creator->email);
        });
    }

    public function test_create_with_self_assign_notifies_creator(): void
    {
        Bus::fake([SendNotificationEmailJob::class]);
        Mail::fake();

        [$creator, , , $project, $state] = $this->seedProjectActors();

        $service = app(NativeTaskService::class);
        $task = $service->createTask([
            'title' => 'Self assigned',
            'project_id' => $project->id,
            'priority' => 'medium',
            'current_state_id' => $state->id,
            'assignee_ids' => [$creator->id],
        ], $creator);

        $this->assertTrue(
            Notification::query()
                ->where('type', 'task_assigned')
                ->where('employee_id', $creator->id)
                ->where('metadata->task_id', $task->id)
                ->exists()
        );

        $this->assertTrue(
            Notification::query()
                ->where('type', 'task_created')
                ->where('employee_id', $creator->id)
                ->where('metadata->task_id', $task->id)
                ->exists()
        );

        Bus::assertDispatchedSync(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $creator) {
            return $job->type === 'task_assigned'
                && $job->modelId === $task->id
                && $job->employeeId === $creator->id;
        });

        Mail::assertSent(TaskCreatedMail::class, function (TaskCreatedMail $mail) use ($creator) {
            return $mail->hasTo($creator->email);
        });

        $this->assertContains($creator->name, $service->lastNotifiedNames());
    }

    public function test_update_assignees_notifies_new_assignee_and_actor(): void
    {
        Bus::fake([SendNotificationEmailJob::class]);
        Mail::fake();

        [$actor, $first, $second, $project, $state] = $this->seedProjectActors();

        $service = app(NativeTaskService::class);
        $task = $service->createTask([
            'title' => 'Reassign me',
            'project_id' => $project->id,
            'priority' => 'medium',
            'current_state_id' => $state->id,
            'assignee_ids' => [$first->id],
        ], $actor);

        Bus::fake([SendNotificationEmailJob::class]);
        Mail::fake();

        $service->updateAssignees($task, [$first->id, $second->id], $actor);

        $this->assertTrue(
            Notification::query()
                ->where('type', 'task_assigned')
                ->where('employee_id', $second->id)
                ->where('metadata->task_id', $task->id)
                ->exists()
        );

        $this->assertTrue(
            Notification::query()
                ->where('type', 'task_assigned')
                ->where('employee_id', $actor->id)
                ->where('metadata->task_id', $task->id)
                ->where('body', 'like', 'You assigned%')
                ->exists()
        );

        Bus::assertDispatchedSync(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $second) {
            return $job->type === 'task_assigned'
                && $job->modelId === $task->id
                && $job->employeeId === $second->id;
        });

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($actor) {
            return $mail->hasTo($actor->email)
                && is_string($mail->intro)
                && str_contains($mail->intro, 'You assigned');
        });

        Bus::assertNotDispatchedSync(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($first) {
            return $job->type === 'task_assigned' && $job->employeeId === $first->id;
        });
    }

    public function test_delete_task_notifies_assignee_creator_and_actor_with_email(): void
    {
        Bus::fake([SendNotificationEmailJob::class]);
        Mail::fake();

        [$creator, $assignee, , $project, $state] = $this->seedProjectActors();
        $deleter = Employee::create([
            'workspace_id' => $creator->workspace_id,
            'name' => 'Deleter',
            'email' => 'deleter-'.uniqid().'@thespace.app',
            'role' => 'boss',
        ]);

        $service = app(NativeTaskService::class);
        $task = $service->createTask([
            'title' => 'Doomed task',
            'project_id' => $project->id,
            'priority' => 'low',
            'current_state_id' => $state->id,
            'assignee_ids' => [$assignee->id],
        ], $creator);

        Bus::fake([SendNotificationEmailJob::class]);
        Mail::fake();

        $service->deleteTask($task, $deleter);

        foreach ([$assignee->id, $creator->id, $deleter->id] as $employeeId) {
            $this->assertTrue(
                Notification::query()
                    ->where('type', 'task_deleted')
                    ->where('employee_id', $employeeId)
                    ->where('metadata->task_id', $task->id)
                    ->exists(),
                "Expected task_deleted in-app for employee {$employeeId}"
            );

            Bus::assertDispatchedSync(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task, $employeeId, $deleter) {
                return $job->type === 'task_deleted'
                    && $job->modelId === $task->id
                    && $job->employeeId === $employeeId
                    && $job->actorId === $deleter->id;
            });
        }

        (new SendNotificationEmailJob('task_deleted', $task->id, $assignee->id, $deleter->id))
            ->handle(app(EmailNotificationService::class));

        Mail::assertSent(TaskDeletedMail::class, function (TaskDeletedMail $mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });

        $this->assertTrue($task->fresh()->trashed());
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Employee, 3: Project, 4: WorkflowState}
     */
    protected function seedProjectActors(): array
    {
        $workspace = Workspace::create(['name' => 'Space', 'slug' => 'space-'.uniqid()]);

        $creator = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Creator',
            'email' => 'creator-'.uniqid().'@thespace.app',
            'role' => 'boss',
        ]);
        $assignee = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Assignee',
            'email' => 'assignee-'.uniqid().'@thespace.app',
            'role' => 'employee',
        ]);
        $teammate = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Teammate',
            'email' => 'teammate-'.uniqid().'@thespace.app',
            'role' => 'employee',
        ]);

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Alpha',
            'code' => 'ALP-'.uniqid(),
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

        return [$creator, $assignee, $teammate, $project, $state];
    }
}
