<?php

namespace Modules\Notifications\Services;

use App\Mail\CommentMentionMail;
use App\Mail\DailyDigestMail;
use App\Mail\IssueAssignedMail;
use App\Mail\NewIssueMail;
use App\Mail\OverdueTaskMail;
use App\Mail\ProjectDeadlineMail;
use App\Mail\ProjectMemberAddedMail;
use App\Mail\ReviewDecisionMail;
use App\Mail\ReviewRequestedMail;
use App\Mail\SessionReadyMail;
use App\Mail\TaskAssignedMail;
use App\Mail\TaskCompletedMail;
use App\Mail\TaskCreatedMail;
use App\Mail\TaskDueSoonMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Communication\Models\ConversationSession;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use Modules\Notifications\Models\NotificationLog;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Tasks\Models\TaskComment;

class EmailNotificationService
{
    /**
     * Send overdue task reminders to all employees with overdue tasks.
     */
    public function sendOverdueReminders(): int
    {
        $sent = 0;

        $employees = $this->getNotifiableEmployees();

        foreach ($employees as $employee) {
            $overdueTasks = $this->getOverdueTasksForEmployee($employee);

            if ($overdueTasks->isEmpty()) {
                continue;
            }

            $this->sendAndLog(
                $employee,
                new OverdueTaskMail($employee, $overdueTasks),
                'overdue_tasks',
                $overdueTasks->count().' overdue tasks',
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Send due-soon reminders (tasks due within 24 hours).
     */
    public function sendDueSoonReminders(): int
    {
        $sent = 0;

        $employees = $this->getNotifiableEmployees();

        foreach ($employees as $employee) {
            $dueSoonTasks = $this->openTasksQueryForEmployee($employee)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now(), now()->addDay()])
                ->with('project')
                ->orderBy('due_date')
                ->get();

            if ($dueSoonTasks->isEmpty()) {
                continue;
            }

            $this->sendAndLog(
                $employee,
                new TaskDueSoonMail($employee, $dueSoonTasks),
                'due_soon',
                $dueSoonTasks->count().' tasks due soon',
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Send project deadline alerts (projects with deadlines within 3 days).
     */
    public function sendProjectDeadlineAlerts(): int
    {
        $sent = 0;

        $employees = $this->getNotifiableEmployees();

        foreach ($employees as $employee) {
            $projects = Project::query()
                ->whereHas('employees', fn ($q) => $q->where('employees.id', $employee->id))
                ->whereNotNull('deadline_at')
                ->whereBetween('deadline_at', [now(), now()->addDays(3)])
                ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
                ->get();

            if ($projects->isEmpty()) {
                continue;
            }

            $this->sendAndLog(
                $employee,
                new ProjectDeadlineMail($employee, $projects),
                'project_deadline',
                $projects->count().' project deadlines approaching',
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Send daily digest emails to all employees.
     */
    public function sendDailyDigests(): int
    {
        $sent = 0;
        $listLimit = 10;

        $employees = $this->getNotifiableEmployees();

        foreach ($employees as $employee) {
            if (! $this->shouldNotify($employee, 'daily_digest')) {
                continue;
            }

            $openTasksCount = $this->openTasksQueryForEmployee($employee)->count();

            $overdueTasks = $this->getOverdueTasksForEmployee($employee);

            $dueSoonQuery = $this->openTasksQueryForEmployee($employee)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now(), now()->addDay()]);

            $dueSoonCount = (clone $dueSoonQuery)->count();
            $dueSoonTasks = (clone $dueSoonQuery)
                ->with('project')
                ->orderBy('due_date')
                ->limit($listLimit)
                ->get();

            $openIssuesQuery = $this->openIssuesQueryForEmployee($employee);
            $openIssuesCount = (clone $openIssuesQuery)->count();
            $openIssues = (clone $openIssuesQuery)
                ->with('project')
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->orderByDesc('created_at')
                ->limit($listLimit)
                ->get();

            $openTasks = $this->openTasksQueryForEmployee($employee)
                ->with(['project', 'currentState'])
                ->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
                ->limit($listLimit)
                ->get();

            $upcomingDeadlines = Project::query()
                ->whereHas('employees', fn ($q) => $q->where('employees.id', $employee->id))
                ->whereNotNull('deadline_at')
                ->whereBetween('deadline_at', [now(), now()->addWeek()])
                ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
                ->orderBy('deadline_at')
                ->get();

            if (
                $openTasksCount === 0
                && $overdueTasks->isEmpty()
                && $openIssuesCount === 0
                && $upcomingDeadlines->isEmpty()
            ) {
                continue;
            }

            $stats = [
                'open_tasks' => $openTasksCount,
                'overdue_tasks' => $overdueTasks->count(),
                'due_soon' => $dueSoonCount,
                'open_issues' => $openIssuesCount,
                'list_limit' => $listLimit,
            ];

            $this->sendAndLog(
                $employee,
                new DailyDigestMail(
                    $employee,
                    $stats,
                    $overdueTasks,
                    $upcomingDeadlines,
                    $openTasks,
                    $openIssues,
                    $dueSoonTasks,
                ),
                'daily_digest',
                'Daily digest',
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Send task-assigned notification email.
     */
    public function sendTaskAssigned(Task $task, Employee $employee): void
    {
        if (! $this->shouldNotify($employee, 'task_assigned')) {
            return;
        }

        $task->loadMissing('project');

        $this->sendAndLog(
            $employee,
            new TaskAssignedMail($employee, $task),
            'task_assigned',
            'Task assigned: '.$task->title,
        );
    }

    /**
     * Send task-completed notification to assignees, creator, and watchers.
     */
    public function sendTaskCompleted(Task $task): void
    {
        $task->loadMissing(['project', 'assignees', 'creator', 'stakeholders.employee']);

        $recipients = collect($task->assignees);

        if ($task->creator) {
            $recipients->push($task->creator);
        }

        foreach ($task->stakeholders as $stakeholder) {
            if ($stakeholder->employee) {
                $recipients->push($stakeholder->employee);
            }
        }

        foreach ($recipients->unique('id') as $employee) {
            if (! $employee instanceof Employee) {
                continue;
            }
            if (! $this->shouldNotify($employee, 'task_completed')) {
                continue;
            }

            $this->sendAndLog(
                $employee,
                new TaskCompletedMail($employee, $task),
                'task_completed',
                'Task completed: '.$task->title,
            );
        }
    }

    /**
     * Notify project members that a task was created (excludes creator).
     */
    public function sendTaskCreated(Task $task, ?Employee $exclude = null): void
    {
        $task->loadMissing(['project.employees', 'creator', 'assignees']);

        if (! $task->project) {
            return;
        }

        $creatorName = $task->creator?->name ?? $exclude?->name;
        $skipIds = $task->assignees->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($exclude) {
            $skipIds[] = (int) $exclude->id;
        }
        if ($task->created_by) {
            $skipIds[] = (int) $task->created_by;
        }
        $skipIds = array_unique($skipIds);

        foreach ($task->project->employees as $employee) {
            if (in_array((int) $employee->id, $skipIds, true)) {
                continue;
            }
            if (! $this->shouldNotify($employee, 'task_created')) {
                continue;
            }

            $this->sendAndLog(
                $employee,
                new TaskCreatedMail($employee, $task, $creatorName),
                'task_created',
                'New task: '.$task->title,
            );
        }
    }

    public function sendIssueAssigned(Issue $issue, Employee $employee): void
    {
        if (! $this->shouldNotify($employee, 'issue_assigned')) {
            return;
        }

        $issue->loadMissing(['project', 'customer']);

        $this->sendAndLog(
            $employee,
            new IssueAssignedMail($employee, $issue),
            'issue_assigned',
            'Issue assigned: '.$issue->title,
        );
    }

    public function sendProjectMemberAdded(Project $project, Employee $employee): void
    {
        if (! $this->shouldNotify($employee, 'project_member_added')) {
            return;
        }

        $this->sendAndLog(
            $employee,
            new ProjectMemberAddedMail($employee, $project),
            'project_member_added',
            'Added to project: '.$project->name,
        );
    }

    public function sendConversationNeedsHuman(ConversationSession $session, Employee $employee): void
    {
        if (! $this->shouldNotify($employee, 'conversation_needs_human')) {
            return;
        }

        $session->loadMissing(['conversation.customer', 'project']);
        $customer = $session->conversation?->customer;
        $project = $session->project;

        $this->sendAndLog(
            $employee,
            new SessionReadyMail($employee, $session, $customer, $project),
            'conversation_needs_human',
            'Conversation needs attention',
        );
    }

    /**
     * Send comment-mention notification email.
     */
    public function sendCommentMention(TaskComment $comment, Employee $employee): void
    {
        if (! $this->shouldNotify($employee, 'comment_mention')) {
            return;
        }

        $comment->loadMissing(['task.project', 'employee', 'mentions']);
        $taskTitle = $comment->task?->title ?? 'a task';

        $this->sendAndLog(
            $employee,
            new CommentMentionMail($employee, $comment),
            'comment_mention',
            "Mentioned in: {$taskTitle}",
        );

        $comment->mentions()
            ->where('employee_id', $employee->id)
            ->update(['notified' => true]);
    }

    public function sendReviewDecision(Task $task, Employee $employee, string $type): void
    {
        if (! $this->shouldNotify($employee, $type)) {
            return;
        }

        $task->loadMissing(['reviewers.employee', 'project']);
        $reviewer = $type === 'task_approved'
            ? $task->reviewers->where('approval_status', 'approved')->sortByDesc('approved_at')->first()
            : $task->latestChangesRequest();

        $this->sendAndLog(
            $employee,
            new ReviewDecisionMail(
                $employee,
                $task,
                $type === 'task_approved' ? 'approved' : 'changes_requested',
                $reviewer?->employee?->name ?? 'A reviewer',
                $reviewer?->approval_note,
            ),
            $type,
            ($type === 'task_approved' ? 'Task approved: ' : 'Changes requested: ').$task->title,
        );
    }

    public function sendReviewRequested(Task $task, Employee $employee, ?int $actorId = null): void
    {
        if (! $this->shouldNotify($employee, 'review_requested')) {
            return;
        }

        $task->loadMissing(['project', 'assignees']);
        $submitter = $actorId ? Employee::find($actorId) : $task->assignees->first();

        $this->sendAndLog(
            $employee,
            new ReviewRequestedMail(
                $employee,
                $task,
                $submitter?->name ?? 'Someone',
            ),
            'review_requested',
            'Ready for review: '.$task->title,
        );
    }

    /**
     * Send new-issue notification to assignee and project members.
     */
    public function sendNewIssue(Issue $issue): void
    {
        $issue->loadMissing(['project.employees', 'assignee', 'customer']);

        $notified = collect();

        if ($issue->assignee && $this->shouldNotify($issue->assignee, 'new_issue')) {
            $this->sendAndLog(
                $issue->assignee,
                new NewIssueMail($issue->assignee, $issue),
                'new_issue',
                'New issue: '.$issue->title,
            );
            $notified->push($issue->assignee->id);
        }

        if ($issue->project) {
            foreach ($issue->project->employees as $employee) {
                if ($notified->contains($employee->id)) {
                    continue;
                }
                if (! $this->shouldNotify($employee, 'new_issue')) {
                    continue;
                }

                $this->sendAndLog(
                    $employee,
                    new NewIssueMail($employee, $issue),
                    'new_issue',
                    'New issue: '.$issue->title,
                );
            }
        }
    }

    protected function openTasksQueryForEmployee(Employee $employee)
    {
        return Task::query()
            ->whereHas('assignees', fn ($q) => $q->where('employees.id', $employee->id))
            ->whereNull('archived_at')
            ->whereNull('completed_at')
            ->whereHas('currentState', fn ($q) => $q->whereNotIn('type', ['completed', 'cancelled']));
    }

    protected function openIssuesQueryForEmployee(Employee $employee)
    {
        return Issue::query()
            ->where('assigned_to', $employee->id)
            ->whereNull('resolved_at')
            ->whereNull('closed_at')
            ->whereNotIn('status', ['resolved', 'closed']);
    }

    protected function getOverdueTasksForEmployee(Employee $employee): Collection
    {
        return $this->openTasksQueryForEmployee($employee)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->with('project')
            ->orderBy('due_date')
            ->get();
    }

    protected function getNotifiableEmployees(): Collection
    {
        return Employee::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('metadata->email_notifications_enabled')
                    ->orWhere('metadata->email_notifications_enabled', true);
            })
            ->get();
    }

    protected function shouldNotify(Employee $employee, string $type): bool
    {
        if (empty($employee->email)) {
            return false;
        }

        $meta = $employee->metadata ?? [];

        if (isset($meta['email_notifications_enabled']) && $meta['email_notifications_enabled'] === false) {
            return false;
        }

        $prefs = $meta['notification_preferences'] ?? [];
        if (isset($prefs[$type]) && $prefs[$type] === false) {
            return false;
        }

        return true;
    }

    protected function sendAndLog(Employee $employee, $mailable, string $type, string $subject): void
    {
        try {
            // sendNow: notification mailables must not re-queue via ShouldQueue.
            // Event emails are dispatched sync via SendNotificationEmailJob::dispatchNotify;
            // Mail::send() on a ShouldQueue mailable would only enqueue SendQueuedMailable.
            Mail::to($employee->email)->sendNow($mailable);

            NotificationLog::create([
                'channel' => 'email',
                'recipient' => $employee->email,
                'subject' => $subject,
                'body' => $type,
                'status' => 'sent',
                'notifiable_type' => Employee::class,
                'notifiable_id' => $employee->id,
                'sent_at' => now(),
                'metadata' => ['type' => $type],
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to send {$type} email to {$employee->email}: {$e->getMessage()}");

            NotificationLog::create([
                'channel' => 'email',
                'recipient' => $employee->email,
                'subject' => $subject,
                'body' => $type,
                'status' => 'failed',
                'notifiable_type' => Employee::class,
                'notifiable_id' => $employee->id,
                'metadata' => ['type' => $type, 'error' => $e->getMessage()],
            ]);
        }
    }
}
