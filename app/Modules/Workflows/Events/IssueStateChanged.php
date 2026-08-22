<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Issues\Models\Issue;
use Modules\Workflows\Models\WorkflowState;

class IssueStateChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Issue $issue,
        public WorkflowState $fromState,
        public WorkflowState $toState
    ) {}
}
