<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmailJob;
use App\Mail\TaskAssignedMail;
use App\Mail\TaskCompletedMail;
use App\Mail\TaskCreatedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\NotificationLog;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class NotificationEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_job_sends_mail_synchronously_not_requeued(): void
    {
        Mail::fake();

        [$assignee, $task] = $this->seedAssignedTask();

        (new SendNotificationEmailJob('task_assigned', $task->id, $assignee->id))
            ->handle(app(EmailNotificationService::class));

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });

        Mail::assertNotQueued(TaskAssignedMail::class);
    }

    public function test_dispatch_notify_runs_synchronously_and_sends_mail(): void
    {
        Mail::fake();

        [$assignee, $task] = $this->seedAssignedTask();

        SendNotificationEmailJob::dispatchNotify('task_assigned', $task->id, $assignee->id);

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });

        Mail::assertNotQueued(TaskAssignedMail::class);
    }

    public function test_task_created_job_sends_to_project_teammates(): void
    {
        Mail::fake();

        [$creator, $assignee, $teammate, $task] = $this->seedProjectTask();

        (new SendNotificationEmailJob('task_created', $task->id, $creator->id))
            ->handle(app(EmailNotificationService::class));

        Mail::assertSent(TaskCreatedMail::class, function (TaskCreatedMail $mail) use ($teammate) {
            return $mail->hasTo($teammate->email);
        });

        Mail::assertNotSent(TaskCreatedMail::class, function (TaskCreatedMail $mail) use ($creator, $assignee) {
            return $mail->hasTo($creator->email) || $mail->hasTo($assignee->email);
        });
    }

    public function test_update_fields_to_done_dispatches_completed_email_job_sync(): void
    {
        Mail::fake();

        [$creator, $assignee, , $task] = $this->seedProjectTask();

        $done = WorkflowState::create([
            'workflow_id' => $task->workflow_id,
            'name' => 'Done',
            'type' => 'completed',
            'order' => 99,
        ]);

        Bus::fake([SendNotificationEmailJob::class]);

        app(NativeTaskService::class)->updateFields($task, [
            'current_state_id' => $done->id,
        ], $creator);

        Bus::assertDispatchedSync(SendNotificationEmailJob::class, function (SendNotificationEmailJob $job) use ($task) {
            return $job->type === 'task_completed' && $job->modelId === $task->id;
        });

        (new SendNotificationEmailJob('task_completed', $task->id))
            ->handle(app(EmailNotificationService::class));

        Mail::assertSent(TaskCompletedMail::class, function (TaskCompletedMail $mail) use ($assignee) {
            return $mail->hasTo($assignee->email);
        });
    }

    public function test_mail_diagnose_sample_task_assigned_sends_mailable(): void
    {
        Mail::fake();

        [$assignee, $task] = $this->seedAssignedTask();
        $to = 'diagnose-sample-'.uniqid().'@example.com';

        $exit = Artisan::call('mail:diagnose', [
            '--send' => $to,
            '--sample' => 'task_assigned',
        ]);

        $this->assertSame(0, $exit);

        Mail::assertSent(TaskAssignedMail::class, function (TaskAssignedMail $mail) use ($to) {
            return $mail->hasTo($to);
        });

        $this->assertTrue(
            NotificationLog::query()
                ->where('recipient', $to)
                ->where('body', 'task_assigned')
                ->where('status', 'sent')
                ->exists()
        );

        $this->assertNotNull($task->id);
        $this->assertNotNull($assignee->id);
    }

    /**
     * @return array{0: Employee, 1: Task}
     */
    protected function seedAssignedTask(): array
    {
        [$creator, $assignee, , $task] = $this->seedProjectTask();

        return [$assignee, $task];
    }

    /**
     * @return array{0: Employee, 1: Employee, 2: Employee, 3: Task}
     */
    protected function seedProjectTask(): array
    {
        $workspace = Workspace::create(['name' => 'Space', 'slug' => 'notif-mail-'.uniqid()]);

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

        $task = app(NativeTaskService::class)->createTask([
            'title' => 'Ship notification emails',
            'project_id' => $project->id,
            'priority' => 'high',
            'current_state_id' => $state->id,
            'assignee_ids' => [$assignee->id],
        ], $creator);

        return [$creator, $assignee, $teammate, $task];
    }
}
