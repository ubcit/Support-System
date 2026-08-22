<?php

namespace Modules\Tasks\Services;

use Illuminate\Support\Facades\Log;
use Modules\AI\DTOs\AIResult;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;
use App\Jobs\SendNotificationEmailJob;
use Modules\Tasks\Events\TaskCreated;
use Modules\Tasks\Models\Task;

class WorkManagementEngine
{
    public const CONFIDENCE_AUTO_ASSIGN = 0.90;

    /**
     * Transforms a Resolved Issue into internal work items.
     */
    public function generateWorkItems(Issue $issue, AIResult $aiResult): void
    {
        Log::info("WorkManagementEngine running for Issue {$issue->uuid}");

        // Determine Work Item Type (simple heuristic for now)
        $type = 'task';
        if (stripos($aiResult->title ?? '', 'bug') !== false || stripos($aiResult->title ?? '', 'fix') !== false) {
            $type = 'bug';
        } elseif (in_array('investigation', $aiResult->tags)) {
            $type = 'investigation';
        }

        $workflowManager = app(\Modules\Workflows\Services\WorkflowManager::class);
        $initialState = $workflowManager->getDefaultState('task');

        // Create the primary Work Item
        $task = Task::create([
            'issue_id' => $issue->id,
            'project_id' => $issue->project_id,
            'type' => $type,
            'title' => $aiResult->title ?? 'New Work Item',
            'summary' => $aiResult->summary ?? 'Generated from conversation.',
            'description' => $aiResult->description,
            'workflow_id' => $initialState?->workflow_id,
            'current_state_id' => $initialState?->id,
            'priority' => 'medium', // Could be inferred by AI later
            'sync_status' => 'queued',
            'metadata' => [
                'ai_tags' => $aiResult->tags,
                'source' => 'ai_generated',
            ]
        ]);

        $this->logTimeline($task, 'created', "Work Item created by Work Management Engine. Type: {$type}");

        // Employee Assignment Matching
        $employee = $this->matchEmployee($aiResult);

        // Check global setting to bypass boss/pending acceptance
        $workspace = \Modules\MultiTenancy\Models\Workspace::where('is_active', true)->first();
        $autoAssign = $workspace ? $workspace->getSetting('auto_assign_tasks', true) : true;

        if ($employee) {
            $assignmentStatus = $autoAssign ? 'accepted' : 'pending';
            
            $task->assignments()->create([
                'employee_id' => $employee->id,
                'status' => $assignmentStatus,
                'role' => 'assignee',
            ]);

            $logMsg = $autoAssign 
                ? "Auto-assigned to {$employee->name} directly (Boss review bypassed)"
                : "Auto-assigned to {$employee->name} (Pending Acceptance)";
                
            $this->logTimeline($task, 'assigned', $logMsg, $employee->id);

            SendNotificationEmailJob::dispatch('task_assigned', $task->id, $employee->id);
        } else {
            $this->logTimeline($task, 'needs_assignment', "Added to unassigned queue.");
        }

        // Emit Domain Event for Sync Listeners
        TaskCreated::dispatch($task);
    }

    protected function matchEmployee(AIResult $aiResult): ?Employee
    {
        if ($aiResult->employeeConfidence >= self::CONFIDENCE_AUTO_ASSIGN && $aiResult->employeeMatch) {
            return Employee::where('name', 'LIKE', "%{$aiResult->employeeMatch}%")->first();
        }
        return null;
    }

    protected function logTimeline(Task $task, string $action, string $description, ?int $employeeId = null): void
    {
        $commsService = app(\Modules\Communication\Services\WorkCommunicationService::class);
        $commsService->logSystemEvent($task, 'system_event', $description, [
            'action' => $action,
            'employee_id' => $employeeId,
        ]);
    }
}
