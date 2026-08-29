<?php

namespace Tests\Feature;

use App\Jobs\SendNotificationEmailJob;
use App\Mail\TaskAssignedMail;
use App\Mail\TaskCompletedMail;
use App\Mail\TaskCreatedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\NotificationLog;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use RuntimeException;
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

    public function test_dispatch_notify_still_sends_mail_for_diagnose_helpers(): void
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

    public function test_update_fields_to_done_sends_completed_email_directly(): void
    {
        Mail::fake();

        [$creator, $assignee, , $task] = $this->seedProjectTask();

        $done = WorkflowState::create([
            'workflow_id' => $task->workflow_id,
            'name' => 'Done',
            'type' => 'completed',
            'order' => 99,
        ]);

        Mail::fake();

        app(NativeTaskService::class)->updateFields($task, [
            'current_state_id' => $done->id,
        ], $creator);

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
        $this->assertStringContainsString('SMTP path OK', Artisan::output());

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

    public function test_mail_diagnose_sample_fails_when_send_and_log_records_failed(): void
    {
        $this->seedAssignedTask();
        $this->unfakeMail();

        $to = 'diagnose-fail-'.uniqid().'@example.com';

        Mail::shouldReceive('mailer')->andReturn($this->stubMailerWithoutSmtpTransport());
        Mail::shouldReceive('raw')->once()->andReturnNull();

        $pending = Mockery::mock();
        $pending->shouldReceive('sendNow')
            ->once()
            ->andThrow(new RuntimeException('Simulated SMTP failure'));

        Mail::shouldReceive('to')->once()->with($to)->andReturn($pending);
        Mail::shouldReceive('purge')->never();

        $exit = Artisan::call('mail:diagnose', [
            '--send' => $to,
            '--sample' => 'task_assigned',
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringNotContainsString('SMTP path OK', $output);
        $this->assertStringContainsString('Simulated SMTP failure', $output);

        $this->assertTrue(
            NotificationLog::query()
                ->where('recipient', $to)
                ->where('body', 'task_assigned')
                ->where('status', 'failed')
                ->exists()
        );
    }

    public function test_send_task_assigned_retries_once_after_starttls_failure(): void
    {
        [$assignee, $task] = $this->seedAssignedTask();
        $this->unfakeMail();

        Mail::shouldReceive('mailer')->andReturn($this->stubMailerWithoutSmtpTransport());
        Mail::shouldReceive('purge')->once();

        $failing = Mockery::mock();
        $failing->shouldReceive('sendNow')
            ->once()
            ->andThrow(new RuntimeException(
                'Unable to connect with STARTTLS: stream_socket_enable_crypto(): SSL operation failed'
            ));

        $succeeding = Mockery::mock();
        $succeeding->shouldReceive('sendNow')->once()->andReturnNull();

        Mail::shouldReceive('to')
            ->twice()
            ->with($assignee->email)
            ->andReturn($failing, $succeeding);

        app(EmailNotificationService::class)->sendTaskAssigned($task, $assignee);

        $this->assertTrue(
            NotificationLog::query()
                ->where('recipient', $assignee->email)
                ->where('body', 'task_assigned')
                ->where('status', 'sent')
                ->exists()
        );
    }

    /**
     * Concrete Mailer mock that skips EnsureTlsCaBundle SMTP stream injection.
     */
    protected function stubMailerWithoutSmtpTransport(): \Illuminate\Mail\Mailer
    {
        $mailer = Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailer->shouldReceive('getSymfonyTransport')->andReturn(
            Mockery::mock(\Symfony\Component\Mailer\Transport\TransportInterface::class)
        );

        return $mailer;
    }

    /**
     * Restore the real Mail manager after seed helpers call Mail::fake().
     */
    protected function unfakeMail(): void
    {
        $root = Mail::getFacadeRoot();
        if ($root instanceof \Illuminate\Support\Testing\Fakes\MailFake) {
            Mail::swap($root->manager);
        }
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

        // Clear mailables recorded during fixture create (creator confirmation, etc.).
        Mail::fake();

        return [$creator, $assignee, $teammate, $task];
    }
}
