<?php

namespace Tests\Feature;

use Database\Seeders\EssentialPlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tasks\Models\TaskActivityLog;
use Modules\Workflows\Models\WorkflowState;
use Tests\TestCase;

class TaskActivityLogSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([EssentialPlatformSeeder::class]);
    }

    public function test_summary_uses_human_readable_labels_instead_of_raw_actions(): void
    {
        $todo = WorkflowState::query()->where('name', 'To Do')->firstOrFail();
        $done = WorkflowState::query()->where('name', 'Done')->firstOrFail();

        $this->assertSame(
            'updated the assignees',
            (new TaskActivityLog(['action' => 'assignees_changed']))->summary()
        );
        $this->assertSame(
            'approved this task',
            (new TaskActivityLog(['action' => 'task_approved']))->summary()
        );
        $this->assertSame(
            'requested changes',
            (new TaskActivityLog(['action' => 'changes_requested']))->summary()
        );
        $this->assertSame(
            "moved status from {$todo->name} to {$done->name}",
            (new TaskActivityLog([
                'action' => 'state_moved',
                'old_value' => (string) $todo->id,
                'new_value' => (string) $done->id,
            ]))->summary()
        );
        $this->assertSame(
            'changed priority from low to high',
            (new TaskActivityLog([
                'action' => 'field_updated',
                'field' => 'priority',
                'old_value' => 'low',
                'new_value' => 'high',
            ]))->summary()
        );
    }
}
