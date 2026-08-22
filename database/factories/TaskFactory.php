<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Tasks\Models\Task;

/**
 * @extends Factory<Task>
 *
 * Deliberately doesn't set `current_state_id` / a workflow: Task::status is
 * a virtual attribute resolved via the currentState relation (see
 * Task::getStatusAttribute()), so exercising status transitions requires a
 * real seeded Workflow/WorkflowState, which is scenario-specific and best
 * left to the test itself (as the rest of the suite already does) rather
 * than baked into every factory-made Task.
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'type' => 'task',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'workspace_id' => fn () => \Modules\MultiTenancy\Models\Workspace::first()?->id,
        ];
    }
}
