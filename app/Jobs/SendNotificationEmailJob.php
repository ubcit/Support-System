<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Communication\Models\ConversationSession;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;

class SendNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public string $type,
        public int $modelId,
        public ?int $employeeId = null,
        public ?int $actorId = null,
    ) {}

    public function handle(EmailNotificationService $service): void
    {
        match ($this->type) {
            'task_assigned' => $this->handleTaskAssigned($service),
            'task_created' => $this->handleTaskCreated($service),
            'task_completed' => $this->handleTaskCompleted($service),
            'new_issue' => $this->handleNewIssue($service),
            'issue_assigned' => $this->handleIssueAssigned($service),
            'project_member_added' => $this->handleProjectMemberAdded($service),
            'conversation_needs_human' => $this->handleConversationNeedsHuman($service),
            'comment_mention' => $this->handleCommentMention($service),
            'task_changes_requested', 'task_approved' => $this->handleReviewDecision($service),
            'review_requested' => $this->handleReviewRequested($service),
            default => null,
        };
    }

    protected function handleTaskAssigned(EmailNotificationService $service): void
    {
        $task = Task::find($this->modelId);
        $employee = Employee::find($this->employeeId);

        if ($task && $employee) {
            $service->sendTaskAssigned($task, $employee);
        }
    }

    protected function handleTaskCreated(EmailNotificationService $service): void
    {
        $task = Task::find($this->modelId);
        $exclude = $this->employeeId ? Employee::find($this->employeeId) : null;

        if ($task) {
            $service->sendTaskCreated($task, $exclude);
        }
    }

    protected function handleTaskCompleted(EmailNotificationService $service): void
    {
        $task = Task::find($this->modelId);

        if ($task) {
            $service->sendTaskCompleted($task);
        }
    }

    protected function handleNewIssue(EmailNotificationService $service): void
    {
        $issue = Issue::find($this->modelId);

        if ($issue) {
            $service->sendNewIssue($issue);
        }
    }

    protected function handleIssueAssigned(EmailNotificationService $service): void
    {
        $issue = Issue::find($this->modelId);
        $employee = Employee::find($this->employeeId);

        if ($issue && $employee) {
            $service->sendIssueAssigned($issue, $employee);
        }
    }

    protected function handleProjectMemberAdded(EmailNotificationService $service): void
    {
        $project = Project::find($this->modelId);
        $employee = Employee::find($this->employeeId);

        if ($project && $employee) {
            $service->sendProjectMemberAdded($project, $employee);
        }
    }

    protected function handleConversationNeedsHuman(EmailNotificationService $service): void
    {
        $session = ConversationSession::find($this->modelId);
        $employee = Employee::find($this->employeeId);

        if ($session && $employee) {
            $service->sendConversationNeedsHuman($session, $employee);
        }
    }

    protected function handleCommentMention(EmailNotificationService $service): void
    {
        $comment = TaskComment::with(['task', 'employee'])->find($this->modelId);
        $employee = Employee::find($this->employeeId);

        if ($comment && $employee) {
            $service->sendCommentMention($comment, $employee);
        }
    }

    protected function handleReviewDecision(EmailNotificationService $service): void
    {
        $task = Task::find($this->modelId);
        $employee = Employee::find($this->employeeId);

        if ($task && $employee) {
            $service->sendReviewDecision($task, $employee, $this->type);
        }
    }

    protected function handleReviewRequested(EmailNotificationService $service): void
    {
        $task = Task::find($this->modelId);
        $employee = Employee::find($this->employeeId);

        if ($task && $employee) {
            $service->sendReviewRequested($task, $employee, $this->actorId);
        }
    }
}
