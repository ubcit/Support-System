<?php

namespace Modules\Issues\Services;

use Illuminate\Support\Facades\Log;
use Modules\AI\DTOs\AIResult;
use Modules\Communication\Models\Conversation;
use Modules\Communication\Models\Message;
use Modules\Issues\Models\Issue;
use Modules\Projects\Models\Project;

class ConversationResolutionEngine
{
    public const CONFIDENCE_AUTO_ACCEPT = 0.95;
    public const CONFIDENCE_REVIEW = 0.60;

    public function resolve(Conversation $conversation, Message $latestMessage): void
    {
        $metadata = $conversation->metadata ?? [];
        $analyses = $metadata['ai_analyses'] ?? [];

        if (empty($analyses)) {
            Log::warning("Resolution Engine aborted: No AI analysis found for Conversation {$conversation->uuid}");
            return;
        }

        // Get latest analysis
        $latestAnalysis = end($analyses);
        $aiResult = AIResult::fromArray($latestAnalysis['result']);

        Log::info("Resolution Engine running for Conversation {$conversation->uuid} based on schema v{$aiResult->schemaVersion}");

        $project = $this->resolveProject($aiResult);

        // Duplicate Detection: Check if there is already an open issue for this conversation
        // Or perhaps a very similar issue for this customer recently.
        // For now, if the conversation is already linked to an issue, we append.
        // If not, we search for open issues by this customer on this project.
        
        $existingIssue = $conversation->issues()->whereNull('resolved_at')->first();

        if (!$existingIssue && $project && $conversation->customer_id) {
            // Find recent open issues by this customer on this project
            // In a real system, we'd use semantic search or let AI determine if it's a duplicate.
            // For V1, we create a new issue if none is linked directly to the conversation.
        }

        if ($existingIssue) {
            $this->appendEvidenceToIssue($existingIssue, $conversation, $aiResult);
        } else {
            $existingIssue = $this->createNewIssue($conversation, $aiResult, $project);
        }

        // Update Message pipeline status
        $status = $latestMessage->processing_status ?? [];
        $status['issue_linked'] = true;
        $latestMessage->update(['processing_status' => $status]);

        // Trigger Internal Task Generation (Step 5) via the Work Management Engine
        \Modules\Tasks\Jobs\RunWorkManagementEngine::dispatch($existingIssue, $aiResult);
    }

    protected function resolveProject(AIResult $aiResult): ?Project
    {
        if ($aiResult->projectConfidence >= self::CONFIDENCE_AUTO_ACCEPT && $aiResult->projectMatch) {
            return Project::where('name', 'LIKE', "%{$aiResult->projectMatch}%")->first();
        }
        return null;
    }

    protected function createNewIssue(Conversation $conversation, AIResult $aiResult, ?Project $project): Issue
    {
        $workflowManager = app(\Modules\Workflows\Services\WorkflowManager::class);
        $initialState = $workflowManager->getDefaultState('issue');

        $issue = Issue::create([
            'title' => $aiResult->title ?? 'New Customer Issue',
            'description' => $aiResult->description ?? 'No description provided.',
            'conversation_id' => $conversation->id,
            'customer_id' => $conversation->customer_id,
            'project_id' => $project?->id,
            'workflow_id' => $initialState?->workflow_id,
            'current_state_id' => $initialState?->id,
            'ai_summary' => $aiResult->summary,
            'metadata' => [
                'tags' => $aiResult->tags,
                'ai_confidence' => $aiResult->overallConfidence,
                'needs_review' => $aiResult->overallConfidence < self::CONFIDENCE_REVIEW,
            ]
        ]);

        $this->logTimeline($issue, 'issue_created', "Issue automatically created from Conversation {$conversation->uuid}.");
        $this->logTimeline($issue, 'ai_completed', "AI Analysis completed. Confidence: {$aiResult->overallConfidence}");

        if ($project) {
            $this->logTimeline($issue, 'project_matched', "Auto-matched to project: {$project->name}");
        }

        return $issue;
    }

    protected function appendEvidenceToIssue(Issue $issue, Conversation $conversation, AIResult $aiResult): void
    {
        // Update the issue if needed, but mainly just log that new info arrived
        $this->logTimeline($issue, 'evidence_appended', "New messages appended from Conversation {$conversation->uuid}.");
    }

    protected function logTimeline(Issue $issue, string $action, string $description): void
    {
        $commsService = app(\Modules\Communication\Services\WorkCommunicationService::class);
        $commsService->logSystemEvent($issue, 'system_event', $description, ['action' => $action]);
    }
}
