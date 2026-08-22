<?php

namespace Modules\Synchronization\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Synchronization\Models\ProviderEvent;
use Modules\Synchronization\DTOs\ProviderEventDTO;
use Modules\Synchronization\Models\Synchronization;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Services\WorkflowManager;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Events\TaskStateChanged;
use Modules\Communication\Services\WorkCommunicationService;

class ProcessProviderEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ProviderEvent $eventRecord,
        public ProviderEventDTO $dto
    ) {}

    public function handle(WorkflowManager $workflowManager, WorkCommunicationService $commsService): void
    {
        try {
            // Find internal object via Provider Identity Registry
            $syncRecord = Synchronization::where('provider', $this->dto->provider)
                ->where('provider_object_id', $this->dto->providerObjectId)
                ->first();

            if (!$syncRecord) {
                // Ignore webhooks for objects we don't know about
                $this->markAs('ignored', 'Object not found in synchronization registry');
                return;
            }

            $internalObject = $syncRecord->syncable;
            if (!$internalObject instanceof Task) {
                $this->markAs('ignored', 'Only Task syncing is currently supported inbound');
                return;
            }

            // Route based on Event Type
            if ($this->dto->eventType === 'taskStatusUpdated') {
                $this->handleStatusUpdate($internalObject, $workflowManager, $commsService);
            } elseif ($this->dto->eventType === 'taskCommentPosted') {
                $this->handleCommentPosted($internalObject, $commsService);
            } else {
                $this->markAs('ignored', "Unhandled event type: {$this->dto->eventType}");
                return;
            }

            $this->markAs('processed');

        } catch (\Exception $e) {
            $this->markAs('failed', $e->getMessage());
            throw $e; // Re-throw to allow queue retries
        }
    }

    protected function handleStatusUpdate(Task $task, WorkflowManager $workflowManager, WorkCommunicationService $commsService): void
    {
        // 1. Determine new internal WorkflowState from webhook payload
        // In reality, this requires reverse mapping `SyncMapping::taskStateToExternal()`
        // For scaffolding, we assume the webhook gives us a valid state name in payload
        $newStatusName = $this->dto->payload['history_items'][0]['after']['status'] ?? null;
        if (!$newStatusName) {
            throw new \Exception('No status provided in webhook payload');
        }

        // Find internal state by name (simplification for Phase 3 scaffolding)
        $newState = WorkflowState::where('workflow_id', $task->workflow_id)
            ->where('name', 'like', "%{$newStatusName}%")
            ->first();

        if (!$newState) {
            throw new \Exception("Could not map external status '{$newStatusName}' to internal WorkflowState");
        }

        // 2. Conflict & Validation via WorkflowManager
        if ($task->current_state_id === $newState->id) {
            return; // Already matches
        }

        if (!$workflowManager->canTransition($task->workflow_id, $task->current_state_id, $newState->id)) {
            // Reject invalid transition
            $commsService->logSystemEvent($task, 'system_event', "Synchronization Conflict: External provider attempted illegal transition to {$newState->name}");
            throw new \Exception("Illegal state transition from {$task->current_state_id} to {$newState->id}");
        }

        // 3. Apply
        $oldState = WorkflowState::find($task->current_state_id);
        $task->update(['current_state_id' => $newState->id]);
        
        $commsService->logSystemEvent($task, 'status_change', "Status changed to {$newState->name} via external sync", ['from' => $oldState?->name, 'to' => $newState->name]);
        
        TaskStateChanged::dispatch($task, $oldState, $newState);
    }

    protected function handleCommentPosted(Task $task, WorkCommunicationService $commsService): void
    {
        $commentText = $this->dto->payload['history_items'][0]['comment']['text_content'] ?? 'Empty Comment';
        
        // Save as WorkCommunication with author_type = null (system/external)
        $commsService->postComment($task, null, 'comment', $commentText, 'internal');
    }

    protected function markAs(string $status, ?string $error = null): void
    {
        $this->eventRecord->update([
            'status' => $status,
            'error' => $error,
            'processed_at' => now(),
        ]);
    }
}
