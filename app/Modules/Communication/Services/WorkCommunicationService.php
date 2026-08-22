<?php

namespace Modules\Communication\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Communication\Events\CommunicationCreated;
use Modules\Communication\Models\WorkCommunication;

class WorkCommunicationService
{
    /**
     * Log a system-generated event (e.g. status transition, assignment).
     */
    public function logSystemEvent(Model $target, string $type, string $content, array $metadata = []): WorkCommunication
    {
        $communication = WorkCommunication::create([
            'communicable_type' => $target->getMorphClass(),
            'communicable_id' => $target->id,
            'type' => $type,
            'visibility' => 'system',
            'status' => 'published',
            'content' => $content,
            'metadata' => $metadata,
        ]);

        CommunicationCreated::dispatch($communication);

        return $communication;
    }

    /**
     * Post a rich comment (human or AI).
     */
    public function postComment(Model $target, ?Model $author, string $type, string $content, string $visibility = 'internal', ?int $parentId = null): WorkCommunication
    {
        $communication = WorkCommunication::create([
            'communicable_type' => $target->getMorphClass(),
            'communicable_id' => $target->id,
            'parent_id' => $parentId,
            'author_type' => $author?->getMorphClass(),
            'author_id' => $author?->id,
            'type' => $type,
            'visibility' => $visibility,
            'status' => 'published',
            'content' => $content,
        ]);

        CommunicationCreated::dispatch($communication);

        return $communication;
    }
}
