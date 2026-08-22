<?php

namespace Modules\Attachments\Repositories;

use App\Core\Contracts\RepositoryInterface;

interface AttachmentRepositoryInterface extends RepositoryInterface
{
    public function getByAttachable(string $type, int $id);
    public function getByType(string $type);
}
