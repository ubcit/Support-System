<?php

namespace Modules\Communication\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\Communication\Models\Conversation;

class ConversationRepository extends BaseRepository implements ConversationRepositoryInterface
{
    public function __construct(Conversation $model)
    {
        parent::__construct($model);
    }

    public function getActiveByCustomer(int $customerId): Collection
    {
        return $this->newQuery()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->get();
    }

    public function getByChannel(string $channel): Collection
    {
        return $this->newQuery()->where('channel', $channel)->get();
    }
}
