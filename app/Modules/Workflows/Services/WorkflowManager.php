<?php

namespace Modules\Workflows\Services;

use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Models\WorkflowTransition;

class WorkflowManager
{
    /**
     * Get the default workflow and initial state for a given entity type (e.g. 'task' or 'issue').
     */
    public function getDefaultState(string $entityType): ?WorkflowState
    {
        $workflow = Workflow::where('entity_type', $entityType)
            ->where('is_default', true)
            ->first();

        if (!$workflow) {
            $workflow = Workflow::where('entity_type', $entityType)->first();
        }

        if (!$workflow) {
            return null; // Handle via seeder in reality
        }

        return $workflow->states()->where('type', 'initial')->orderBy('order')->first();
    }

    /**
     * Verify if a transition is permitted.
     */
    public function canTransition(int $workflowId, int $fromStateId, int $toStateId, array $userRoles = []): bool
    {
        $transition = WorkflowTransition::where('workflow_id', $workflowId)
            ->where('from_state_id', $fromStateId)
            ->where('to_state_id', $toStateId)
            ->first();

        if (!$transition) {
            return false;
        }

        if (empty($transition->roles_allowed)) {
            return true; // No restriction
        }

        // Check if any of the user's roles for this item intersect with allowed roles
        return !empty(array_intersect($userRoles, $transition->roles_allowed));
    }
}
