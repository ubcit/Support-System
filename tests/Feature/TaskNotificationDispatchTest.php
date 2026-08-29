<?php

namespace Tests\Feature;

use App\Mail\TaskAssignedMail;
use App\Mail\TaskCreatedMail;
use App\Mail\TaskDeletedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Models\NotificationLog;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Projects\Models\Project;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskNotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_task_notifies_assignee_creator_and_project_members(): void
    {
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

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });

        Mail::assertSent(TaskCreatedMail::class, function (TaskCreatedMail $mail) use ($creator) {
            return $mail->hasTo($creator->email);
        });

        Mail::assertSent(TaskCreatedMail::class, function (TaskCreatedMail $mail) use ($teammate) {
            return $mail->hasTo($teammate->email);
        });
    }

    public function test_create_with_self_assign_notifies_creator(): void
    {
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

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($creator) {
            return $mail->hasTo($creator->email);
        });

        Mail::assertSent(TaskCreatedMail::class, function (TaskCreatedMail $mail) use ($creator) {
            return $mail->hasTo($creator->email);
        });

        $this->assertContains($creator->name, $service->lastNotifiedNames());
    }

    public function test_update_assignees_notifies_new_assignee_and_actor(): void
    {
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

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($second) {
            return $mail->hasTo($second->email);
        });

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($actor) {
            return $mail->hasTo($actor->email)
                && is_string($mail->intro)
                && str_contains($mail->intro, 'You assigned');
        });

        Mail::assertNotSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($first) {
            return $mail->hasTo($first->email);
        });
    }

    public function test_delete_task_notifies_assignee_creator_and_actor_with_email(): void
    {
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
        }

        Mail::assertSent(TaskDeletedMail::class, function (TaskDeletedMail $mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });
        Mail::assertSent(TaskDeletedMail::class, function (TaskDeletedMail $mail) use ($creator) {
            return $mail->hasTo($creator->email);
        });
        Mail::assertSent(TaskDeletedMail::class, function (TaskDeletedMail $mail) use ($deleter) {
            return $mail->hasTo($deleter->email);
        });

        $this->assertTrue($task->fresh()->trashed());
    }

    public function test_should_notify_false_logs_skipped(): void
    {
        Mail::fake();

        $workspace = Workspace::create(['name' => 'Space', 'slug' => 'skip-'.uniqid()]);
        $employee = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Opted Out',
            'email' => 'optout-'.uniqid().'@thespace.app',
            'role' => 'employee',
            'metadata' => [
                'email_notifications_enabled' => true,
                'notification_preferences' => ['task_assigned' => false],
            ],
        ]);

        $workflow = Workflow::create(['name' => 'Default', 'entity_type' => 'task', 'is_default' => true]);
        $state = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);
        $task = app(NativeTaskService::class)->createTask([
            'title' => 'Muted assign',
            'priority' => 'medium',
            'current_state_id' => $state->id,
            'assignee_ids' => [$employee->id],
        ], $employee);

        // create also sends task_created confirmation — clear and test assign path directly
        Mail::fake();
        NotificationLog::query()->delete();

        app(EmailNotificationService::class)->sendTaskAssigned($task, $employee);

        Mail::assertNothingSent();

        $this->assertTrue(
            NotificationLog::query()
                ->where('recipient', $employee->email)
                ->where('body', 'task_assigned')
                ->where('status', 'skipped')
                ->where('metadata->reason', 'pref_off:task_assigned')
                ->exists()
        );
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
