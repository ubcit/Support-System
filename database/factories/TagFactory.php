<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tasks\Models\Tag;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('tag-????'),
            'color' => fake()->randomElement([
                '#6B7280',
                '#EF4444',
                '#F59E0B',
                '#10B981',
                '#3B82F6',
                '#8B5CF6',
                '#EC4899',
            ]),
            'workspace_id' => fn () => \Modules\MultiTenancy\Models\Workspace::first()?->id,
        ];
    }
}
