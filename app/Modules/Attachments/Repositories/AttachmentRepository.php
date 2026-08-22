<?php

namespace Modules\Attachments\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\Attachments\Models\Attachment;

class AttachmentRepository extends BaseRepository implements AttachmentRepositoryInterface
{
    public function __construct(Attachment $model)
    {
        parent::__construct($model);
    }

    public function getByAttachable(string $type, int $id): Collection
    {
        return $this->newQuery()
            ->where('attachable_type', $type)
            ->where('attachable_id', $id)
            ->get();
    }

    public function getByType(string $type): Collection
    {
        return $this->newQuery()->where('type', $type)->get();
    }
}
