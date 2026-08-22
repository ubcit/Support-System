<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;
use Modules\Tasks\Models\Task;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowState;
use Modules\Workflows\Models\WorkflowTransition;
use Tests\TestCase;

class NativeTaskPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default workflow
        $workflow = Workflow::create([
            'name' => 'Default Task Workflow',
            'entity_type' => 'task',
            'is_default' => true,
        ]);

        $todoState = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'To Do',
            'type' => 'initial',
            'order' => 1,
        ]);

        $inProgressState = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'In Progress',
            'type' => 'active',
            'order' => 2,
        ]);

        $doneState = WorkflowState::create([
            'workflow_id' => $workflow->id,
            'name' => 'Done',
            'type' => 'completed',
            'order' => 3,
        ]);

        // Register valid transitions
        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => $todoState->id,
            'to_state_id' => $inProgressState->id,
        ]);

        WorkflowTransition::create([
            'workflow_id' => $workflow->id,
            'from_state_id' => $inProgressState->id,
            'to_state_id' => $doneState->id,
        ]);

        // Create Employee
        $employee = Employee::create([
            'name' => 'John Developer',
            'email' => 'john@thespace.app',
            'role' => 'Software Engineer',
        ]);
    }

    public function test_native_task_lifecycle_subtasks_checklists_and_time_tracking()
    {
        $employee = Employee::first();
        $state = WorkflowState::first();

        // 1. Create Native Task
        $taskService = app(\Modules\Tasks\Services\NativeTaskService::class);
        $task = $taskService->createTask([
            'title' => 'Implement Native Authentication Service',
            'description' => 'Build JWT authentication layer.',
            'priority' => 'high',
            'current_state_id' => $state->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'estimated_hours' => 8.0,
            'assignee_ids' => [$employee->id],
        ], $employee);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Implement Native Authentication Service',
        ]);

        // 2. Create Subtask
        $subtask = $taskService->createSubtask($task, [
            'title' => 'Configure OAuth2 Callbacks',
        ], $employee);

        $this->assertEquals($task->id, $subtask->parent_id);

        // 3. Add Checklist
        $checklist = $taskService->addChecklist($task, 'Definition of Done', [
            'Code compiled',
            'Unit tests passed',
        ]);

        $this->assertCount(2, $checklist->items);

        // Toggle Item
        $firstItem = $checklist->items->first();
        $taskService->toggleChecklistItem($firstItem, true, $employee);

        $this->assertTrue($firstItem->fresh()->is_completed);

        // 4. Time Tracking
        $timeService = app(\Modules\Tasks\Services\TimeTrackingService::class);
        $timer = $timeService->startTimer($task, $employee, 'Writing unit tests');
        $this->assertTrue($timer->is_running);

        $stoppedTimer = $timeService->stopTimer($timer);
        $this->assertFalse($stoppedTimer->is_running);
    }

    public function test_kanban_engine_board_data_and_card_movement()
    {
        $employee = Employee::first();
        $states = WorkflowState::orderBy('order')->get();
        $toDoState = $states[0];
        $inProgressState = $states[1];
        $workflow = Workflow::first();

        $taskService = app(\Modules\Tasks\Services\NativeTaskService::class);
        $task = $taskService->createTask([
            'title' => 'Kanban Test Task',
            'workflow_id' => $workflow->id,
            'current_state_id' => $toDoState->id,
        ], $employee);

        $kanbanService = app(\Modules\Tasks\Services\KanbanEngineService::class);
        $boardData = $kanbanService->getBoardData();

        $this->assertCount(3, $boardData['columns']);

        // Move card to In Progress
        $result = $kanbanService->moveCard($task, $inProgressState->id, 1, $employee);
        $this->assertTrue($result['success']);
        $this->assertEquals($inProgressState->id, $task->fresh()->current_state_id);
    }

    public function test_employee_and_boss_workspace_services()
    {
        $employee = Employee::first();
        $state = WorkflowState::first();

        $taskService = app(\Modules\Tasks\Services\NativeTaskService::class);
        $task = $taskService->createTask([
            'title' => 'Workspace Inspection Task',
            'current_state_id' => $state->id,
            'assignee_ids' => [$employee->id],
        ], $employee);

        // Search Service test
        $searchService = app(\Modules\Tasks\Services\GlobalSearchService::class);
        $searchResults = $searchService->search('Workspace Inspection');

        $this->assertNotEmpty($searchResults['results']['tasks']);
    }

    public function test_ai_copilot_service_recommendation_only_governance()
    {
        $task = Task::create([
            'title' => 'AI Copilot Audit Task',
            'description' => 'Verify AI returns suggestions only and never mutates state directly.',
        ]);

        $copilot = app(\Modules\Tasks\Services\TaskAICopilotService::class);
        $summary = $copilot->summarizeTask($task);

        $this->assertArrayHasKey('disclaimer', $summary);
        $this->assertStringContainsString('Human approval required', $summary['disclaimer']);
    }
}
