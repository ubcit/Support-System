<?php

namespace Modules\Workflows\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\WorkflowState;

class TaskStateChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public WorkflowState $fromState,
        public WorkflowState $toState
    ) {}
}
