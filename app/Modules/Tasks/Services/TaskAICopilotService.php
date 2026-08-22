<?php

namespace Modules\Tasks\Services;

use Modules\AI\Contracts\AIProviderInterface;
use Modules\Communication\Models\Conversation;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;

class TaskAICopilotService
{
    public function __construct(
        protected AIProviderInterface $aiProvider
    ) {}

    public function summarizeTask(Task $task): array
    {
        $response = [];
        try {
            $response = $this->aiProvider->analyzeConversation(
                ['task_uuid' => $task->uuid, 'title' => $task->title],
                [['role' => 'user', 'text' => $task->description ?? $task->title]]
            );
        } catch (\Throwable $e) {
            $response = [];
        }

        return [
            'summary' => $response['summary'] ?? "Task '{$task->title}' focuses on resolution of logged items.",
            'risk_assessment' => $response['risk'] ?? ($task->isOverdue() ? 'High risk: Task is overdue' : 'Low risk'),
            'recommended_priority' => $task->priority?->value ?? 'medium',
            'recommended_actions' => [
                'Review checklists',
                'Verify assignees',
                'Check milestone progress',
            ],
            'disclaimer' => 'AI suggestion only. Human approval required for all actions.',
        ];
    }

    public function summarizeConversation(Conversation $conversation): array
    {
        $messages = $conversation->messages->map(fn ($m) => ['role' => $m->direction ?? 'user', 'text' => $m->body])->toArray();
        $response = [];

        try {
            $response = $this->aiProvider->analyzeConversation(
                ['conversation_id' => $conversation->id],
                $messages
            );
        } catch (\Throwable $e) {
            $response = [];
        }

        return [
            'summary' => $response['summary'] ?? "Customer requested support regarding active conversation.",
            'extracted_intent' => $response['intent'] ?? 'support_request',
            'suggested_task_title' => "Follow up on customer request",
            'suggested_checklist' => [
                'Verify customer identity and account',
                'Inspect reported error or requirement',
                'Confirm resolution with customer',
            ],
            'disclaimer' => 'AI suggestion only. Human approval required for all actions.',
        ];
    }

    public function summarizeProject(Project $project): array
    {
        $tasksCount = $project->tasks()->count();
        $overdueCount = $project->tasks()->get()->filter(fn ($t) => $t->isOverdue())->count();

        return [
            'project_name' => $project->name,
            'total_tasks' => $tasksCount,
            'overdue_tasks' => $overdueCount,
            'health_score' => $overdueCount > 0 ? 'Needs Attention' : 'Healthy',
            'ai_insights' => [
                'Maintain current velocity',
                'Focus resources on overdue task items',
            ],
            'disclaimer' => 'AI suggestion only. Human approval required for all actions.',
        ];
    }

    public function generateChecklist(string $taskTitle, ?string $description = null): array
    {
        return [
            'task_title' => $taskTitle,
            'suggested_items' => [
                'Initial triage and requirement review',
                'Implementation and local testing',
                'Peer code review and verification',
                'Documentation and customer notification',
            ],
            'disclaimer' => 'AI suggestion only. Human approval required to apply checklist.',
        ];
    }

    public function generateReply(Task $task): array
    {
        return [
            'task_title' => $task->title,
            'suggested_reply' => "Hello, we have updated your task '{$task->title}' to the current stage and our team is actively addressing it. Thank you for your patience.",
            'disclaimer' => 'AI suggestion only. Human approval required to send reply.',
        ];
    }
}
