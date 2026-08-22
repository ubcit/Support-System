<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Enums\TaskStatus;
use Modules\Tasks\Models\TaskStakeholder;
use Modules\Tasks\Services\NativeTaskService;
use Modules\Tasks\Support\TaskLifecycle;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Models\WorkflowTransition;
use Tests\TestCase;

class TaskLifecycleTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected Workflow $workflow;

    protected WorkflowState $todoState;

    protected WorkflowState $inProgressState;

    protected WorkflowState $reviewState;

    protected WorkflowState $doneState;

    protected Employee $employee;

    protected Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = Workflow::create([
            'name' => 'Task Lifecycle Timeline Workflow',
            'entity_type' => 'task',
            'is_default' => true,
        ]);

        $this->todoState = WorkflowState::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);

        $this->inProgressState = WorkflowState::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'In Progress',
            'type' => 'active',
            'order' => 2,
        ]);

        $this->reviewState = WorkflowState::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Review',
            'type' => 'active',
            'order' => 3,
        ]);

        $this->doneState = WorkflowState::create([
            'workflow_id' => $this->workflow->id,
            'name' => 'Done',
            'type' => 'completed',
            'order' => 4,
        ]);

        WorkflowTransition::create([
            'workflow_id' => $this->workflow->id,
            'from_state_id' => $this->todoState->id,
            'to_state_id' => $this->inProgressState->id,
        ]);

        WorkflowTransition::create([
            'workflow_id' => $this->workflow->id,
            'from_state_id' => $this->inProgressState->id,
            'to_state_id' => $this->reviewState->id,
        ]);

        WorkflowTransition::create([
            'workflow_id' => $this->workflow->id,
            'from_state_id' => $this->reviewState->id,
            'to_state_id' => $this->doneState->id,
        ]);

        $this->employee = Employee::create([
            'name' => 'John Developer',
            'email' => 'john@thespace.app',
            'role' => 'Software Engineer',
        ]);

        $this->manager = Employee::create([
            'name' => 'Jane Boss',
            'email' => 'jane@thespace.app',
            'role' => 'boss',
        ]);
    }

    public function test_status_dwell_segments_cycle_time(): void
    {
        $service = app(NativeTaskService::class);
        $lifecycle = app(TaskLifecycle::class);

        $t0 = Carbon::parse('2026-08-20 10:00:00');
        $t1 = Carbon::parse('2026-08-20 12:00:00'); // +2h (To Do dwell)
        $t2 = Carbon::parse('2026-08-20 13:30:00'); // +1.5h (In Progress dwell)
        $t3 = Carbon::parse('2026-08-20 14:15:00'); // +45m (Review dwell, completion)

        Carbon::setTestNow($t0);

        $task = $service->createTask([
            'title' => 'Lifecycle Test Task',
            'description' => 'Test status timeline reconstruction',
            'priority' => 'high',
            'current_state_id' => $this->todoState->id,
        ], $this->employee);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Lifecycle Test Task',
        ]);

        $this->assertNull($task->started_at, 'started_at should be set only when entering an active state.');

        Carbon::setTestNow($t1);
        $service->moveToState($task, $this->inProgressState->id, $this->employee);

        $this->assertSame($t1->format('Y-m-d H:i:s'), $task->fresh()->started_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow($t2);
        $service->moveToState($task->fresh(), $this->reviewState->id, $this->employee);

        // started_at should not change when moving between active states.
        $this->assertSame($t1->format('Y-m-d H:i:s'), $task->fresh()->started_at?->format('Y-m-d H:i:s'));

        // Moving to Done from "Review" requires reviewer approval.
        TaskStakeholder::create([
            'task_id' => $task->fresh()->id,
            'employee_id' => $this->employee->id,
            'role' => 'reviewer',
            'approval_status' => 'approved',
            'assigned_by' => $this->employee->id,
            'approved_at' => $t2,
        ]);

        Carbon::setTestNow($t3);
        $service->moveToState($task->fresh(), $this->doneState->id, $this->manager);

        $task = $task->fresh()->load(['activityLogs', 'currentState']);

        $segments = $lifecycle->segments($task);
        $cycleSeconds = $lifecycle->cycleSeconds($task);

        $dwells = array_values(array_filter($segments, fn ($seg) => (int) ($seg['seconds'] ?? 0) > 0));

        $this->assertCount(3, $dwells, 'Expected exactly 3 non-zero dwells: To Do, In Progress, Review.');
        $this->assertSame(TaskStatus::Todo, $dwells[0]['status']);
        $this->assertSame(TaskStatus::InProgress, $dwells[1]['status']);
        $this->assertSame(TaskStatus::Review, $dwells[2]['status']);

        $this->assertSame((int) $t0->diffInSeconds($t1), (int) $dwells[0]['seconds']);
        $this->assertSame((int) $t1->diffInSeconds($t2), (int) $dwells[1]['seconds']);
        $this->assertSame((int) $t2->diffInSeconds($t3), (int) $dwells[2]['seconds']);

        $this->assertSame((int) $t0->diffInSeconds($t3), (int) $cycleSeconds);
    }

    public function test_status_dwell_fallback_when_no_status_logs(): void
    {
        $service = app(NativeTaskService::class);
        $lifecycle = app(TaskLifecycle::class);

        $t0 = Carbon::parse('2026-08-20 10:00:00');
        $t1 = Carbon::parse('2026-08-20 13:00:00'); // +3h

        Carbon::setTestNow($t0);

        $task = $service->createTask([
            'title' => 'Lifecycle Fallback Task',
            'current_state_id' => $this->todoState->id,
        ], $this->employee);

        Carbon::setTestNow($t1);

        $task = $task->fresh()->load(['activityLogs', 'currentState']);

        $segments = $lifecycle->segments($task, $t1);

        $this->assertCount(1, $segments, 'When no state change logs exist, we should show a single dwell segment.');
        $this->assertSame(TaskStatus::Todo, $segments[0]['status']);
        $this->assertSame((int) $t0->diffInSeconds($t1), (int) $segments[0]['seconds']);
    }
}
