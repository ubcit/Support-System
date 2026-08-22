<?php

namespace App\Helpers;

use Modules\Projects\Models\Project;

class ProjectNavHelper
{
    /**
     * Flat project list for the sidebar.
     *
     * @return array<int, array{id: int|string, uuid: string|null, name: string}>
     */
    public static function getProjects(): array
    {
        return Project::query()
            ->select('id', 'uuid', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'name' => $project->name,
            ])
            ->all();
    }
}
