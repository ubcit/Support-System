<?php

namespace Modules\Communication\Repositories;

use App\Core\Contracts\RepositoryInterface;

interface ConversationRepositoryInterface extends RepositoryInterface
{
    public function getActiveByCustomer(int $customerId);
    public function getByChannel(string $channel);
}
