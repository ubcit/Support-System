<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Projects\Models\Project;
use Modules\Projects\Support\ProjectCodeGenerator;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'code' => app(ProjectCodeGenerator::class)->generate(),
            'customer_id' => CustomerFactory::new(),
            'name' => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'status' => 'active',
            'workspace_id' => fn () => Workspace::first()?->id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Project $project) {
            if ($project->customer_id) {
                $project->customers()->syncWithoutDetaching([$project->customer_id]);
            }
        });
    }
}
