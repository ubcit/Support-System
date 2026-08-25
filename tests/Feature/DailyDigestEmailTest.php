<?php

namespace Tests\Feature;

use App\Mail\DailyDigestMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class DailyDigestEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_digest_uses_real_open_items_and_excludes_terminal_ones(): void
    {
        Mail::fake();

        $workspace = Workspace::create(['name' => 'Space', 'slug' => 'digest-'.uniqid()]);

        $employee = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Digest User',
            'email' => 'digest-'.uniqid().'@thespace.app',
            'role' => 'employee',
            'metadata' => ['email_notifications_enabled' => true],
        ]);

        $project = Project::create([
            'workspace_id' => $workspace->id,
            'name' => 'Digest Project',
            'code' => 'DIG-'.uniqid(),
            'status' => 'active',
        ]);
        $project->employees()->sync([$employee->id]);

        $workflow = Workflow::create([
            'name' => 'Default',
            'entity_type' => 'task',
            'is_default' => true,
        ]);
        $todo = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);
        $done = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'Done',
            'type' => 'completed',
            'order' => 2,
        ]);

        $openTask = Task::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Open digest task',
            'workflow_id' => $workflow->id,
            'current_state_id' => $todo->id,
            'due_date' => now()->addHours(12),
            'priority' => 'high',
        ]);
        $openTask->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $overdueTask = Task::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Overdue digest task',
            'workflow_id' => $workflow->id,
            'current_state_id' => $todo->id,
            'due_date' => now()->subDays(2)->startOfDay(),
            'priority' => 'urgent',
        ]);
        $overdueTask->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $completedTask = Task::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Completed digest task',
            'workflow_id' => $workflow->id,
            'current_state_id' => $done->id,
            'completed_at' => now(),
            'due_date' => now()->subDay(),
        ]);
        $completedTask->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $archivedTask = Task::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Archived digest task',
            'workflow_id' => $workflow->id,
            'current_state_id' => $todo->id,
            'archived_at' => now(),
            'due_date' => now()->addHours(6),
        ]);
        $archivedTask->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $openIssue = Issue::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Open digest issue',
            'assigned_to' => $employee->id,
            'status' => 'open',
            'priority' => 'high',
        ]);

        $resolvedIssue = Issue::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Resolved digest issue',
            'assigned_to' => $employee->id,
            'status' => 'resolved',
            'resolved_at' => now(),
            'priority' => 'medium',
        ]);

        $staleStatusIssue = Issue::factory()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'title' => 'Stale status digest issue',
            'assigned_to' => $employee->id,
            'status' => 'open',
            'resolved_at' => now(),
            'priority' => 'low',
        ]);

        $this->artisan('notifications:daily-digest')
            ->assertSuccessful();

        Mail::assertSent(DailyDigestMail::class, function (DailyDigestMail $mail) use ($employee, $openTask, $overdueTask, $openIssue, $completedTask, $archivedTask, $resolvedIssue, $staleStatusIssue) {
            if (! $mail->hasTo($employee->email)) {
                return false;
            }

            $this->assertSame(2, $mail->stats['open_tasks']);
            $this->assertSame(1, $mail->stats['overdue_tasks']);
            $this->assertSame(1, $mail->stats['due_soon']);
            $this->assertSame(1, $mail->stats['open_issues']);

            $this->assertTrue($mail->openTasks->contains('id', $openTask->id));
            $this->assertTrue($mail->openTasks->contains('id', $overdueTask->id));
            $this->assertFalse($mail->openTasks->contains('id', $completedTask->id));
            $this->assertFalse($mail->openTasks->contains('id', $archivedTask->id));

            $this->assertTrue($mail->overdueTasks->contains('id', $overdueTask->id));
            $this->assertTrue($mail->dueSoonTasks->contains('id', $openTask->id));

            $this->assertTrue($mail->openIssues->contains('id', $openIssue->id));
            $this->assertFalse($mail->openIssues->contains('id', $resolvedIssue->id));
            $this->assertFalse($mail->openIssues->contains('id', $staleStatusIssue->id));

            $html = view('emails.daily-digest', [
                'employee' => $mail->employee,
                'stats' => $mail->stats,
                'overdueTasks' => $mail->overdueTasks,
                'upcomingDeadlines' => $mail->upcomingDeadlines,
                'openTasks' => $mail->openTasks,
                'openIssues' => $mail->openIssues,
                'dueSoonTasks' => $mail->dueSoonTasks,
            ])->render();

            $this->assertStringContainsString('#'.$openIssue->id, $html);
            $this->assertStringContainsString('Open digest issue', $html);
            $this->assertStringContainsString('Open digest task', $html);
            $this->assertStringContainsString('Overdue digest task', $html);
            $this->assertStringNotContainsString('Completed digest task', $html);
            $this->assertStringNotContainsString('Archived digest task', $html);
            $this->assertStringNotContainsString('Resolved digest issue', $html);
            $this->assertStringNotContainsString('Stale status digest issue', $html);

            return true;
        });
    }

    public function test_daily_digest_respects_preference_opt_out(): void
    {
        Mail::fake();

        $workspace = Workspace::create(['name' => 'Space', 'slug' => 'digest-opt-'.uniqid()]);

        $employee = Employee::create([
            'workspace_id' => $workspace->id,
            'name' => 'Opt Out User',
            'email' => 'digest-opt-'.uniqid().'@thespace.app',
            'role' => 'employee',
            'metadata' => [
                'email_notifications_enabled' => true,
                'notification_preferences' => [
                    'daily_digest' => false,
                ],
            ],
        ]);

        $workflow = Workflow::create([
            'name' => 'Default',
            'entity_type' => 'task',
            'is_default' => true,
        ]);
        $todo = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);

        $task = Task::factory()->create([
            'workspace_id' => $workspace->id,
            'title' => 'Should not digest',
            'workflow_id' => $workflow->id,
            'current_state_id' => $todo->id,
        ]);
        $task->assignments()->create(['employee_id' => $employee->id, 'assigned_at' => now()]);

        $this->artisan('notifications:daily-digest')
            ->assertSuccessful();

        Mail::assertNotSent(DailyDigestMail::class);
    }
}
